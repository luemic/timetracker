<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Converts exceptions to JSON errors for API/Fetch requests so the frontend sees meaningful messages
 * instead of the generic HTML error page / "HTTP 500".
 */
class ApiExceptionSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly TranslatorInterface $translator) {}

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => ['onKernelException', 0],
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $request = $event->getRequest();
        if (!$this->isApiOrJsonRequest($request)) {
            return; // do not interfere with normal HTML responses
        }

        $throwable = $event->getThrowable();

        // Default status code and message
        $status = 500;
        $message = $this->translator->trans('errors.unexpected');

        // Map common exception types to appropriate status codes with meaningful messages
        if ($throwable instanceof HttpExceptionInterface) {
            $status = $throwable->getStatusCode();
            $message = $throwable->getMessage() ?: $message;
        } elseif ($throwable instanceof \InvalidArgumentException) {
            $status = 400;
            $message = $throwable->getMessage() ?: $this->translator->trans('errors.invalid_input');
        } elseif ($throwable instanceof \TypeError || $throwable instanceof \ValueError) {
            // PHP engine type/value errors during request processing (e.g., invalid date inputs)
            $status = 400;
            $message = $this->translator->trans('errors.invalid_input_generic');
        } elseif ($throwable instanceof \RuntimeException) {
            // Often used for not found / invalid references in services
            $status = 404;
            $message = $throwable->getMessage() ?: $this->translator->trans('errors.not_found');
        }

        // In debug env, enrich the payload a bit (without full traces) to help development
        $payload = ['error' => $message];
        if ($this->isDebug($request)) {
            $payload['exception'] = \get_class($throwable);
            $throwableMessage = trim($throwable->getMessage() ?? '');
            if ($throwableMessage !== '') {
                $payload['details'] = $throwableMessage;
            }
        }

        $response = new JsonResponse($payload, $status, [
            'Content-Type' => 'application/json',
        ]);
        $event->setResponse($response);
    }

    private function isApiOrJsonRequest(Request $request): bool
    {
        $path = $request->getPathInfo() ?? '';
        if (str_starts_with($path, '/api') || str_starts_with($path, '/legacy-api')) {
            return true;
        }
        $accept = $request->headers->get('Accept', '');
        if (stripos($accept, 'application/json') !== false) {
            return true;
        }
        $xrw = $request->headers->get('X-Requested-With');
        if ($xrw && strcasecmp($xrw, 'fetch') === 0) {
            return true;
        }
        return false;
    }

    private function isDebug(Request $request): bool
    {
        // Symfony exposes APP_DEBUG in server params for requests; fall back to env
        $debug = $request->server->get('APP_DEBUG');
        if ($debug !== null) {
            return filter_var($debug, FILTER_VALIDATE_BOOLEAN);
        }
        return filter_var((string)($_ENV['APP_DEBUG'] ?? $_SERVER['APP_DEBUG'] ?? '0'), FILTER_VALIDATE_BOOLEAN);
    }
}
