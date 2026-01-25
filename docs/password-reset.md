# TechNova – Réinitialisation de mot de passe

## Vue d'ensemble

Le système de réinitialisation de mot de passe permet aux utilisateurs de réinitialiser leur mot de passe s'ils l'ont oublié. Il utilise le bundle SymfonyCasts `reset-password` pour gérer les tokens de réinitialisation sécurisés.

## Architecture

### Contrôleur
- **Fichier** : `src/Controller/Web/ResetPasswordController.php`
- **Trait** : `ResetPasswordControllerTrait` (SymfonyCasts Bundle)
- **Routes** :
  - `GET /connexion/mot-de-passe-oublie` - Affiche le formulaire de demande de réinitialisation
  - `POST /connexion/mot-de-passe-oublie` - Traite la demande et envoie l'email
  - `GET /connexion/mot-de-passe-oublie/check-email` - Page de confirmation
  - `GET|POST /connexion/mot-de-passe-oublie/reset/{token}` - Formulaire de réinitialisation avec validation du token

### Formulaires
- **RequestPasswordResetFormType** : Formulaire simple demandant l'email utilisateur
- **ChangePasswordFormType** : Formulaire avec validation du nouveau mot de passe et confirmation

### Templates
- `templates/security/reset_password_request.html.twig` - Formulaire de demande
- `templates/security/reset_password_check_email.html.twig` - Page de confirmation
- `templates/security/reset_password_reset.html.twig` - Formulaire de réinitialisation

## Flux de réinitialisation

```
1. Utilisateur accède à /connexion/mot-de-passe-oublie
2. Soumet le formulaire avec son email
3. ResetPasswordController génère un token sécurisé
4. Email de réinitialisation envoyé avec lien contenant le token
5. Utilisateur clique sur le lien dans l'email
6. Validé du token et affichage du formulaire de nouveau mot de passe
7. Utilisateur soumet le nouveau mot de passe
8. Mot de passe hashé et stocké en base
9. Redirection vers page de connexion
```

## Envoi d'email

### Configuration
- **Logger** : Canal Monolog dédié `monolog.logger.email`
- **Log file** : `var/log/email.log`
- **Template HTML** : `templates/emails/reset_password.html.twig`
- **Template texte** : `templates/emails/reset_password.text.twig`

### Variables disponibles dans les templates
- `resetToken` : Objet token avec propriété `token`
- `user` : Entité User avec propriétés `firstname`, `lastname`, `email`

### Exemple de logs
```json
[2026-01-25T12:28:19.837274+00:00] email.INFO: Reset password email sent {"user_id":30,"email":"user@example.com"} []
```

## Gestion des erreurs

Le contrôleur capture les exceptions lors de :
1. **Génération du token** - Si le token existe déjà ou si une erreur système survient
2. **Construction de l'email** - Si les templates sont invalides ou inaccessibles
3. **Envoi d'email** - Si le serveur SMTP est indisponible

Les erreurs sont loggées dans `var/log/email.log` avec le message d'erreur complet pour diagnostiquer les problèmes.

### Exemple d'erreur loggée
```json
[2026-01-25T12:28:19.837274+00:00] email.ERROR: Failed to send reset password email {"user_id":30,"email":"user@example.com","error":"SMTP connection failed"} []
```

## Configuration SMTP

### Environnement de développement
Configurez dans `.env.local` :
```env
MAILER_DSN=smtp://username:password@host:587?encryption=tls&auth_mode=login
MAILER_FROM="TechNova <noreply@technova.local>"
```

### Environnement de production (Alwaysdata)
Défini dans les variables d'environnement Alwaysdata :
```
MAILER_DSN=smtp://technova@alwaysdata.net:password@smtp-technova.alwaysdata.net:587?encryption=tls&auth_mode=login
MAILER_FROM="TechNova <technova@alwaysdata.net>"
```

## Sécurité

- Les tokens sont générés avec `random_bytes()` et hashés en base de données
- Les tokens expirent après 5 minutes (configurable dans `config/packages/reset_password.yaml`)
- Un utilisateur ne peut avoir qu'un seul token actif à la fois
- Les mots de passe sont hashés avec `UserPasswordHasher` avant stockage

## Dépendances

```json
{
  "symfonycasts/reset-password-bundle": "^1.21"
}
```

## Troubleshooting

### Email non reçu
1. Vérifier la configuration SMTP dans `.env.local`
2. Vérifier les logs : `tail -f var/log/email.log`
3. Vérifier que l'utilisateur existe en base : `php bin/console doctrine:query:sql "SELECT email FROM user WHERE id = ?"`

### Token invalide/expiré
Les tokens expirent après 5 minutes. L'utilisateur doit recommencer la demande de réinitialisation.

### Template texte invalide
Si la template `reset_password.text.twig` a des erreurs de rendu Twig, l'email ne sera pas envoyé. Vérifier les variables disponibles dans le contexte.

## Tests

### Manuel (développement)
1. Accéder à `http://localhost:8000/connexion/mot-de-passe-oublie`
2. Entrer un email d'utilisateur existant (ex: `admin@test.fr`)
3. Vérifier la création du lien dans les logs email
4. Cliquer sur le lien ou le copier depuis les logs
5. Entrer le nouveau mot de passe deux fois
6. Vérifier la redirection vers `/connexion`

### Vérification des logs
```bash
# Voir les derniers envois d'email
tail -20 var/log/email.log

# Filtrer les erreurs
grep ERROR var/log/email.log
```

## Notes de mise à jour (25 janvier 2026)

- ✅ Réordonné les paramètres du constructeur (optionnels après requis)
- ✅ Ajouté le template texte pour les emails
- ✅ Implémenté la gestion d'erreurs avec try/catch
- ✅ Ajouté le logging détaillé pour diagnostiquer les problèmes
- ✅ Testé avec utilisateur existant en base de données
