<?php

namespace App\Service;

use App\Entity\TradeOffer;
use App\Entity\User;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\Multipart\FormDataPart;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class DiscordNotifier
{
    /** Discord plafonne un message à 10 embeds et 10 pièces jointes. */
    private const MAX_EMBEDS = 10;

    /**
     * Filet de sécurité : au-delà, on repart sur un message neuf même si le
     * destinataire n'a pas changé. Éditer un message vieux de plusieurs heures
     * le laisserait enfoui dans l'historique du salon, invisible.
     */
    private const BATCH_TTL = 1800;

    /** Un seul message de drop est "ouvert" à la fois : c'est toujours le dernier publié. */
    private const BATCH_KEY = 'discord_drop_current_batch';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly CacheItemPoolInterface $cache,
        private readonly string $publicDir,
        private readonly ?string $webhookUrl,
        private readonly ?string $tradeWebhookUrl = null,
    ) {
    }

    public function notifyDrop(
        User $user,
        string $cardName,
        string $category,
        string $rarity,
        string $type,
        ?string $imagePath,
    ): void {
        if (!$this->webhookUrl) {
            return;
        }

        $absoluteImagePath = $this->resolveImagePath($imagePath);
        if ($imagePath && !$absoluteImagePath) {
            $this->logger->warning('Image de carte introuvable, notification Discord envoyée sans visuel', [
                'imagePath' => $imagePath,
                'publicDir' => $this->publicDir,
            ]);
        }

        $embed = [
            'title' => $cardName,
            'description' => \sprintf('🎁 **%s** a obtenu une nouvelle carte !', $user->getPseudo()),
            'color' => $this->colorForRarity($rarity),
            'fields' => [
                ['name' => 'Catégorie', 'value' => $category, 'inline' => true],
                ['name' => 'Rareté', 'value' => $rarity, 'inline' => true],
                ['name' => 'Type', 'value' => $type, 'inline' => true],
            ],
        ];

        // Les drops s'accumulent dans un même message tant qu'il s'agit du même
        // joueur et du même type. Dès qu'on attribue à quelqu'un d'autre (ou qu'on
        // passe des animes aux films), le message précédent est clos et un nouveau
        // s'ouvre : on n'édite ainsi jamais autre chose que le dernier message du salon.
        $owner = $this->ownerKey($user, $type);

        try {
            $batch = $this->loadBatch($owner);

            if ($batch !== null && \count($batch['embeds']) < self::MAX_EMBEDS) {
                $this->appendToBatch($owner, $user, $batch, $embed, $absoluteImagePath);
            } else {
                $this->startBatch($owner, $user, $embed, $absoluteImagePath);
            }
        } catch (\Throwable $e) {
            // Un souci Discord (rate limit, réseau, webhook invalide) ne doit jamais faire échouer l'attribution de carte.
            $this->logger->warning('Échec de la notification Discord', ['exception' => $e]);
        }
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
        ], $recipient);
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
        ], $proposer);
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
     * Envoie un embed isolé sur un webhook, avec pièce jointe optionnelle.
     * Utilisé par les notifications d'échange, qui ne sont pas regroupées.
     *
     * @param array<string, mixed> $embed
     * @param User|null            $mention joueur à notifier, s'il a renseigné son identifiant Discord
     */
    private function send(?string $webhookUrl, array $embed, ?User $mention = null, ?string $absoluteImagePath = null): void
    {
        if (!$webhookUrl) {
            return;
        }

        if (($embed['url'] ?? null) === null) {
            unset($embed['url']);
        }

        $filename = null;
        if ($absoluteImagePath !== null) {
            $filename = $this->attachmentName(0, $absoluteImagePath);
            $embed['image'] = ['url' => 'attachment://' . $filename];
        }

        $payload = ['embeds' => [$embed]] + $this->mentionPayload($mention);

        try {
            $this->request('POST', $webhookUrl, $payload, $absoluteImagePath, $filename);
        } catch (\Throwable $e) {
            // Un souci Discord (rate limit, réseau, webhook invalide) ne doit jamais faire échouer l'action métier.
            $this->logger->warning('Échec de la notification Discord', ['exception' => $e]);
        }
    }

    /**
     * Première carte de la fenêtre : on crée un message et on retient son id pour pouvoir l'enrichir ensuite.
     *
     * @param array<string, mixed> $embed
     */
    private function startBatch(string $owner, User $user, array $embed, ?string $absoluteImagePath): void
    {
        $filename = $absoluteImagePath ? $this->attachmentName(0, $absoluteImagePath) : null;
        if ($filename !== null) {
            $embed['image'] = ['url' => 'attachment://' . $filename];
        }

        $payload = ['embeds' => [$embed]] + $this->mentionPayload($user);

        // wait=true force Discord à renvoyer le message créé, dont on a besoin pour l'éditer ensuite.
        $message = $this->request('POST', $this->webhookUrl . '?wait=true', $payload, $absoluteImagePath, $filename);
        if ($message === null) {
            return;
        }

        $this->saveBatch($owner, [
            'messageId' => (string) $message['id'],
            'embeds' => [$embed],
            'attachmentIds' => $this->attachmentIds($message),
        ]);
    }

    /**
     * Cartes suivantes : on édite le message existant au lieu d'en publier un nouveau.
     *
     * @param array{messageId: string, embeds: list<array<string, mixed>>, attachmentIds: list<string>} $batch
     * @param array<string, mixed>                                                                      $embed
     */
    private function appendToBatch(string $owner, User $user, array $batch, array $embed, ?string $absoluteImagePath): void
    {
        $index = \count($batch['embeds']);
        $filename = $absoluteImagePath ? $this->attachmentName($index, $absoluteImagePath) : null;
        if ($filename !== null) {
            $embed['image'] = ['url' => 'attachment://' . $filename];
        }

        $embeds = [...$batch['embeds'], $embed];

        // Sur un PATCH, "attachments" fait autorité : toute pièce jointe non listée
        // est retirée du message, il faut donc rappeler celles déjà présentes.
        $attachments = [];
        foreach ($batch['attachmentIds'] as $existingId) {
            $attachments[] = ['id' => $existingId];
        }
        if ($filename !== null) {
            // Pour une nouvelle pièce jointe, l'id est l'index du fichier envoyé dans cette requête.
            $attachments[] = ['id' => 0, 'filename' => $filename];
        }

        $payload = ['embeds' => $embeds, 'attachments' => $attachments];

        // On ne renvoie pas "content" : sur un PATCH, un champ omis reste inchangé,
        // la mention du premier message est donc conservée sans re-notifier.
        $message = $this->request(
            'PATCH',
            $this->webhookUrl . '/messages/' . $batch['messageId'],
            $payload,
            $absoluteImagePath,
            $filename
        );

        if ($message === null) {
            // Message supprimé ou introuvable : on repart sur un message neuf
            // plutôt que de perdre la notification.
            $this->cache->deleteItem(self::BATCH_KEY);
            $this->startBatch($owner, $user, $embed, $absoluteImagePath);

            return;
        }

        $this->saveBatch($owner, [
            'messageId' => $batch['messageId'],
            'embeds' => $embeds,
            'attachmentIds' => $this->attachmentIds($message),
        ]);
    }

    /**
     * Une mention ne notifie que si elle est dans le "content" : placée dans un
     * embed, Discord l'affiche en surbrillance mais ne prévient personne.
     *
     * @return array<string, mixed>
     */
    private function mentionPayload(?User $user): array
    {
        $discordId = $user?->getDiscordId();

        if (!$discordId) {
            return [];
        }

        return [
            'content' => \sprintf('<@%s>', $discordId),
            'allowed_mentions' => ['parse' => [], 'users' => [$discordId]],
        ];
    }

    /**
     * Exécute la requête, en multipart si une image doit être jointe.
     *
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>|null le message renvoyé par Discord, ou null si l'appel a échoué
     */
    private function request(string $method, string $url, array $payload, ?string $absoluteImagePath, ?string $filename): ?array
    {
        if ($absoluteImagePath !== null && $filename !== null) {
            $formData = new FormDataPart([
                'payload_json' => json_encode($payload, JSON_THROW_ON_ERROR),
                'files[0]' => DataPart::fromPath($absoluteImagePath, $filename),
            ]);

            $response = $this->httpClient->request($method, $url, [
                'headers' => $formData->getPreparedHeaders()->toArray(),
                'body' => $formData->bodyToString(),
            ]);
        } else {
            $response = $this->httpClient->request($method, $url, ['json' => $payload]);
        }

        // Les réponses du client HTTP sont "lazy" : la requête ne part vraiment
        // que lorsqu'on consulte la réponse (ex: getStatusCode()).
        $status = $response->getStatusCode();
        if ($status >= 300) {
            $this->logger->warning(\sprintf('Discord a refusé la notification (HTTP %d): %s', $status, $response->getContent(false)));

            return null;
        }

        // Un webhook sans "wait=true" répond 204 sans corps : rien à décoder.
        return 204 === $status ? null : $response->toArray(false);
    }

    /**
     * Préfixe le nom de fichier par son rang : deux exemplaires de la même carte
     * dans un message donneraient sinon deux pièces jointes homonymes, et
     * "attachment://" ne saurait plus laquelle désigner.
     */
    private function attachmentName(int $index, string $absoluteImagePath): string
    {
        return $index . '_' . basename($absoluteImagePath);
    }

    /**
     * @param array<string, mixed> $message
     *
     * @return list<string>
     */
    private function attachmentIds(array $message): array
    {
        return array_map(
            static fn (array $attachment): string => (string) $attachment['id'],
            $message['attachments'] ?? []
        );
    }

    /**
     * Identifie le destinataire du message en cours : joueur + type de carte.
     * Deux drops partagent un message si et seulement si cette clé est identique.
     */
    private function ownerKey(User $user, string $type): string
    {
        return $user->getId() . ':' . $type;
    }

    /**
     * Renvoie le message en cours s'il appartient bien à ce destinataire.
     * Si le précédent drop concernait quelqu'un d'autre, on repart de zéro.
     *
     * @return array{owner: string, messageId: string, embeds: list<array<string, mixed>>, attachmentIds: list<string>}|null
     */
    private function loadBatch(string $owner): ?array
    {
        $item = $this->cache->getItem(self::BATCH_KEY);
        if (!$item->isHit()) {
            return null;
        }

        $batch = $item->get();

        return ($batch['owner'] ?? null) === $owner ? $batch : null;
    }

    /**
     * @param array{messageId: string, embeds: list<array<string, mixed>>, attachmentIds: list<string>} $batch
     */
    private function saveBatch(string $owner, array $batch): void
    {
        $item = $this->cache->getItem(self::BATCH_KEY);
        $item->set(['owner' => $owner] + $batch);
        $item->expiresAfter(self::BATCH_TTL);
        $this->cache->save($item);
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

        $absolute = rtrim($this->publicDir, '/\\') . '/' . ltrim($path, '/');

        return is_file($absolute) ? $absolute : null;
    }
}
