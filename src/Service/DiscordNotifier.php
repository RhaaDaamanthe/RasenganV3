<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\Multipart\FormDataPart;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class DiscordNotifier
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly string $projectDir,
        private readonly ?string $webhookUrl,
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

        $absoluteImagePath = $this->resolveImagePath($imagePath);

        try {
            if ($absoluteImagePath) {
                $filename = basename($absoluteImagePath);
                $embed['image'] = ['url' => 'attachment://' . $filename];

                $formData = new FormDataPart([
                    'payload_json' => json_encode(['embeds' => [$embed]], JSON_THROW_ON_ERROR),
                    'files[0]' => DataPart::fromPath($absoluteImagePath, $filename),
                ]);

                $response = $this->httpClient->request('POST', $this->webhookUrl, [
                    'headers' => $formData->getPreparedHeaders()->toArray(),
                    'body' => $formData->bodyToString(),
                ]);
            } else {
                $response = $this->httpClient->request('POST', $this->webhookUrl, [
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
            // Un souci Discord (rate limit, réseau, webhook invalide) ne doit jamais faire échouer l'attribution de carte.
            $this->logger->warning('Échec de la notification Discord', ['exception' => $e]);
        }
    }

    private function colorForRarity(string $rarity): int
    {
        return match (mb_strtolower($rarity)) {
            'commune' => 0x95A5A6,
            'rare' => 0x3498DB,
            'épique', 'epique' => 0x9B59B6,
            'légendaire', 'legendaire' => 0xF1C40F,
            'mythique' => 0xE74C3C,
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
