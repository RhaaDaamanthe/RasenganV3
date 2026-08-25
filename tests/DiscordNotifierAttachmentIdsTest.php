<?php

use App\Service\DiscordNotifier;

require dirname(__DIR__) . '/vendor/autoload.php';

$notifier = (new ReflectionClass(DiscordNotifier::class))->newInstanceWithoutConstructor();
$method = new ReflectionMethod(DiscordNotifier::class, 'attachmentIds');

$ids = $method->invoke($notifier, [
    'attachments' => [['id' => '300']],
    'embeds' => [
        ['image' => ['url' => 'https://cdn.discordapp.com/attachments/100/200/0_card.png']],
        ['image' => ['url' => 'https://media.discordapp.net/attachments/100/300/1_card.png']],
        ['title' => 'Sans image'],
    ],
]);

assert($ids === ['300', '200']);
