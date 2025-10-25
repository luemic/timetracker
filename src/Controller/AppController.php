<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class AppController extends AbstractController
{
    #[Route('/app', name: 'app_frontend')]
    #[IsGranted('ROLE_USER')]
    public function index(): Response
    {
        return $this->render('app/index.html.twig');
    }
}
