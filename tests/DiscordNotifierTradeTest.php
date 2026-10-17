<?php

namespace App\Tests;

use App\Entity\Anime;
use App\Entity\CardAnime;
use App\Entity\Rarities;
use App\Entity\TradeOffer;
use App\Entity\TradeOfferItem;
use App\Entity\User;
use App\Service\DiscordNotifier;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Les notifications d'échange doivent montrer les cartes engagées, comme les drops :
 * un embed par carte, avec son image jointe au message.
 */
class DiscordNotifierTradeTest extends TestCase
{
    private string $publicDir;

    /** @var list<array{method: string, options: array<string, mixed>}> */
    private array $requests = [];

    protected function setUp(): void
    {
        $this->publicDir = sys_get_temp_dir() . '/rasengan_trade_test_' . uniqid();
        mkdir($this->publicDir . '/images', 0777, true);
        file_put_contents($this->publicDir . '/images/naruto.png', 'png');
    }

    protected function tearDown(): void
    {
        @unlink($this->publicDir . '/images/naruto.png');
        @rmdir($this->publicDir . '/images');
        @rmdir($this->publicDir);
    }

    public function testProposedTradeHasOneEmbedPerCardWithImage(): void
    {
        $offer = $this->offer();

        $this->notifier()->notifyTradeProposed($offer);

        self::assertCount(1, $this->requests);
        $body = $this->requests[0]['options']['body'];

        // Une image trouvée => envoi multipart, avec le payload JSON et la pièce jointe.
        self::assertStringContainsString('name="files[0]"; filename="1_naruto.png"', $body);
        self::assertSame(1, preg_match('~name="payload_json"\r\n\r\n(.*?)\r\n--~s', $body, $matches));
        $payload = json_decode($matches[1], true, flags: JSON_THROW_ON_ERROR);

        self::assertCount(3, $payload['embeds'], 'Récapitulatif + une carte par item.');
        self::assertSame('Naruto', $payload['embeds'][1]['title']);
        self::assertSame('attachment://1_naruto.png', $payload['embeds'][1]['image']['url']);
        self::assertSame('Sasuke x2', $payload['embeds'][2]['title']);
        self::assertArrayNotHasKey('image', $payload['embeds'][2], 'Image absente : embed sans visuel.');
        self::assertStringContainsString('**Alice** ➜ **Bob**', $payload['embeds'][1]['description']);
    }

    public function testAcceptedTradeWithoutImagesIsSentAsJson(): void
    {
        $offer = $this->offer(withImage: false);

        $this->notifier()->notifyTradeAccepted($offer);

        $payload = json_decode($this->requests[0]['options']['body'], true, flags: JSON_THROW_ON_ERROR);
        self::assertCount(3, $payload['embeds']);
    }

    private function notifier(): DiscordNotifier
    {
        $client = new MockHttpClient(function (string $method, string $url, array $options) {
            $this->requests[] = ['method' => $method, 'options' => $options];

            return new MockResponse('', ['http_code' => 204]);
        });

        return new DiscordNotifier(
            $client,
            new NullLogger(),
            $this->createMock(UrlGeneratorInterface::class),
            new ArrayAdapter(),
            new RequestStack(),
            $this->publicDir,
            null,
            'https://discord.test/api/webhooks/2/token',
        );
    }

    private function offer(bool $withImage = true): TradeOffer
    {
        $alice = (new User())->setPseudo('Alice');
        $bob = (new User())->setPseudo('Bob');
        $anime = (new Anime())->setNom('Naruto Shippuden');
        $rare = (new Rarities())->setLibelle('Rares');

        $offer = (new TradeOffer())->setProposer($alice)->setRecipient($bob);
        $offer->addItem((new TradeOfferItem())->setOwner($alice)->setQuantity(1)->setCardAnime(
            (new CardAnime())->setNom('Naruto')->setRarity($rare)->setAnime($anime)
                ->setImagePath($withImage ? 'images/naruto.png' : null)
        ));
        $offer->addItem((new TradeOfferItem())->setOwner($bob)->setQuantity(2)->setCardAnime(
            (new CardAnime())->setNom('Sasuke')->setRarity($rare)->setAnime($anime)->setImagePath('images/absente.png')
        ));

        return $offer;
    }
}
