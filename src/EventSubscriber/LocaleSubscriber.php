<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Applies the current locale from _locale query or session and persists it.
 */
class LocaleSubscriber implements EventSubscriberInterface
{
    private string $defaultLocale;

    public function __construct(string $defaultLocale = 'de')
    {
        $this->defaultLocale = $defaultLocale;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => [['onKernelRequest', 20]],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        if (!$request->hasSession()) {
            return;
        }

        $session = $request->getSession();

        // Allow switching via _locale query parameter and persist it
        $queryLocale = $request->query->get('_locale');
        if (is_string($queryLocale) && in_array($queryLocale, ['de','en'], true)) {
            $session->set('_locale', $queryLocale);
            $request->setLocale($queryLocale);
            return;
        }

        // Otherwise use session locale if available
        $locale = $session->get('_locale');
        if (is_string($locale) && $locale !== '') {
            $request->setLocale($locale);
        } else {
            $request->setLocale($this->defaultLocale);
        }
    }
}
