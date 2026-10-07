# USM Volley — Site web et back-office

Site public + interface d'administration pour l'**Unions Salles Mios Volley-Ball**.

## 🎯 Caractéristiques

- ⚽ Gestion des équipes et des joueurs (pages équipes avec effectif, photos, capitaines et suivi complet des rencontres de la saison : matchs à venir, rencontres passées, indicateurs de convocation et liens interactifs vers les feuilles de match)
- 🏆 Référentiel des Équipes Adverses & Saisie des Scores :
  - Référentiel centralisé côté Admin (`/admin/opponent-teams`) avec détection des équipes créées à la volée (`needs_review`), validation et fusion automatique des doublons avec réassignation en cascade.
  - Saisie obligatoire de l'adversaire lors de la création/modification d'un match (sélection ou création libre par le capitaine).
  - Support complet des **Plateaux multi-équipes** : sélection et création de plusieurs adversaires lors du même événement, saisie des scores individuels par adversaire (`scores[opp_id]`), calcul automatique du bilan consolidé (ex : `2V - 1D`) et relance intelligente (désactivée une fois tous les scores saisis).
  - Saisie complète des scores par le capitaine (`/member/captain/matches/{id}/result`) ou l'administrateur : sets obligatoires (0 à 5, interdiction des égalités en sets), points par set optionnels.
  - Affichage public instantané sur l'agenda et les fiches équipes : intitulé enrichi « USM vs {Adversaire} » ou « Plateau vs {Équipes} », liste des rencontres et scores détaillés, badges dynamiques Victoire (vert) / Défaite (rouge) ou Bilan global (bleu/ambre/ardoise).
- 👑 Espace Capitaine dédié : suivi des présences, création/édition de matchs et plateaux multi-équipes avec pré-sélection d'équipe, sélection de l'effectif, consultation des matchs passés et des convocations de la saison avec bascule interactive, bouton de création rapide de match intégré directement dans les états vides, et **bandeau d'alerte rouge proéminent** signalant les matchs et plateaux passés nécessitant la saisie du score.
- 📅 Agenda des matchs et entraînements :
  - Architecture Haute Performance : chargement groupé en mémoire (Batch Fetching) réduisant le temps de génération de 27s à moins d'1s, immunisé contre les timeouts des hébergements mutualisés (Free, InfinityFree)
  - Filtrage dynamique (type, manifestation, équipe, lieu, période) avec panneau réactif en un clic
  - Indicateur de participation personnalisée (calcul des réponses manquantes ciblé selon les équipes/catégories réelles de l'adhérent)
  - Gestion automatique de la file d'attente en surnombre pour les créneaux d'entraînement (`inscrits > terrains × 12`) avec ordre chronologique, comptabilisation fidèle des accompagnants (`Présent(e) +x` / `Présent(e) à x`) et statut `Attente +x`
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

### Agenda & Espace Adhérent
1. Saison activée → données synchronisées depuis base externe.
2. Tableau croisé joueurs × événements avec icône d'édition en direct (`✏️`) sur les participations modifiables.
3. Cartes d'événements haute lisibilité et ergonomie moderne :
   - Carte entièrement cliquable vers le détail de l'événement (`/agenda/{id}`) sans interférer avec les actions interactives.
   - Badge de date proéminent (jour de la semaine, numéro et mois).
   - Pastilles haute visibilité pour l'horaire précis et le lieu de l'événement.
   - Pied de carte dédié intégrant le statut actuel et les boutons d'action de participation repositionnés en bas de carte sur mobile et desktop.
4. Suivi de participation et modifications en lot avec filtres cumulatifs (périodes, types, sous-types, lieux, recherche).
5. Synchronisation en temps réel du KPI « À répondre » : mise à jour instantanée du compteur et de la bordure ambrée dès le vote d'une carte (1-clic, sélecteur ou modification groupée) avec retour API enrichi.
6. Authentification adhérent persistante (session 1 an, jeton cryptographique `localStorage`, reconnexion transparente sans interruption).
7. Redirection contextuelle vers la page d'origine après authentification (`?redirect=...`).
8. Ciblage et éligibilité mutualisés (`EventTargetingService`) garantissant une synchronisation stricte entre les événements visibles et les notifications par email.
9. **Relances automatiques de disponibilité (J-2 et J-1)** :
   - Détection automatique des personnes sans réponse strictement réservée aux types **Match** et **Plateau** à venir, et **uniquement si l'équipe est en sous-effectif** (`joueurs engagés < min_players requis`). Exclusion totale des entraînements, des événements passés et des équipes déjà au complet.
   - Envoi automatisé à J-2 et J-1 via Brevo avec boutons de réponse instantanée (1-clic avec jeton sécurisé HMAC-SHA256).
   - Suivi idempotent et anti-spam via la table locale `event_reminders_sent` (garantit au maximum 1 relance à J-2 et 1 relance à J-1 par adhérent).
   - Déclenchement automatique par le planificateur (`ScheduledJob` action `event_reminder`), par le Lazy Cron (`/api/cron/lazy-trigger`), ou par appel direct externe (`GET /api/cron/event-reminder?token=...`).
10. **Rappels automatiques de saisie des scores aux capitaines** :
    - Détection des matchs officiels passés (< 60 jours) non annulés dont le score n'a pas encore été renseigné.
    - Première relance déclenchée à J+1 (au moins 14 heures après l'heure du match, ex: lendemain 9h pour un match à 19h).
    - Relances espacées de 48 heures (J+3, J+5) plafonnées à un maximum strict de 3 relances par match via la table `score_reminders_sent`.
    - Respect des préférences d'e-mail des capitaines (`pref_score_reminder`).
    - Déclenchement automatique par le planificateur (`ScheduledJob` action `score_reminder`), par le Lazy Cron (`/api/cron/lazy-trigger`), ou par appel direct externe (`GET /api/cron/score-reminder?token=...`).

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
