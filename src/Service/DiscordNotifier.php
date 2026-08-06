<?php

namespace App\Service;

use App\Entity\TradeOffer;
use App\Entity\User;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\Multipart\FormDataPart;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class DiscordNotifier
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly string $projectDir,
        private readonly ?string $webhookUrl,
        private readonly ?string $tradeWebhookUrl = null,
    ) {
    }

    public function notifyDrop(
        string $pseudo,
        string $cardName,
        string $category,
        string $rarity,
        string $type,
        ?string $imagePath,
    ): void {
        if (!$this->webhookUrl) {
            return;
        }

        $embed = [
            'title' => $cardName,
            'description' => "🎁 **{$pseudo}** a obtenu une nouvelle carte !",
            'color' => $this->colorForRarity($rarity),
            'fields' => [
                ['name' => 'Catégorie', 'value' => $category, 'inline' => true],
                ['name' => 'Rareté', 'value' => $rarity, 'inline' => true],
                ['name' => 'Type', 'value' => $type, 'inline' => true],
            ],
        ];

        $this->send($this->webhookUrl, $embed, $this->resolveImagePath($imagePath));
    }

    /**
     * Prévient le destinataire qu'une offre d'échange l'attend.
     */
    public function notifyTradeProposed(TradeOffer $offer): void
    {
        $proposer = $offer->getProposer();
        $recipient = $offer->getRecipient();

        $isCounter = $offer->getParentOffer() !== null;
        $verb = $isCounter ? 'a envoyé une contre-offre à' : 'propose un échange à';

        $this->send($this->tradeWebhookUrl, [
            'title' => $isCounter ? "🔁 Contre-offre d'échange" : '🔁 Nouvelle offre d\'échange',
            'description' => \sprintf('**%s** %s **%s**.', $proposer->getPseudo(), $verb, $recipient->getPseudo()),
            'color' => 0xE76D0A,
            'url' => $this->tradeUrl($offer),
            'fields' => [
                [
                    'name' => \sprintf('%s donne', $proposer->getPseudo()),
                    'value' => $this->describeSide($offer, $proposer),
                    'inline' => true,
                ],
                [
                    'name' => \sprintf('%s donne', $recipient->getPseudo()),
                    'value' => $this->describeSide($offer, $recipient),
                    'inline' => true,
                ],
            ],
        ]);
    }

    /**
     * Prévient que l'échange a été conclu et les cartes transférées.
     */
    public function notifyTradeAccepted(TradeOffer $offer): void
    {
        $proposer = $offer->getProposer();
        $recipient = $offer->getRecipient();

        $this->send($this->tradeWebhookUrl, [
            'title' => '✅ Échange conclu',
            'description' => \sprintf(
                '**%s** a accepté l\'offre de **%s**. Les cartes ont été transférées !',
                $recipient->getPseudo(),
                $proposer->getPseudo()
            ),
            'color' => 0x4CAF50,
            'url' => $this->tradeUrl($offer),
            'fields' => [
                [
                    'name' => \sprintf('%s reçoit', $recipient->getPseudo()),
                    'value' => $this->describeSide($offer, $proposer),
                    'inline' => true,
                ],
                [
                    'name' => \sprintf('%s reçoit', $proposer->getPseudo()),
                    'value' => $this->describeSide($offer, $recipient),
                    'inline' => true,
                ],
            ],
        ]);
    }

    /**
     * Liste les cartes mises sur la table par un joueur, au format lisible dans un embed.
     */
    private function describeSide(TradeOffer $offer, User $user): string
    {
        $lines = [];

        foreach ($offer->getItemsOfferedBy($user) as $item) {
            $card = $item->getCard();
            if ($card !== null) {
                $lines[] = \sprintf('• %s x%d', $card->getNom(), $item->getQuantity());
            }
        }

        // Un champ d'embed Discord ne peut pas être vide et est plafonné à 1024 caractères.
        if (!$lines) {
            return '—';
        }

        $value = implode("\n", $lines);

        return mb_strlen($value) > 1024 ? mb_substr($value, 0, 1021) . '...' : $value;
    }

    private function tradeUrl(TradeOffer $offer): ?string
    {
        try {
            return $this->urlGenerator->generate(
                'app_trade_show',
                ['id' => $offer->getId()],
                UrlGeneratorInterface::ABSOLUTE_URL
            );
        } catch (\Throwable) {
            // Hors contexte HTTP (commande console) sans default_uri configuré.
            return null;
        }
    }

    /**
     * Envoie un embed sur un webhook, avec pièce jointe optionnelle.
     *
     * @param array<string, mixed> $embed
     */
    private function send(?string $webhookUrl, array $embed, ?string $absoluteImagePath = null): void
    {
        if (!$webhookUrl) {
            return;
        }

        if (($embed['url'] ?? null) === null) {
            unset($embed['url']);
        }

        try {
            if ($absoluteImagePath) {
                $filename = basename($absoluteImagePath);
                $embed['image'] = ['url' => 'attachment://' . $filename];

                $formData = new FormDataPart([
                    'payload_json' => json_encode(['embeds' => [$embed]], JSON_THROW_ON_ERROR),
                    'files[0]' => DataPart::fromPath($absoluteImagePath, $filename),
                ]);

                $response = $this->httpClient->request('POST', $webhookUrl, [
                    'headers' => $formData->getPreparedHeaders()->toArray(),
                    'body' => $formData->bodyToString(),
                ]);
            } else {
                $response = $this->httpClient->request('POST', $webhookUrl, [
                    'json' => ['embeds' => [$embed]],
                ]);
            }

            // Les réponses du client HTTP sont "lazy" : la requête ne part vraiment
            // que lorsqu'on consulte la réponse (ex: getStatusCode()).
            $status = $response->getStatusCode();
            if ($status >= 300) {
                $this->logger->warning(\sprintf('Discord a refusé la notification (HTTP %d): %s', $status, $response->getContent(false)));
            }
        } catch (\Throwable $e) {
            // Un souci Discord (rate limit, réseau, webhook invalide) ne doit jamais faire échouer l'action métier.
            $this->logger->warning('Échec de la notification Discord', ['exception' => $e]);
        }
    }

    private function colorForRarity(string $rarity): int
    {
        return match (mb_strtolower($rarity)) {
            'communes' => 0x95A5A6,
            'rares' => 0x3498DB,
            'épiques', 'epiques' => 0x9B59B6,
            'légendaires', 'legendaires' => 0xF1C40F,
            'mythiques' => 0xE74C3C,
            default => 0x2ECC71,
        };
    }

    private function resolveImagePath(?string $path): ?string
    {
        if (!$path || str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return null;
        }

        $absolute = $this->projectDir . '/public/' . ltrim($path, '/');

        return is_file($absolute) ? $absolute : null;
    }
}
