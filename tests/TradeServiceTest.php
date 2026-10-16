<?php

namespace App\Tests;

use App\Entity\CardAnime;
use App\Entity\TradeOffer;
use App\Entity\TradeOfferItem;
use App\Entity\User;
use App\Entity\UserCardAnime;
use App\Repository\TradeOfferRepository;
use App\Service\BadgeService;
use App\Service\DiscordNotifier;
use App\Service\TradeService;
use App\Service\WishlistService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Règles de validation des échanges, sans base : les repositories sont simulés à partir
 * d'un inventaire en mémoire.
 */
class TradeServiceTest extends TestCase
{
    private User $alice;
    private User $bob;

    /** @var array<int, CardAnime> */
    private array $cards = [];

    /** @var list<UserCardAnime> */
    private array $inventory = [];

    private EntityManagerInterface&MockObject $entityManager;
    private DiscordNotifier&MockObject $discordNotifier;
    private TradeService $service;

    protected function setUp(): void
    {
        $this->alice = (new User())->setPseudo('Alice');
        $this->bob = (new User())->setPseudo('Bob');

        $this->cards = [
            1 => $this->card(1, 'Naruto'),
            2 => $this->card(2, 'Sasuke'),
        ];

        // Alice possède 1 Naruto, Bob possède 3 Sasuke.
        $this->own($this->alice, $this->cards[1], 1);
        $this->own($this->bob, $this->cards[2], 3);

        $cardRepository = $this->createMock(EntityRepository::class);
        $cardRepository->method('find')->willReturnCallback(fn ($id) => $this->cards[$id] ?? null);

        $userCardRepository = $this->createMock(EntityRepository::class);
        $userCardRepository->method('findOneBy')->willReturnCallback(function (array $criteria) {
            foreach ($this->inventory as $userCard) {
                if ($userCard->getUser() === $criteria['user'] && $userCard->getCardAnime() === $criteria['cardAnime']) {
                    return $userCard;
                }
            }

            return null;
        });

        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->entityManager->method('getRepository')->willReturnCallback(fn (string $class) => match ($class) {
            CardAnime::class => $cardRepository,
            UserCardAnime::class => $userCardRepository,
        });

        $this->discordNotifier = $this->createMock(DiscordNotifier::class);

        $this->service = new TradeService(
            $this->entityManager,
            $this->createMock(TradeOfferRepository::class),
            $this->createMock(BadgeService::class),
            $this->createMock(WishlistService::class),
            $this->discordNotifier,
        );
    }

    public function testValidOfferIsPersistedAndAnnounced(): void
    {
        $this->entityManager->expects(self::once())->method('persist')->with(self::isInstanceOf(TradeOffer::class));
        $this->entityManager->expects(self::once())->method('flush');
        $this->discordNotifier->expects(self::once())->method('notifyTradeProposed');

        $offer = $this->service->proposeTrade($this->alice, $this->bob, [
            $this->line($this->alice, 1, 1),
            $this->line($this->bob, 2, 2),
        ]);

        self::assertTrue($offer->isPending());
        self::assertSame($this->alice, $offer->getProposer());
        self::assertSame($this->bob, $offer->getRecipient());
        self::assertCount(2, $offer->getItems());
    }

    public function testCannotTradeWithYourself(): void
    {
        $this->expectExceptionMessage('soi-même');

        $this->service->proposeTrade($this->alice, $this->alice, [$this->line($this->alice, 1, 1)]);
    }

    public function testOfferMustNotBeEmpty(): void
    {
        $this->expectExceptionMessage('au moins une carte');

        $this->service->proposeTrade($this->alice, $this->bob, []);
    }

    public function testOfferNeedsCardsFromBothPlayers(): void
    {
        $this->expectExceptionMessage('de chaque joueur');

        $this->service->proposeTrade($this->alice, $this->bob, [$this->line($this->alice, 1, 1)]);
    }

    /**
     * Deux lignes "1 Naruto" passeraient chacune la vérification : elles doivent être
     * additionnées avant de contrôler le stock du joueur.
     */
    public function testDuplicateLinesAreAddedUpBeforeCheckingOwnership(): void
    {
        $this->expectExceptionMessage('Alice ne possède pas 2x "Naruto"');

        $this->service->proposeTrade($this->alice, $this->bob, [
            $this->line($this->alice, 1, 1),
            $this->line($this->alice, 1, 1),
            $this->line($this->bob, 2, 1),
        ]);
    }

