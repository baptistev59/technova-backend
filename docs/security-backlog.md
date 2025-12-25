# Backlog sécurité & authentification

Ces sujets ont été identifiés mais seront traités dans un sprint ultérieur.

1. **Rate limiting sur `/api/login` et `/api/register`**  
   - Configurer `symfony/rate-limiter` pour limiter les tentatives (IP ou email).
   - Retourner un message explicite lorsqu’un seuil est atteint.

2. **reCAPTCHA / challenge anti-bot**  
   - Intégrer reCAPTCHA v3 ou un équivalent léger côté formulaires publics (Twig + API).
   - Bloquer la création de compte si le score est insuffisant.

3. **/api/test conditionnel**  
   - Firewall ou feature flag pour restreindre le healthcheck en production (IP allo-liste ou authentification basique).

4. **Rotation / invalidation des JWT**  
   - Conserver un journal des tokens émis et les invalider lors d’un logout manuel ou d’un changement critique (mot de passe).

5. **Purge automatique des audits/logs**  
   - Ajouter une commande/cron pour supprimer les entrées au-delà d’une durée définie.

Terminé
-------

- **Double authentification**  
  - Clients : OTP email + trusted device.
  - Vendeurs/admin : TOTP obligatoire.
- **Journalisation & audit exploitables**  
  - Back-office : consultation audit + accès/téléchargement/purge des logs applicatifs.

Revenir sur ce fichier avant de démarrer le sprint dédié afin d’évaluer la complexité et l’ordre de priorité.
