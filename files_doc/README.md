# Plateforme Bancaire en Ligne

Projet académique - Système de gestion bancaire multi-banques avec Symfony 7, Twig et PostgreSQL.

## 📋 Table des matières

- [Installation](#installation)
- [Configuration](#configuration)
- [Utilisation](#utilisation)
- [Comptes Test](#comptes-test)
- [Architecture](#architecture)
- [Commandes Utiles](#commandes-utiles)

## 🚀 Installation

### Prérequis

- Docker et Docker Compose
- PHP 8.2+ (si exécution locale sans Docker)
- Symfony CLI 5.17.1+
- PostgreSQL 14+

### Avec Docker (Recommandé)

```bash
# Cloner le projet
git clone <repo-url>
cd banque-en-ligne

# Lancer les services
docker-compose up -d

# Attendre que PostgreSQL soit prêt
sleep 10

# Exécuter les migrations Doctrine
docker exec banque_symfony php bin/console doctrine:migrations:migrate

# Charger les fixtures (données test)
docker exec banque_symfony php bin/console doctrine:fixtures:load

# Créer les utilisateurs de test
docker exec banque_symfony php bin/console app:create-test-users
```

L'application est alors accessible sur:
- **Frontend:** http://localhost:8000
- **API:** http://localhost:8000/api
- **Nginx:** http://localhost:80

### Installation locale

```bash
# Installer les dépendances PHP
composer install

# Configurer la base de données
# Éditer .env.local et définir DATABASE_URL

# Créer la base de données
symfony console doctrine:database:create

# Exécuter les migrations
symfony console doctrine:migrations:migrate

# Charger les fixtures
symfony console doctrine:fixtures:load

# Lancer le serveur de développement
symfony serve
```

## ⚙️ Configuration

### Variables d'environnement (.env)

```env
APP_ENV=dev
APP_DEBUG=true
APP_SECRET=<random-secret>

DATABASE_URL=postgresql://banque_user:banque_password@localhost:5432/banque_db

MAILER_DSN=smtp://localhost
MAILER_FROM=noreply@banque.local

# Sécurité
ARGON2_TIME_COST=2
ARGON2_MEMORY_COST=19
```

### Configuration Symfony

Les fichiers de configuration principaux:

- `config/packages/doctrine.yaml` - Configuration Doctrine ORM
- `config/packages/security.yaml` - Configuration sécurité + authentification
- `config/packages/twig.yaml` - Configuration Twig

## 📊 Modèle de données

Le système utilise 4 tables principales:

### Banque
```
- id (INT, PK)
- nom, email, mot_de_passe
- téléphone, statut, date_création
```

### Client
```
- id (INT, PK)
- nom, prénom, email, mot_de_passe
- rôle (ROLE_CLIENT ou ROLE_ADMIN)
- banque_id (FK → Banque)
```

### Compte
```
- id (INT, PK)
- numéro_compte, type, solde, devise
- client_id (FK → Client)
```

### Transaction
```
- id (INT, PK)
- montant, type (débit/crédit)
- compte_source_id, compte_destination_id
- statut (succès/échoué)
```

## 👤 Comptes Test

### Administrateur
```
Email:    admin@system.fr
Mot de passe: Admin123!
Rôle:     ROLE_ADMIN
```

### Représentant Banque
```
Email:    banque@test.fr
Mot de passe: Banque123!
Rôle:     ROLE_BANQUE
```

### Client
```
Email:    client@test.fr
Mot de passe: Client123!
Rôle:     ROLE_CLIENT
Banque:   Banque Test
Compte:   FR1234567890123456789012345
Solde:    1000.00 EUR
```

## 🏗️ Architecture

### Stack Technique

| Composant | Technologie | Version |
|-----------|-------------|---------|
| Backend | Symfony | 7 |
| Frontend | Twig + Bootstrap | 5 |
| Base de données | PostgreSQL | 14+ |
| ORM | Doctrine | Latest |
| Authentification | Symfony Security | Latest |

### Structure du Projet

```
banque-en-ligne/
├── src/
│   ├── Controller/        # Contrôleurs Symfony
│   ├── Entity/            # Entités Doctrine (Banque, Client, Compte, Transaction)
│   ├── Repository/        # Repositories (requêtes BD)
│   ├── Service/           # Services métier (VirementService, etc.)
│   └── Security/          # Providers, authenticators
├── templates/             # Templates Twig
├── migrations/            # Migrations Doctrine
├── public/                # Assets (CSS, JS)
├── config/                # Configuration Symfony
├── tests/                 # Tests unitaires et fonctionnels
├── docker-compose.yml     # Configuration Docker
└── .env                   # Variables d'environnement
```

## 💻 Commandes Utiles

### Commandes Symfony

```bash
# Démarrer le serveur local
symfony serve

# Créer une migration
symfony console make:migration

# Exécuter les migrations
symfony console doctrine:migrations:migrate

# Créer une entité
symfony console make:entity

# Créer un contrôleur
symfony console make:controller

# Lancer les tests
symfony console test

# Vider le cache
symfony console cache:clear

# Créer un utilisateur
symfony console make:user
```

### Commandes Docker

```bash
# Lancer les services
docker-compose up -d

# Arrêter les services
docker-compose down

# Voir les logs
docker-compose logs -f symfony

# Accéder au shell PostgreSQL
docker exec -it banque_db psql -U banque_user -d banque_db

# Accéder au shell Symfony
docker exec -it banque_symfony bash
```

## 🔐 Sécurité

### Recommandations

- ✓ Tous les mots de passe sont hachés avec Argon2
- ✓ CSRF protection activée sur tous les formulaires
- ✓ Validation des données côté serveur
- ✓ Contrôle d'accès basé sur les rôles (RBAC)
- ✓ Protection contre l'injection SQL (Doctrine ORM)

### Fonctionnalités de sécurité

1. **Authentification:** Utiliser Symfony Security
2. **Autorisation:** Vérifier les rôles avant chaque action
3. **Chiffrement:** Argon2 pour les mots de passe
4. **Validation:** Utiliser Symfony Validator
5. **Logs:** Enregistrer les actions critiques

## 🧪 Tests

### Exécuter les tests

```bash
# Tous les tests
symfony console test

# Tests d'une classe spécifique
symfony console test tests/Service/VirementServiceTest.php

# Tests avec couverture
symfony console test --code-coverage
```

### Exemples de cas de test

- Virement avec solde insuffisant (doit échouer)
- Virement vers un compte désactivé (doit échouer)
- Création d'un compte (doit réussir)
- Authentification (doit réussir)

## 📚 Règles Métier Implémentées

| Règle | Description |
|-------|-------------|
| R1 | Un virement est refusé si solde insuffisant → ECHOUE |
| R2 | Un compte désactivé ne peut pas émettre/recevoir |
| R3 | Le solde ne peut pas être négatif |
| R4 | Un virement réussi crée 2 transactions (débit + crédit) |

## 🐛 Dépannage

### La base de données ne démarre pas

```bash
docker-compose down -v
docker-compose up -d
```

### Les migrations échouent

```bash
docker exec banque_symfony php bin/console doctrine:database:drop --force
docker exec banque_symfony php bin/console doctrine:database:create
docker exec banque_symfony php bin/console doctrine:migrations:migrate
```

### Port 5432 déjà utilisé

Modifier le port dans `docker-compose.yml`:
```yaml
ports:
  - "5433:5432"  # Utiliser 5433 au lieu de 5432
```

## 📄 Livrables

- ✓ Cahier des charges (CDC)
- ✓ Diagrammes UML (cas d'utilisation, classes)
- ✓ Code source Symfony 7
- ✓ Schéma SQL PostgreSQL
- ✓ Docker Compose
- ✓ Tests unitaires et fonctionnels
- ✓ Documentation (ce fichier)

## 📞 Support

Pour toute question ou bug:
1. Vérifier les logs: `docker-compose logs -f`
2. Consulter la documentation Symfony: https://symfony.com/doc
3. Vérifier le cahier des charges (CDC_Banque_en_Ligne.pdf)

---

**Document:** README.md  
**Version:** 1.0  
**Date:** 10 juin 2026  
**Statut:** Projet académique ✓
