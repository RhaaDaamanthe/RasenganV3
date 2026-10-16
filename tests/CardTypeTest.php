<?php

namespace App\Tests;

use App\Entity\User;
use App\Enum\CardType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CardTypeTest extends TestCase
{
    public static function types(): iterable
    {
        foreach (CardType::cases() as $type) {
            yield $type->name => [$type];
        }
    }

    /**
     * Les noms de propriétés servent à construire du DQL : ils doivent exister sur les entités.
     */
    #[DataProvider('types')]
    public function testPropertiesExistOnEntities(CardType $type): void
    {
        self::assertTrue(property_exists($type->userCardClass(), $type->cardProperty()));
        self::assertTrue(property_exists($type->cardClass(), $type->titleProperty()));
        self::assertTrue(property_exists($type->requirementClass(), 'mythicCard'));
    }

    #[DataProvider('types')]
    public function testFromCardAndNewUserCardRoundTrip(CardType $type): void
    {
        $cardClass = $type->cardClass();
        $card = new $cardClass();

        self::assertSame($type, CardType::fromCard($card));

        $userCard = $type->newUserCard($card);
        self::assertInstanceOf($type->userCardClass(), $userCard);
        self::assertSame($card, $type->cardOf($userCard));
        self::assertNull($type->titleOf($card));
    }

    #[DataProvider('types')]
    public function testUserCollectionsMatchTheFamily(CardType $type): void
    {
        $user = new User();

        self::assertSame($type->wishlistOf($user), $type->wishlistOf($user));
        self::assertCount(0, $type->userCardsOf($user));
    }

    public function testCollectionsAreDistinctPerFamily(): void
    {
        $user = new User();

        self::assertNotSame(CardType::Anime->wishlistOf($user), CardType::Film->wishlistOf($user));
        self::assertNotSame(CardType::Film->userCardsOf($user), CardType::Jeu->userCardsOf($user));
    }

    public function testDiscordLabels(): void
    {
        self::assertSame('Anime', CardType::Anime->discordLabel());
        self::assertSame('Film', CardType::Film->discordLabel());
        self::assertSame('Jeu vidéo', CardType::Jeu->discordLabel());
    }
}
