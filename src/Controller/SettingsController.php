<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class SettingsController extends AbstractController
{
    #[Route('/settings', name: 'app_settings', methods: ['GET'])]
    public function index(Request $request): Response
    {
        return $this->render('settings/index.html.twig', [
            'current_locale' => $request->getLocale(),
        ]);
    }

    #[Route('/settings/locale', name: 'app_settings_locale', methods: ['POST'])]
    public function setLocale(Request $request): Response
    {
        $locale = (string) $request->request->get('locale', 'de');
        $allowed = ['de', 'en'];
        if (!in_array($locale, $allowed, true)) {
            $locale = 'de';
        }
        $request->getSession()->set('_locale', $locale);

        return $this->redirectToRoute('app_settings');
    }
}
