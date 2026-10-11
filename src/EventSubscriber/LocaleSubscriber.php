<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * `?_locale=it` switches the language and remembers it in the session, so the
 * Live Component requests that re-render the calendar keep it too.
 */
final class LocaleSubscriber
{
    private const LOCALES = ['en', 'it', 'fr', 'de', 'es'];

    // Before the LocaleListener (priority 16), which reads the request locale.
    #[AsEventListener(event: KernelEvents::REQUEST, priority: 20)]
    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        if (!$request->hasSession()) {
            return;
        }
        $session = $request->getSession();

        $locale = $request->query->get('_locale');
        if (\is_string($locale) && \in_array($locale, self::LOCALES, true)) {
            $session->set('_locale', $locale);
        }

        $request->setLocale($session->get('_locale', $request->getDefaultLocale()));
    }
}
