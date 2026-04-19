# USM Volley - Documentation pour Claude Code

## Vue d'ensemble du Projet

**USM Volley** est une plateforme web et ERP pour la gestion d'un club de volleyball français (Union Salles Mios Volley Ball).

- **Stack**: Next.js 14 + React 18 + TypeScript + Prisma ORM + PostgreSQL
- **Styling**: Tailwind CSS (Design Neo-Brutaliste)
- **Infrastructure**: Docker Compose (App + PostgreSQL)
- **Auth**: NextAuth.js 4
- **Intégrations**: HelloAsso (paiements), Brevo (email)

## Phases de Développement

### Phase 1: Infrastructure ✅ (TERMINÉE)
- ✅ Configuration Next.js, Docker, Prisma
- ✅ Schéma de base de données complet
- ✅ Configuration Tailwind CSS
- ✅ Structure de base du projet

### Phase 2: Authentification & RBAC (À FAIRE)
- [ ] Configuration NextAuth.js
- [ ] Modèles de session & JWT
- [ ] Middleware d'authentification
- [ ] Tests d'authentification

### Phase 3: Processus d'Adhésion (À FAIRE)
- [ ] Route API `/api/adhesion/calculate`
- [ ] Validation règles métiers (âge, genre, catégories)
- [ ] Formulaire dynamique front-end
- [ ] Intégration HelloAsso

### Phase 4: Webhooks & Paiements (À FAIRE)
- [ ] Endpoint `/api/webhooks/helloasso`
- [ ] Vérification signatures
- [ ] Mise à jour statut paiement
- [ ] Emails transactionnels Brevo

### Phase 5: Espace Adhérent (À FAIRE)
- [ ] Pages `/mon-compte/*`
- [ ] Gestion profil
- [ ] Base documentaire sécurisée
- [ ] Galeries photos par équipe

### Phase 6: Back-office Admin (À FAIRE)
- [ ] Pages `/admin/*`
- [ ] DataTable adhérents
- [ ] Import CSV
- [ ] Gestion équipes/saisons
- [ ] Éditeur CKEditor pour blog

## Structure Clés du Projet

### Modèles Prisma

```
User (adhérents, entraîneurs, bureau)
  ├─ adhesions (N-N via Adhesion)
  └─ equipes (N-N)

Saison (ex: 2024-2025)
  ├─ adhesions (1-N)
  └─ equipes (1-N)

Adhesion (User + Saison + tarification)
  ├─ preferences (JSON: indisponibilités, souhaits, coupes)
  └─ statutPaiement (EN_ATTENTE, VALIDÉ, REMBOURSÉ)

Equipe_Groupe (ex: M18F, DEP, CompetLib)
  └─ joueurs (N-N vers User)

Document (fichiers sécurisés)
Post (articles blog)
Event & Photo (galeries)
```

### Routes Clés

**Routes Publiques**:
- `/` - Accueil
- `/le-club`, `/le-bureau`, `/horaires` - Pages statiques
- `/equipes` - Liste équipes
- `/blog` - Articles
- `/adhesion` - Formulaire d'adhésion
- `/login` - Connexion

**Routes Adhérent** (Session requise):
- `/mon-compte` - Dashboard
- `/mon-compte/profil` - Édition profil
- `/mon-compte/documents` - Documents (factures)
- `/mon-compte/equipe` - Annuaire équipe

**Routes Admin** (Rôle BUREAU):
- `/admin` - KPIs
- `/admin/adherents` - Gestion adhérents
- `/admin/equipes` - Gestion équipes
- `/admin/blog` - Éditeur blog
- `/admin/parametres` - Paramètres saison

**Routes API**:
- `/api/auth/*` - NextAuth.js
- `/api/webhooks/helloasso` - Webhooks paiement
- `/api/upload` - Upload d'images
- `/api/documents/private/[id]` - Fichiers sécurisés
- `/api/adhesion/calculate` - Calcul tarif

## Règles Métiers Clés

### Tarification
- Sans Compétition (>15 ans): 60€
- Compétition Volley (M13F, M15F, M18F): 100€
- Compétition Loisir (CompetLib): 100€
- Compétition DEP (Masculine): 150€

### Validations
- Âge: Bloquer <15 ans pour "Sans Compétition"
- Genre: Coupe Heitz (filet 2m24) = Féminin, Coupe Aïco (2m43) = Masculin
- Indisponibilités: Multiple choix (Mardi, Mercredi, Vendredi)
- Compet Loisir: Déploie champs "Coupes" et "Souhait Équipe"

### Permissions Document
- PUBLIC: Visible par tous
- GROUPE_RESTREINT: Équipe spécifique uniquement
- ADHERENTS_ONLY: Tous adhérents (non entraîneur/bureau)
- BUREAU_ONLY: Bureau uniquement

## Commandes Utiles

```bash
# Développement
npm run dev

# Prisma
npm run prisma:migrate          # Créer migration
npm run prisma:generate         # Générer types
npm run prisma:studio           # UI Prisma

# Docker
docker compose up -d            # Démarrer services
docker compose down             # Arrêter services
docker compose logs app         # Logs application

# Build production
npm run build && npm start
```

## Fichiers Importants

- `prisma/schema.prisma` - Schéma de données complet
- `src/lib/prisma.ts` - Instance Prisma client
- `src/lib/utils.ts` - Utilitaires (formatDate, calculateAge, etc.)
- `tailwind.config.ts` - Design system Neo-Brutaliste
- `docker-compose.yml` - Configuration services

## Notes de Sécurité

- ✅ Validation inputs utilisateur
- ✅ Authentification JWT NextAuth
- ✅ RBAC (User.role)
- ✅ Hash mots de passe (bcrypt recommandé)
- ⚠️ À implémenter: Rate limiting, CORS, Audit logs, CSP headers

## Prochaines Étapes

1. Implémenter authentification NextAuth.js
2. Créer endpoint `/api/adhesion/calculate` avec validations
3. Intégrer HelloAsso webhook
4. Construire interface adhésion front-end
5. Développer espace adhérent
6. Mettre en place back-office admin

## Liens Utiles

- Spécifications complètes: [Document fourni]
- Next.js Docs: https://nextjs.org
- Prisma Docs: https://www.prisma.io
- Tailwind CSS: https://tailwindcss.com
- NextAuth: https://authjs.dev

---

**Statut**: Infrastructure ✅ | Authentification ⏳ | Adhésion ⏳ | Admin ⏳
