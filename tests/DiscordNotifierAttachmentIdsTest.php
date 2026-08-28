<?php

namespace App\Tests;

use App\Service\DiscordNotifier;
use PHPUnit\Framework\TestCase;

class DiscordNotifierAttachmentIdsTest extends TestCase
{
    /**
     * Discord ne liste pas dans "attachments" les fichiers déjà utilisés par un embed :
     * leur identifiant doit être récupéré depuis l'URL CDN de l'image, sans doublon.
     */
    public function testAttachmentIdsCompletesAttachmentsWithEmbedImageIds(): void
    {
        $notifier = (new \ReflectionClass(DiscordNotifier::class))->newInstanceWithoutConstructor();
        $method = new \ReflectionMethod(DiscordNotifier::class, 'attachmentIds');

        $ids = $method->invoke($notifier, [
            'attachments' => [['id' => '300']],
            'embeds' => [
                ['image' => ['url' => 'https://cdn.discordapp.com/attachments/100/200/0_card.png']],
                ['image' => ['url' => 'https://media.discordapp.net/attachments/100/300/1_card.png']],
                ['title' => 'Sans image'],
            ],
        ]);

        self::assertSame(['300', '200'], $ids);
    }
}
