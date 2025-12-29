# Confirmation email — guide d’usage

## Principe
- À l’inscription, un token est généré et envoyé par email.
- Le lien pointe vers : `GET /verification/email/{token}`.
- Le token expire après 24h.

## Champs en base
- `user.is_email_verified` (bool)
- `user.email_verification_token` (string, nullable)
- `user.email_verification_expires_at` (datetime, nullable)

## Cycle de vie
1) Inscription → token généré + email envoyé.
2) Clic sur le lien → validation, token effacé, email marqué “verifié”.
3) Lien expiré/invalide → page d’erreur dédiée.

## Relance du token (optionnel)
Non implémenté pour l’instant. Pour ajouter :
- Un endpoint `POST /verification/email/resend`
- Générer un nouveau token + nouvel email
- Respecter un “cooldown” (ex. 5 minutes)

## Expiration
Par défaut : 24h.  
Défini dans `src/Service/EmailVerificationService.php` via `TOKEN_TTL`.

## Pages/Emails concernés
- Email HTML : `templates/emails/verify_email.html.twig`
- Email texte : `templates/emails/verify_email.text.twig`
- Page de confirmation : `templates/security/verify_email.html.twig`
