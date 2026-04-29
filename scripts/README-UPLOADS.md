# Organisation des uploads

Scripts pour réorganiser et gérer les fichiers uploadés.

## Structure organisée

```
/public/assets/uploads/
├── external/2026/04/external-xxx.jpg       (images de canalblog, etc.)
├── post/2026/04/photo-xxx.jpg              (images des articles)
├── page/2026/04/photo-xxx.jpg              (images des pages)
├── equipe_saison/2026/04/photo-xxx.jpg    (photos d'équipes)
└── home_block/2026/04/photo-xxx.jpg       (images des blocs accueil)
```

## Scripts

### Réorganiser les uploads existants

```bash
# En local
php scripts/organize-uploads.php

# Ou dans Docker
docker compose exec app php scripts/organize-uploads.php
```

**Que fait ce script?**
- Parcourt tous les fichiers existants dans `/public/assets/uploads/`
- Détermine le type d'entité (post, page, equipe_saison, home_block, external)
- Crée les dossiers de destination `{type}/YYYY/MM/`
- Déplace les fichiers vers leur nouveau dossier
- Met à jour la base de données avec les nouveaux chemins
- Affiche un résumé avec nombre de fichiers déplacés et d'erreurs

**Exemple de sortie:**
```
=== Réorganisation des uploads ===

Réorganisation des photos...
  ✓ photo-1234567-abc123.jpg → post/2026/04/photo-1234567-abc123.jpg
  ✓ photo-1234568-def456.jpg → page/2026/04/photo-1234568-def456.jpg
  ...

=== Résumé ===
✓ Fichiers déplacés: 42
✓ Enregistrements mis à jour: 42
✓ Aucune erreur!
```

### Annuler la réorganisation (Rollback)

```bash
php scripts/rollback-uploads.php
```

**Que fait ce script?**
- Ramène tous les fichiers organisés vers `/public/assets/uploads/` (racine)
- Gère les doublons en ajoutant `-1`, `-2`, etc.
- Met à jour la base de données
- Demande une confirmation avant de commencer

## Sécurité

### Avant de lancer les scripts

1. **Sauvegarde la base de données**
   ```bash
   mysqldump -u usm -p usm_volley > backup-$(date +%Y%m%d-%H%M%S).sql
   ```

2. **Sauvegarde le dossier uploads**
   ```bash
   cp -r public/assets/uploads public/assets/uploads.backup-$(date +%Y%m%d-%H%M%S)
   ```

### En cas de problème

```bash
# Restaurer la BD
mysql -u usm -p usm_volley < backup-20260429-150000.sql

# Restaurer les fichiers
rm -rf public/assets/uploads
mv public/assets/uploads.backup-20260429-150000 public/assets/uploads

# Ou lancer le script rollback
php scripts/rollback-uploads.php
```

## Notes

- Les scripts ignorent les fichiers déjà organisés (contenant `/` dans le chemin)
- La date utilisée pour créer les dossiers `YYYY/MM` provient de la date de modification du fichier
- Les chemins sont stockés dans la BD sans le préfixe `/public/assets/uploads/`
- Les fichiers téléchargés à l'avenir seront automatiquement organisés
