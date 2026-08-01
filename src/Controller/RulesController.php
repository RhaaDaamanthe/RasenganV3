<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class RulesController extends AbstractController
{
    #[Route('/regles', name: 'app_rules')]
    public function index(): Response
    {
        return $this->render('rules/index.html.twig');
    }
}
