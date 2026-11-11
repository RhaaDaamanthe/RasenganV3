<?php

namespace App\Tests;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Carte de remplacement servie en dev quand une image de public/images/Cartes/ manque.
 */
final class ImagePlaceholderControllerTest extends KernelTestCase
{
    protected static function getKernelClass(): string
    {
        return \App\Kernel::class;
    }

    private function requete(string $url): \Symfony\Component\HttpFoundation\Response
    {
        $kernel = self::bootKernel(['environment' => 'dev', 'debug' => true]);

        return $kernel->handle(Request::create($url), HttpKernelInterface::MAIN_REQUEST, false);
    }

    public function testImageAbsenteDonneUneCarteDeRemplacement(): void
    {
        $reponse = $this->requete('/images/Cartes/%C3%89piques/999_Perso%20Inexistant.webp');

        self::assertSame(200, $reponse->getStatusCode());
        self::assertSame('image/svg+xml', $reponse->headers->get('Content-Type'));
        self::assertStringContainsString('Perso Inexistant', $reponse->getContent());
        self::assertStringContainsString('#a855f7', $reponse->getContent()); // couleur épique
    }

    public function testLeNomEstEchappe(): void
    {
        $reponse = $this->requete('/images/Cartes/Rares/1_%3Cscript%3E.webp');

        self::assertStringNotContainsString('<script>', $reponse->getContent());
    }

    public function testLaRouteNExistePasEnProd(): void
    {
        $kernel = self::bootKernel(['environment' => 'prod', 'debug' => false]);

        self::assertNull($kernel->getContainer()->get('router')->getRouteCollection()->get('app_dev_image_carte'));
    }
}
