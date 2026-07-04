---
name: docker-runner
description: Aide à l'exécution de commandes, scripts de diagnostic et tests PHP ou SQL à l'intérieur des conteneurs Docker du projet (docker compose) au lieu du système hôte.
---

# Docker Runner Skill

Ce skill fournit des instructions et des exemples pour exécuter de façon sécurisée des scripts de diagnostic, des commandes PHP ou des requêtes de base de données à l'intérieur des conteneurs Docker du projet. Il permet d'éviter l'exécution locale directe sur la machine hôte Windows.

## Principes clés

1. **Pas d'exécution locale pour PHP / SQL** : N'exécutez jamais de commande `php` ou d'outils d'administration de base de données en direct sur le système hôte.
2. **Utilisation systématique de Docker Compose** : Utilisez toujours `docker compose exec` pour interagir avec les services en cours d'exécution.
3. **Répertoire racine** : Le répertoire de travail dans le conteneur `app` est `/var/www/html`.

## Commandes courantes

### Exécuter un script PHP à la racine du projet
Pour exécuter un script PHP (ex: `diagnostic.php` situé à la racine du projet local) :
```bash
docker compose exec -T app php /var/www/html/diagnostic.php
```

### Exécuter du code PHP à la volée (One-liner)
Pour tester rapidement du code :
```bash
docker compose exec -T app php -r "echo PHP_VERSION;"
```

### Lancer les migrations
Les migrations s'appliquent automatiquement au démarrage des conteneurs via les services `migrate` et `migrate_external`. Pour forcer une ré-exécution ou vérifier l'état :
```bash
docker compose restart migrate
docker compose restart migrate_external
```

## Écriture et nettoyage de scripts temporaires
Si vous devez créer un script temporaire pour exécuter un test ou valider des données :
1. Créez le fichier dans le répertoire de travail local du projet (ex: `temp_test.php`).
2. Exécutez-le dans le conteneur `app` :
   ```bash
   docker compose exec -T app php /var/www/html/temp_test.php
   ```
3. Supprimez-le immédiatement après exécution sur l'hôte pour garder le projet propre :
   ```bash
   rm temp_test.php
   ```
