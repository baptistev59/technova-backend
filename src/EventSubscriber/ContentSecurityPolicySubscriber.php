<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class ContentSecurityPolicySubscriber implements EventSubscriberInterface
{
    /**
     * @var array<string, array<string>>
     */
    private array $requiredSources = [
        'script-src' => [
            'https://js.stripe.com',
            'https://checkout.stripe.com',
            'https://cdn.jsdelivr.net',
            'http://unpkg.com',
            'https://ga.jspm.io',
            "'unsafe-inline'",
            "'unsafe-eval'",
            'data:',
        ],
        'style-src' => [
            "'self'",
            "'unsafe-inline'",
            'https://fonts.googleapis.com',
            'https://cdn.jsdelivr.net',
            'http://unpkg.com',
            'https://ga.jspm.io',
        ],
        'style-src-elem' => [
            "'self'",
            "'unsafe-inline'",
            'https://fonts.googleapis.com',
            'https://cdn.jsdelivr.net',
            'http://unpkg.com',
            'https://ga.jspm.io',
        ],
        'font-src' => [
            "'self'",
            'https://fonts.gstatic.com',
            'data:',
            'https://ga.jspm.io',
        ],
        'img-src' => [
            "'self'",
            'data:',
            'blob:',
            'https://ga.jspm.io',
            'https://images.unsplash.com',
        ],
        'connect-src' => [
            "'self'",
            'https://checkout.stripe.com',
            'https://api.stripe.com',
        ],
        'frame-src' => [
            "'self'",
            'https://js.stripe.com',
            'https://checkout.stripe.com',
        ],
    ];

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => 'onKernelResponse',
        ];
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $response = $event->getResponse();
        $policyHeader = $response->headers->get('Content-Security-Policy');

        if ('' === (string) $policyHeader) {
            $response->headers->set('Content-Security-Policy', $this->buildDefaultPolicy());

            return;
        }

        $directives = $this->parseDirectives($policyHeader);
        foreach ($this->requiredSources as $directive => $sources) {
            $this->ensureDirective($directives, $directive, $sources);
        }

        $response->headers->set('Content-Security-Policy', $this->buildPolicyString($directives));
    }

    /**
     * @return array<string, array<string>>
     */
    private function parseDirectives(string $policyHeader): array
    {
        $directives = [];
        foreach (explode(';', $policyHeader) as $segment) {
            $segment = trim($segment);
            if ('' === $segment) {
                continue;
            }

            $parts = preg_split('/\s+/', $segment);
            if (!$parts || !isset($parts[0])) {
                continue;
            }

            $name = array_shift($parts);
            $directives[$name] = $parts;
        }

        return $directives;
    }

    /**
     * @param array<string, array<string>> $directives
     * @param array<string> $sources
     */
    private function ensureDirective(array &$directives, string $directive, array $sources): void
    {
        if (!isset($directives[$directive])) {
            $directives[$directive] = [];
        }

        foreach ($sources as $source) {
            if (!in_array($source, $directives[$directive], true)) {
                $directives[$directive][] = $source;
            }
        }
    }

    /**
     * @param array<string, array<string>> $directives
     */
    private function buildPolicyString(array $directives): string
    {
        $parts = [];
        foreach ($directives as $name => $values) {
            if ([] === $values) {
                $parts[] = $name;
                continue;
            }

            $parts[] = $name.' '.implode(' ', $values);
        }

        return implode('; ', $parts);
    }

    private function buildDefaultPolicy(): string
    {
        $baseDirectives = [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://js.stripe.com https://checkout.stripe.com https://cdn.jsdelivr.net http://unpkg.com https://ga.jspm.io data:",
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net http://unpkg.com https://ga.jspm.io",
            "style-src-elem 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net http://unpkg.com https://ga.jspm.io",
            "font-src 'self' https://fonts.gstatic.com data: https://ga.jspm.io",
            "img-src 'self' data: blob: https://ga.jspm.io https://images.unsplash.com",
            "connect-src 'self' https://checkout.stripe.com https://api.stripe.com",
            "frame-src 'self' https://js.stripe.com https://checkout.stripe.com",
        ];

        return implode('; ', $baseDirectives);
    }
}
