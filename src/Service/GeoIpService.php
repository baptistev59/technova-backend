<?php

declare(strict_types=1);

namespace App\Service;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class GeoIpService
{
    private const CACHE_TTL = 86400 * 7; // 7 days
    private const API_TIMEOUT = 2;
    private const DEFAULT_COUNTRY = 'FR';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly CacheItemPoolInterface $cache,
    ) {
    }

    /**
     * Get country code from IP address.
     * Uses caching and falls back to default country on API failure.
     */
    public function getCountryFromIp(string $ip): string
    {
        // Validate IP
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return self::DEFAULT_COUNTRY;
        }

        // Local/private IPs
        if ($this->isPrivateIp($ip)) {
            return self::DEFAULT_COUNTRY;
        }

        // Check cache
        $cacheKey = 'geoip_' . md5($ip);
        try {
            $cacheItem = $this->cache->getItem($cacheKey);
            if ($cacheItem->isHit()) {
                return $cacheItem->get();
            }
        } catch (\Exception) {
            // Cache unavailable, continue
        }

        // Fetch from API
        $country = $this->fetchCountryFromApi($ip);

        // Save to cache
        try {
            $cacheItem = $this->cache->getItem($cacheKey);
            $cacheItem->set($country)->expiresAfter(self::CACHE_TTL);
            $this->cache->save($cacheItem);
        } catch (\Exception) {
            // Cache save failed, continue
        }

        return $country;
    }

    private function fetchCountryFromApi(string $ip): string
    {
        try {
            $response = $this->httpClient->request('GET', "https://ipapi.co/{$ip}/json/", [
                'timeout' => self::API_TIMEOUT,
            ]);

            $data = $response->toArray();
            $country = strtoupper($data['country_code'] ?? '');

            return match (strlen($country)) {
                2 => $country,
                default => self::DEFAULT_COUNTRY,
            };
        } catch (\Exception) {
            return self::DEFAULT_COUNTRY;
        }
    }

    private function isPrivateIp(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
    }
}
