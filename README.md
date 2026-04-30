# USM Volley — Site web et back-office

Site public + interface d'administration pour l'**Union Sportive Miosienne Volley-Ball**.

## 🎯 Caractéristiques

- ⚽ Gestion des équipes et des joueurs
- 📅 Agenda des matchs et entraînements
- 📝 Blog d'actualités avec tags et catégories
- 📸 Galerie de photos avec upload Dropzone
- 📧 Formulaire de contact intégré
- 🔐 Interface d'administration sécurisée
- 📱 Design responsive et accessible
- 🎨 Design system en néo-brutalisme
- 🌐 Import automatique d'articles via API CanalBlog

## 🛠️ Stack technique

- **Backend** : PHP 8.2
- **Templating** : Twig
- **Base de données** : MySQL 8
- **Frontend** : TailwindCSS (CDN) + Alpine.js
- **Conteneurisation** : Docker Compose
- **Emails** : Intégration Brevo API

## 📋 Prérequis

- Docker et Docker Compose
- 4 GB RAM minimum
- Accès à Internet (pour les CDN et API externes)

## 🚀 Installation et lancement

### 1. Cloner le projet

```bash
git clone https://github.com/salamichel/usm-core.git
cd usm-core
```

### 2. Configurer les variables d'environnement

```bash
cp .env.example .env
```

Éditer `.env` et ajuster les variables si nécessaire (voir [CLAUDE.md](CLAUDE.md) pour les détails).

### 3. Lancer le projet

```bash
docker compose up -d --build --force-recreate --no-cache
```

### 4. Accéder au site

| Service | URL |
|---------|-----|
| Site web | http://localhost:8080 |
| Admin | http://localhost:8080/admin |
| phpMyAdmin | http://localhost:8081 |

## 🔐 Accès Admin

Les identifiants par défaut sont définis via les variables d'environnement `ADMIN_EMAIL` et `ADMIN_PASSWORD_HASH`.

Pour générer un hash de mot de passe (PHP) :
```php
password_hash('votre_password', PASSWORD_BCRYPT)
```

## 📚 Documentation

- **[CLAUDE.md](CLAUDE.md)** — Instructions complètes de développement, architecture du projet, patterns à respecter
- **[docs/README.md](docs/README.md)** — Index de la documentation technique
- **[docs/ADMIN_API_IMPROVEMENTS.md](docs/ADMIN_API_IMPROVEMENTS.md)** — API CanalBlog et gestion des articles importés
- **[docs/API_ARTICLES.md](docs/API_ARTICLES.md)** — Documentation de l'API de création d'articles
- **[docs/TAGS_SYSTEM.md](docs/TAGS_SYSTEM.md)** — Système complet de tags et filtrage

## 📁 Structure du projet

```
/
├── public/                    ← Point d'entrée + assets
│   ├── index.php
│   └── assets/uploads/
├── src/
│   ├── Core/                  ← Noyau (routing, BDD, auth)
│   ├── Controllers/           ← Logique des pages
│   ├── Models/                ← Accès aux données
│   ├── Services/              ← Logique métier
│   └── Helpers/               ← Utilitaires
├── config/                    ← Configuration
├── database/                  ← Migrations et seeds
├── templates/                 ← Templates Twig
├── logs/                      ← Fichiers de log
├── docs/                      ← Documentation technique
├── Dockerfile
├── docker-compose.yml
└── CLAUDE.md                  ← Instructions dev
```

## 🗄️ Bases de données

### Base locale (usm_volley)
Contient tous les contenus gérés par le site : articles, pages, équipes, photos, saisons, etc.

### Base externe (USM)
En production : base InfinityFree du club
En dev : simulée par le service `db_external`

Les deux bases sont synchronisées automatiquement au démarrage via les migrations.

## 🔄 Workflow principal

### Pages statiques
1. Admin crée/édite une page → `/admin/pages`
2. Publication via checkbox
3. Page accessible au public sous un slug personnalisé

### Blog
1. Admin crée un article → `/admin/posts`
2. Gestion des tags
3. Upload de photos (cover + galerie)
4. Publication

### Équipes
1. Admin configure les équipes → `/admin/equipes-config`
2. Crée une saison → `/admin/saisons`
3. Importe les joueurs via "Flash" (lit base externe)
4. Ajustements manuels post-import
5. Public accède à `/equipes` avec liste et détails

### Agenda
1. Saison activée → données syncronisées depuis base externe
2. Affichage tableau croisé joueurs × événements
3. Suivi de participation en temps réel

### Formulaire de contact
1. Visiteur remplit le formulaire `/contact`
2. Admin notifié par email (Brevo)
3. Admin répond via `/admin/contacts/{id}`
4. Email de réponse envoyé au visiteur

## 🔧 Commandes utiles

```bash
# Démarrer les services
docker compose up -d

# Voir les logs
docker compose logs -f app

# Accéder au shell PHP
docker compose exec app bash

# Redémarrer la BDD
docker compose down && docker compose up -d --build

# Nettoyer les conteneurs
docker compose down -v
```

## 📧 Configuration Brevo (emails)

Pour que les emails fonctionnent, configurer dans `.env` :
```env
BREVO_API_KEY=your_api_key
BREVO_FROM_EMAIL=noreply@usm-volley.fr
BREVO_FROM_NAME=USM Volley
```

## 🔒 Sécurité

- ✅ Tokens CSRF sur tous les formulaires POST
- ✅ Hachage bcrypt des mots de passe admin
- ✅ Validation centralisée des inputs
- ✅ Pas de fichiers `.git` dans l'image Docker de production
- ✅ Protection des fichiers sensibles dans `.gitignore`

## 🚀 Déploiement

### InfinityFree (production)

Le projet est conçu pour tourner sur **InfinityFree** (shared hosting sans SSH, pas de Composer en prod).

Toutes les dépendances (`vendor/`) sont versionnées dans le repository.

**Processus de déploiement** :
1. Pousser les changements vers le remote
2. FTP sur le serveur InfinityFree
3. Configurer les variables d'environnement

## 📝 Contribution

Les instructions de développement sont dans [CLAUDE.md](CLAUDE.md).

Points importants :
- Pas d'ORM, utilisation directe de PDO
- Migrations SQL idempotentes
- Patterns statiques pour les Models
- Validation centralisée avec `Validator`
- Logging multi-canal avec `Logger`

## 📞 Support

Pour toute question ou problème :
- Vérifier la documentation dans [CLAUDE.md](CLAUDE.md)
- Consulter les logs : `docker compose logs -f app`
- Vérifier la base de données via phpMyAdmin : http://localhost:8081

## 📄 Licence

Ce projet est propriétaire de l'USM Volley.

---

**Maintenant sur GitHub** : https://github.com/salamichel/usm-core