    public function testCannotOfferMoreThanOwned(): void
    {
        $this->expectExceptionMessage('Bob ne possède pas 4x "Sasuke"');

        $this->service->proposeTrade($this->alice, $this->bob, [
            $this->line($this->alice, 1, 1),
            $this->line($this->bob, 2, 4),
        ]);
    }

    public function testCardOwnerMustBeAParticipant(): void
    {
        $this->expectExceptionMessage('un des deux joueurs');

        $this->service->proposeTrade($this->alice, $this->bob, [
            $this->line((new User())->setPseudo('Charlie'), 1, 1),
        ]);
    }

    public function testQuantityMustBePositive(): void
    {
        $this->expectExceptionMessage('supérieure à 0');

        $this->service->proposeTrade($this->alice, $this->bob, [$this->line($this->alice, 1, 0)]);
    }

    public function testUnknownCardTypeIsRejected(): void
    {
        $this->expectExceptionMessage('Type de carte invalide');

        $this->service->proposeTrade($this->alice, $this->bob, [
            ['owner' => $this->alice, 'type' => 'manga', 'cardId' => 1, 'quantity' => 1],
        ]);
    }

    public function testOnlyRecipientCanAccept(): void
    {
        $this->expectExceptionMessage('Seul le destinataire');

        $this->service->accept($this->pendingOffer(), $this->alice);
    }

    public function testOnlyRecipientCanDecline(): void
    {
        $this->expectExceptionMessage('Seul le destinataire');

        $this->service->decline($this->pendingOffer(), $this->alice);
    }

    public function testOnlyProposerCanCancel(): void
    {
        $this->expectExceptionMessage("Seul l'auteur");

        $this->service->cancel($this->pendingOffer(), $this->bob);
    }

    public function testDeclineAndCancelResolveTheOffer(): void
    {
        $declined = $this->pendingOffer();
        $this->service->decline($declined, $this->bob);
        self::assertSame(TradeOffer::STATUS_DECLINED, $declined->getStatus());
        self::assertNotNull($declined->getResolvedAt());

        $cancelled = $this->pendingOffer();
        $this->service->cancel($cancelled, $this->alice);
        self::assertSame(TradeOffer::STATUS_CANCELLED, $cancelled->getStatus());
    }

    public function testResolvedOfferCannotBeHandledAgain(): void
    {
        $offer = $this->pendingOffer()->setStatus(TradeOffer::STATUS_DECLINED);

        $this->expectExceptionMessage('déjà été traitée');

        $this->service->accept($offer, $this->bob);
    }

    public function testNewOfferExpiresAfterOneWeek(): void
    {
        $offer = $this->pendingOffer();

        self::assertEquals($offer->getCreatedAt()->modify('+7 days'), $offer->getExpiresAt());
        self::assertFalse($offer->isOverdue());
    }

    public function testOverdueOfferCannotBeAcceptedAndBecomesExpired(): void
    {
        $offer = $this->pendingOffer()->setExpiresAt(new \DateTimeImmutable('-1 minute'));

        try {
            $this->service->accept($offer, $this->bob);
            self::fail('Une offre expirée ne doit pas pouvoir être acceptée.');
        } catch (\InvalidArgumentException $e) {
            self::assertSame('Cette offre a expiré.', $e->getMessage());
        }

        self::assertSame(TradeOffer::STATUS_EXPIRED, $offer->getStatus());
        self::assertEquals($offer->getExpiresAt(), $offer->getResolvedAt());
    }

    private function card(int $id, string $nom): CardAnime
    {
        $card = (new CardAnime())->setNom($nom);
        (new \ReflectionProperty(CardAnime::class, 'id'))->setValue($card, $id);

        return $card;
    }

    private function own(User $user, CardAnime $card, int $quantity): void
    {
        $this->inventory[] = (new UserCardAnime())->setUser($user)->setCardAnime($card)->setQuantity($quantity);
    }

    /**
     * @return array{owner: User, type: string, cardId: int, quantity: int}
     */
    private function line(User $owner, int $cardId, int $quantity): array
    {
        return ['owner' => $owner, 'type' => 'anime', 'cardId' => $cardId, 'quantity' => $quantity];
    }

    /** Offre en attente d'Alice (proposer) vers Bob (destinataire). */
    private function pendingOffer(): TradeOffer
    {
        $offer = (new TradeOffer())->setProposer($this->alice)->setRecipient($this->bob);
        $offer->addItem((new TradeOfferItem())->setOwner($this->alice)->setCardAnime($this->cards[1])->setQuantity(1));

        return $offer;
    }
}
