---
name: db-migrations
description: Aide à la création et à la gestion des migrations SQL idempotentes
---

# Compétence : Migrations de Base de Données (db-migrations)

Cette compétence guide et automatise la création de scripts de migration SQL pour les bases de données locale et externe de l'USM Volley.

## Commande de Création de Migration
Vous pouvez exécuter le script via Docker :

* **Si vos conteneurs sont déjà lancés (`docker compose up`) :**
  ```bash
  docker compose exec app php .agents/skills/db-migrations/scripts/create.php nom_de_la_migration
  ```

* **Si vos conteneurs ne sont pas lancés :**
  ```bash
  docker compose run --rm app php .agents/skills/db-migrations/scripts/create.php nom_de_la_migration
  ```

**Exemple :**
```bash
docker compose exec app php .agents/skills/db-migrations/scripts/create.php ajouter_table_partenaires
```
Cela créera un fichier comme `database/migrations/026_ajouter_table_partenaires.sql`.

---

## Règles d'Idempotence des Migrations SQL

Puisque les migrations s'exécutent **à chaque démarrage** du conteneur Docker, chaque instruction SQL doit être strictement idempotente pour éviter d'échouer ou de dupliquer des données.

### 1. Création de Table
Toujours utiliser `IF NOT EXISTS` :
```sql
CREATE TABLE IF NOT EXISTS `ma_table` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `titre` VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 2. Ajout de Colonne
MariaDB ne supporte pas nativement le `ADD COLUMN IF NOT EXISTS` dans les anciennes syntaxes, mais sur MySQL 8 / MariaDB récentes, vous pouvez utiliser :
```sql
ALTER TABLE `ma_table` ADD COLUMN IF NOT EXISTS `nouvelle_colonne` VARCHAR(255) NULL;
```
Ou utiliser une procédure stockée conditionnelle si la compatibilité doit être assurée.

### 3. Insertion de Données
Pour insérer sans provoquer d'erreurs de clé primaire/unique :
- **INSERT IGNORE** : Ignore silencieusement l'insertion si la clé existe déjà.
```sql
INSERT IGNORE INTO `site_config` (`cle`, `valeur`) VALUES ('cle_unique', 'ma_valeur');
```
- **ON DUPLICATE KEY UPDATE** : Met à jour la ligne existante en cas de conflit.
```sql
INSERT INTO `site_config` (`cle`, `valeur`) 
VALUES ('cle_unique', 'nouvelle_valeur')
ON DUPLICATE KEY UPDATE `valeur` = VALUES(`valeur`);
```

---

## Fonctionnement des migrations dans Docker
Les conteneurs de migration s'exécutent au démarrage :
1. **Base locale** : Le service `migrate` applique `database/schema.sql` -> `database/seed.sql` -> `database/add_photos.sql` -> puis tous les scripts de `database/migrations/*.sql` triés par nom.
2. **Base externe** : Le service `migrate_external` applique les migrations correspondantes.
