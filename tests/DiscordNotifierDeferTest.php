<?php

namespace App\Tests;

use App\Entity\User;
use App\Service\DiscordNotifier;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Pendant une requête HTTP, l'appel au webhook doit partir après la réponse
 * (kernel.terminate), pour qu'un Discord lent ne ralentisse pas la roue.
 */
class DiscordNotifierDeferTest extends TestCase
{
    private MockHttpClient $httpClient;
    private RequestStack $requestStack;
    private DiscordNotifier $notifier;

    protected function setUp(): void
    {
        $this->httpClient = new MockHttpClient(
            fn () => new MockResponse('{"id": "42", "attachments": []}', ['http_code' => 200]),
        );
        $this->requestStack = new RequestStack();

        $this->notifier = new DiscordNotifier(
            $this->httpClient,
            new NullLogger(),
            $this->createMock(UrlGeneratorInterface::class),
            new ArrayAdapter(),
            $this->requestStack,
            sys_get_temp_dir(),
            'https://discord.test/api/webhooks/1/token',
        );
    }

    public function testDropDuringHttpRequestIsSentOnTerminate(): void
    {
        $this->requestStack->push(new Request());

        $this->notifyDrop();
        self::assertSame(0, $this->httpClient->getRequestsCount(), 'Rien ne doit partir avant la réponse.');

        $this->notifier->flushPending();
        self::assertSame(1, $this->httpClient->getRequestsCount());

        $this->notifier->flushPending();
        self::assertSame(1, $this->httpClient->getRequestsCount(), 'Une tâche ne doit être exécutée qu\'une fois.');
    }

    public function testDropOutsideHttpRequestIsSentImmediately(): void
    {
        $this->notifyDrop();

        self::assertSame(1, $this->httpClient->getRequestsCount());
    }

    /**
     * Le second drop édite le message créé par le premier : l'ordre d'exécution doit
     * être conservé pour que le regroupement fonctionne.
     */
    public function testQueuedDropsKeepTheirOrder(): void
    {
        $this->requestStack->push(new Request());

        $this->notifyDrop();
        $this->notifyDrop();
        $this->notifier->flushPending();

        self::assertSame(2, $this->httpClient->getRequestsCount());
    }

    private function notifyDrop(): void
    {
        $this->notifier->notifyDrop((new User())->setPseudo('Alice'), 'Naruto', 'Naruto', 'Communes', 'Anime', null);
    }
}
