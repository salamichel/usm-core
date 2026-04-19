# USM Volley - Application Web et ERP

Application web et plateforme de gestion pour l'Union Salles Mios Volley Ball (USMVB).

## Technologies

- **Frontend/Backend**: Next.js 15 + React 19 + TypeScript
- **Base de données**: PostgreSQL 16 + Prisma ORM
- **Styling**: Tailwind CSS (Design Neo-Brutaliste)
- **Infrastructure**: Docker Compose
- **Authentification**: NextAuth.js 5
- **Email**: Brevo (transactionnel)
- **Paiements**: HelloAsso (webhooks)
- **Images**: Sharp (optimisation)
- **Éditeur riche**: CKEditor 5

## Structure du Projet

```
usm-volley/
├── src/
│   ├── app/                 # Routes Next.js (App Router)
│   │   ├── (front)/         # Front-office public
│   │   ├── (membre)/        # Espace adhérent
│   │   ├── admin/           # Back-office
│   │   └── api/             # Routes API
│   ├── components/          # Composants React
│   ├── lib/                 # Utilitaires & clients API
│   └── types/               # Types TypeScript
├── prisma/
│   ├── schema.prisma        # Schéma de données
│   └── migrations/          # Historique des migrations
├── public/
│   ├── images/              # Images statiques
│   └── uploads/             # Fichiers uploadés (volume Docker)
├── docker-compose.yml       # Configuration Docker
├── Dockerfile               # Image Next.js
├── .env.example             # Template variables d'env
├── tailwind.config.ts       # Configuration Tailwind
└── tsconfig.json            # Configuration TypeScript
```

## Installation

### Prérequis

- Node.js 20+
- Docker & Docker Compose

### Démarrage Local (avec Docker)

1. **Cloner le repository**
```bash
git clone <repository-url>
cd usm-volley
```

2. **Configurer les variables d'environnement**
```bash
cp .env.example .env
# Éditer .env avec les clés API réelles si besoin
```

3. **Démarrer Docker Compose**
```bash
docker compose up -d
```

4. **Initialiser la base de données**
```bash
docker compose exec app npx prisma migrate dev
```

5. **Accéder à l'application**
- App: http://localhost:3000
- Prisma Studio: `docker compose exec app npx prisma studio`
- PostgreSQL: localhost:5432

### Démarrage Local (sans Docker)

1. **Installer les dépendances**
```bash
npm install
```

2. **Configurer PostgreSQL**
- Créer une base de données `usm_volley`
- Mettre à jour `DATABASE_URL` dans `.env`

3. **Migrer la base de données**
```bash
npx prisma migrate dev
```

4. **Démarrer le serveur de développement**
```bash
npm run dev
```

5. **Accéder à l'application**
- App: http://localhost:3000

## Commandes Utiles

```bash
# Développement
npm run dev

# Build production
npm run build

# Démarrer production
npm start

# Prisma commands
npm run prisma:generate    # Générer Prisma Client
npm run prisma:migrate     # Créer migration
npm run prisma:studio      # Ouvrir Prisma Studio

# Linting
npm run lint
```

## Modèle de Données

### Modèles Principaux

- **User**: Adhérent, Entraîneur, Bureau avec rôles (RBAC)
- **Saison**: Périodes d'adhésion (2024-2025, etc.)
- **Adhesion**: Lien User-Saison avec catégorie et montant
- **Equipe_Groupe**: Équipes par saison (M18F, DEP, CompetLib, etc.)
- **Document**: Fichiers sécurisés avec permissions
- **Post**: Articles de blog
- **Event** & **Photo**: Galeries événements

### Énumérations

- **CategorieAdhesion**: SANS_COMPETITION, COMPETITION_VOLLEY, COMPETLIB, COMPETITION_DEP
- **StatutPaiement**: EN_ATTENTE, VALIDÉ, REMBOURSÉ
- **Role**: ADHERENT, ENTRAINEUR, BUREAU
- **PermissionDocument**: PUBLIC, GROUPE_RESTREINT, ADHERENTS_ONLY, BUREAU_ONLY

## Variables d'Environnement

```env
# Database
DATABASE_URL=postgresql://user:password@host:5432/usm_volley

# NextAuth
NEXTAUTH_SECRET=<generate with: openssl rand -hex 32>
NEXTAUTH_URL=http://localhost:3000

# HelloAsso
HELLOASSO_API_KEY=<API key>
HELLOASSO_WEBHOOK_SECRET=<webhook secret>

# Brevo
BREVO_API_KEY=<API key>

# Application
NODE_ENV=development
```

## Workflow de Développement

1. Créer une branche feature: `git checkout -b feature/nom-feature`
2. Apporter les modifications
3. Committer avec messages explicites: `git commit -m "feat: description"`
4. Pousser: `git push origin feature/nom-feature`
5. Créer une Pull Request

## Déploiement

Le projet est configuré pour être déployé via Docker Compose sur un serveur dédié.

**Configuration production**:
- Variables d'env sécurisées (secrets)
- Database backups quotidiens
- Volume persistant `/uploads`
- Reverse proxy/Load balancer recommandé

## Sécurité

- ✅ Validation des inputs utilisateur
- ✅ Authentification JWT via NextAuth
- ✅ RBAC (Rôles & Permissions)
- ✅ Hash des mots de passe (bcrypt)
- ✅ Validation des fichiers uploadés (Sharp)
- ✅ CSRF protection (NextAuth)
- ⚠️ En cours: Rate limiting, Audit logs

## Support

Pour les questions ou les problèmes, consulter:
- Documentation: `/docs`
- Issues: [GitHub Issues]
- Contact: [Bureau USM]

## License

Propriétaire - USM Volley Ball
