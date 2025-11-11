# 🧱 TechNova Backend (Symfony API REST)

![Symfony](https://img.shields.io/badge/Symfony-7.3-blue)
![PHP](https://img.shields.io/badge/PHP-8.3-purple)
![PostgreSQL](https://img.shields.io/badge/PostgreSQL-17-blue)
![JWT](https://img.shields.io/badge/Security-JWT-green)
![License: MIT](https://img.shields.io/badge/License-MIT-yellow)

## 🚀 Présentation
Le **backend TechNova** est une API REST développée avec **Symfony 7.3**, destinée à alimenter le frontend React et l’application mobile Flutter.
Elle gère l’authentification JWT, les paiements via Stripe, les logs système (Monolog) et la traçabilité (AuditLog).

---

## ⚙️ Stack technique
- **Symfony 7.3**
- **PHP 8.3**
- **PostgreSQL 17**
- **Lexik JWT Authentication Bundle**
- **Doctrine ORM + Migrations**
- **Monolog / AuditLog**
- **Stripe API (paiements)**

---

## 🧩 Installation locale

```bash
git clone https://github.com/baptistev59/technova-backend.git
cd technova-backend
composer install
cp .env .env.local
# Configure la base de données PostgreSQL
symfony console doctrine:database:create
symfony console doctrine:migrations:migrate
symfony serve
```

L’API sera disponible sur : **http://localhost:8000**

---

## 🔐 Variables d’environnement (.env.local)
```env
DATABASE_URL="postgresql://user:password@localhost:5432/technova_db?serverVersion=17&charset=utf8"
JWT_PASSPHRASE="votre_passphrase"
STRIPE_SECRET_KEY="sk_test_..."
STRIPE_PUBLIC_KEY="pk_test_..."
```

---

## 🛡️ Sécurité & RGPD
- Authentification via **JWT**
- Journalisation applicative via **Monolog**
- Journalisation métier via **AuditLog**
- Conformité RGPD : seules les données nécessaires sont conservées

---

## 🧠 Commandes utiles
```bash
symfony console cache:clear
symfony console make:entity
symfony console doctrine:migrations:migrate
symfony console doctrine:fixtures:load
```

---

## ☁️ Déploiement
### Render
1. Connecter le dépôt GitHub
2. Choisir l’image **PHP 8.3** et ajouter les variables d’environnement
3. Configurer le port `8000` et PostgreSQL intégré

### AlwaysData
1. Créer une app PHP 8.3
2. Déployer le contenu du projet via SFTP
3. Configurer la base PostgreSQL et les variables d’environnement

---

## 👤 Auteur
**Développé par : Baptiste VANDAELE**

---

## 📜 Licence
Ce projet est sous licence **MIT**. Voir le fichier `LICENSE` pour plus d’informations.
