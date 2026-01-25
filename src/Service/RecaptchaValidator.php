<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Service pour valider les tokens reCAPTCHA v3 avec Google.
 */
class RecaptchaValidator
{
    private const VERIFY_URL = 'https://www.google.com/recaptcha/api/siteverify';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $recaptchaSecret,
    ) {
    }

    /**
     * Valide un token reCAPTCHA v3.
     *
     * @param string|null $token Token reçu du frontend
     * @return bool true si le score > 0.5 (utilisateur humain probable)
     */
    public function isValid(?string $token = null): bool
    {
        if (!$token) {
            return false;
        }

        try {
            $response = $this->httpClient->request('POST', self::VERIFY_URL, [
                'body' => [
                    'secret' => $this->recaptchaSecret,
                    'response' => $token,
                ],
            ]);

            $data = $response->toArray();

            // Vérifier que reCAPTCHA a validé
            if (!($data['success'] ?? false)) {
                return false;
            }

            // Vérifier le score (0.0 - 1.0)
            // 0.0 = probablement un bot
            // 1.0 = probablement humain
            $score = (float) ($data['score'] ?? 0);

            return $score > 0.5;
        } catch (\Exception $e) {
            // En cas d'erreur, on rejette (côté client: afficher erreur réseau)
            return false;
        }
    }

    /**
     * Obtient le score brut reCAPTCHA (pour logging/debug).
     *
     * @return float Score entre 0.0 et 1.0
     */
    public function getScore(?string $token = null): float
    {
        if (!$token) {
            return 0.0;
        }

        try {
            $response = $this->httpClient->request('POST', self::VERIFY_URL, [
                'body' => [
                    'secret' => $this->recaptchaSecret,
                    'response' => $token,
                ],
            ]);

            return (float) ($response->toArray()['score'] ?? 0);
        } catch (\Exception) {
            return 0.0;
        }
    }
}
