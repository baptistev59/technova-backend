# Comptes créés par les fixtures

Les données de démonstration injectées via `php bin/console doctrine:fixtures:load --purge-with-truncate` créent les utilisateurs suivants :

| Rôle | Email | Mot de passe | Description |
|------|-------|--------------|-------------|
| Admin | `admin@test.fr` | `123456` | Accès complet API/Twig |
| Vendeur | `vendor01@technova.test` | `Vendor#01` | Aurora Labs |
| Vendeur | `vendor02@technova.test` | `Vendor#02` | Pulse Ride Collective |
| Vendeur | `vendor03@technova.test` | `Vendor#03` | Nexa Studio |
| Vendeur | `vendor04@technova.test` | `Vendor#04` | Lumina Habitat |
| Vendeur | `vendor05@technova.test` | `Vendor#05` | Orbit Care Robotics |
| Vendeur | `vendor06@technova.test` | `Vendor#06` | Flux Visionary |
| Vendeur | `vendor07@technova.test` | `Vendor#07` | Solara Motion |
| Vendeur | `vendor08@technova.test` | `Vendor#08` | Quantum Ring Labs |
| Vendeur | `vendor09@technova.test` | `Vendor#09` | Helios Drones |
| Vendeur | `vendor10@technova.test` | `Vendor#10` | Axon Dynamics |
| Client | `lena.client@technova.test` | `Client#01` | Profil client complet + adresse par défaut |
| Client | `maxime.client@technova.test` | `Client#02` | Utilisé pour le workflow panier/commande |
| Client | `nora.client@technova.test` | `Client#03` | Sert de compte témoin pour l’historique de commandes |

Notes :
- Les avatars par défaut se trouvent dans `public/images/avatars/` (`avatar-admin.svg`, `avatar-vendor.svg`, `avatar-customer.svg`).  
- Les comptes clients créés via `/inscription` héritent automatiquement de l’avatar client et peuvent l’écraser via `/mon-compte/profil`.
- Les comptes admin/vendeur doivent activer la TOTP au premier login via `/mon-compte/2fa`.  
- Les comptes clients utilisent un OTP email (trusted device possible).

> ⚠️ Le chargement des fixtures est **destructif** (`--purge-with-truncate`). Lance la commande uniquement lorsque tu souhaites repartir d’une base propre.
