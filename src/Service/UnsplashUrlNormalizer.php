<?php

declare(strict_types=1);

namespace App\Service;

final class UnsplashUrlNormalizer
{
    /**
     * Try to extract an Unsplash photo id from a given URL and return a stable reconstructed URL.
     * Returns null when the URL is not an Unsplash photo URL.
     *
     * @return array{source:string,id:string,original:string,reconstructed:string}|null
     */
    public function normalize(string $url): ?array
    {
        $url = trim($url);
        if ($url === '') {
            return null;
        }

        $parts = parse_url($url);
        if (false === $parts || !isset($parts['host'])) {
            return null;
        }

        $host = $parts['host'];
        if (!str_ends_with($host, 'unsplash.com')) {
            return null;
        }

        // match patterns like /photo-<id> or /photo-<id>-<slug>
        $path = $parts['path'] ?? '';
        if (!preg_match('#/photo-([^/\?]+)#i', $path, $m)) {
            return null;
        }

        $id = $m[1];

        // reconstruct a stable URL: prefer auto=format and reasonable defaults
        $reconstructed = sprintf('https://images.unsplash.com/photo-%s?auto=format&fit=crop&w=1200&q=80', $id);

        return [
            'source' => 'unsplash',
            'id' => $id,
            'original' => $url,
            'reconstructed' => $reconstructed,
        ];
    }
}
