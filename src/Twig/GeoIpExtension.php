<?php

declare(strict_types=1);

namespace App\Twig;

use App\Repository\CountryRepository;
use App\Service\GeoIpService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class GeoIpExtension extends AbstractExtension
{
    public function __construct(
        private readonly GeoIpService $geoIpService,
        private readonly RequestStack $requestStack,
        private readonly CountryRepository $countryRepository,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('get_country_from_ip', [$this, 'getCountryFromIp']),
            new TwigFunction('get_country_name', [$this, 'getCountryName']),
            new TwigFunction('get_country_flag', [$this, 'getCountryFlag']),
            new TwigFunction('get_country_label', [$this, 'getCountryLabel']),
        ];
    }

    public function getCountryFromIp(): string
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request) {
            return 'FR';
        }

        $clientIp = $this->getClientIp($request);
        if (!$clientIp) {
            return 'FR';
        }

        return $this->geoIpService->getCountryFromIp($clientIp);
    }

    public function getCountryName(string $countryCode): string
    {
        $code = strtoupper($countryCode);
        $map = $this->countryRepository->getMapByCodes([$code]);

        return $map[$code]['name'] ?? $countryCode;
    }

    public function getCountryFlag(string $countryCode): string
    {
        $code = strtoupper($countryCode);
        $map = $this->countryRepository->getMapByCodes([$code]);

        return $map[$code]['flag'] ?? '🌍';
    }

    public function getCountryLabel(string $countryCode): string
    {
        $code = strtoupper($countryCode);
        $map = $this->countryRepository->getMapByCodes([$code]);
        $flag = $map[$code]['flag'] ?? '🌍';
        $name = $map[$code]['name'] ?? $countryCode;

        return sprintf('%s %s', $flag, $name);
    }

    private function getClientIp(Request $request): ?string
    {
        if ($request->headers->has('CF-Connecting-IP')) {
            return $request->headers->get('CF-Connecting-IP');
        }

        if ($request->headers->has('X-Forwarded-For')) {
            $ips = explode(',', $request->headers->get('X-Forwarded-For', ''));
            $ip = trim($ips[0] ?? '');
            return $ip ?: null;
        }

        return $request->getClientIp();
    }
}
