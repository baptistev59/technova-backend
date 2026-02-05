<?php

declare(strict_types=1);

namespace App\Twig;

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
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('get_country_from_ip', [$this, 'getCountryFromIp']),
            new TwigFunction('get_country_name', [$this, 'getCountryName']),
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
        $countries = [
            'FR' => 'France',
            'DE' => 'Allemagne',
            'IT' => 'Italie',
            'ES' => 'Espagne',
            'GB' => 'Royaume-Uni',
            'BE' => 'Belgique',
            'NL' => 'Pays-Bas',
            'AT' => 'Autriche',
            'CH' => 'Suisse',
            'SE' => 'Suède',
            'NO' => 'Norvège',
            'DK' => 'Danemark',
            'FI' => 'Finlande',
            'PL' => 'Pologne',
            'CZ' => 'République Tchèque',
            'SK' => 'Slovaquie',
            'HU' => 'Hongrie',
            'RO' => 'Roumanie',
            'BG' => 'Bulgarie',
            'HR' => 'Croatie',
            'SI' => 'Slovénie',
            'GR' => 'Grèce',
            'PT' => 'Portugal',
            'IE' => 'Irlande',
            'US' => 'États-Unis',
            'CA' => 'Canada',
            'AU' => 'Australie',
            'JP' => 'Japon',
            'CN' => 'Chine',
            'IN' => 'Inde',
            'BR' => 'Brésil',
            'MX' => 'Mexique',
        ];

        return $countries[strtoupper($countryCode)] ?? $countryCode;
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
