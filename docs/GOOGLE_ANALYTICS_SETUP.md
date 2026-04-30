# Setup Google Analytics Dashboard — Guide Complet

## 📋 Prérequis

- Compte Google Analytics 4 (GA4) existant
- Accès à Google Cloud Platform (GCP)
- Herbergement permettant les variables d'environnement (InfinityFree n'a pas de fichiers JSON, voir section Alternatives)

---

## 🔧 Étape 1 : Créer un Service Account sur Google Cloud Platform

### 1.1 Créer un projet GCP

1. Allez sur [Google Cloud Console](https://console.cloud.google.com/)
2. Créez un nouveau projet : `USM Volley Analytics`
3. Activez l'API **"Google Analytics Data API v1"**
   - Recherchez "Google Analytics Data API"
   - Cliquez sur "Activer"

### 1.2 Créer un Service Account

1. Allez à **IAM & Admin** → **Service Accounts**
2. Cliquez sur **"Créer un compte de service"**
3. Remplissez :
   - **Nom du service** : `usm-analytics`
   - **Déscription** : `Service account for USM Volley analytics`
4. Cliquez sur **"Créer et continuer"**
5. Accordez le rôle **"Basic Editor"** (ou "Viewer" pour read-only)
6. Cliquez sur **"Continuer"** puis **"Terminer"**

### 1.3 Créer une clé JSON

1. Cliquez sur le service account créé
2. Allez à l'onglet **"Clés"**
3. Cliquez sur **"Ajouter une clé"** → **"Créer une nouvelle clé"**
4. Sélectionnez **"JSON"** et cliquez sur **"Créer"**
5. Un fichier `credentials.json` se télécharge
   - **Sauvegardez-le en sécurité**

---

## 🔑 Étape 2 : Autoriser le Service Account dans Google Analytics

### 2.1 Ajouter le service account à GA4

1. Allez à votre propriété GA4
2. Cliquez sur **Admin** (icône engrenage en bas à gauche)
3. Allez à **Accès et sécurité** → **Gestion des accès**
4. Cliquez sur **"Ajouter des contributeurs"**
5. Copiez l'email du service account (format : `usm-analytics@PROJECT_ID.iam.gserviceaccount.com`)
   - Trouvez-le dans le fichier `credentials.json` (clé `"client_email"`)
6. Collez l'email et accordez le rôle **"Éditeur"** ou **"Lecteur et analyste"**
7. Validez

### 2.2 Récupérer l'ID de propriété GA4

1. Dans **Admin** → **Paramètres de la propriété**
2. Copiez l'**"ID de propriété"** (format : `123456789`)

---

## 📁 Étape 3 : Configurer les Variables d'Environnement

### En développement (Docker)

**1. Placez le fichier `credentials.json` dans le projet** (ne pas commiter !)

```bash
# À la racine du projet
cp ~/Downloads/credentials.json ./config/google-credentials.json
```

**2. Ajoutez à `.env` ou `docker-compose.yml` :**

```bash
GA_PROPERTY_ID=123456789
GA_CREDENTIALS_PATH=/app/config/google-credentials.json
```

**3. Mettez à jour `docker-compose.yml` :**

```yaml
services:
  app:
    environment:
      - GA_PROPERTY_ID=123456789
      - GA_CREDENTIALS_PATH=/app/config/google-credentials.json
```

**4. Lancez le projet :**

```bash
docker compose up --build
```

### En production (InfinityFree)

⚠️ **InfinityFree ne supporte pas les fichiers JSON en variables d'environnement.**

**Solution 1 : Encoder le JSON en base64**

```bash
# Sur votre machine locale
base64 -i config/google-credentials.json

# Résultat : eyJhbGciOiJIUzI1Ni...
```

Puis mettez à jour le code pour décoder :

```php
// config/config.php
$credentialsJson = base64_decode(getenv('GA_CREDENTIALS_BASE64') ?: '');
if ($credentialsJson) {
    $tmpPath = sys_get_temp_dir() . '/ga-credentials-' . md5($credentialsJson) . '.json';
    if (!file_exists($tmpPath)) {
        file_put_contents($tmpPath, $credentialsJson);
    }
    define('GA_CREDENTIALS_PATH', $tmpPath);
} else {
    define('GA_CREDENTIALS_PATH', '');
}
```

**Solution 2 : Service tiers (Plausible, Fathom)**

Utilisez une alternative plus simple (voir GOOGLE_ANALYTICS.md).

---

## ✅ Étape 4 : Tester la Configuration

### 4.1 Vérifier l'installation du package

```bash
composer show google/analytics-data
```

Doit afficher : `google/analytics-data  v0.23.3`

### 4.2 Tester la connexion

**Créez un fichier de test temporaire** (`test-ga.php`) :

```php
<?php
require 'vendor/autoload.php';

putenv('GOOGLE_APPLICATION_CREDENTIALS=/app/config/google-credentials.json');

$client = new \Google\Analytics\Data\V1beta\BetaAnalyticsDataClient();
$propertyId = '123456789'; // Remplacez par votre ID

try {
    $response = $client->runReport(
        new \Google\Analytics\Data\V1beta\RunReportRequest([
            'property' => 'properties/' . $propertyId,
            'date_ranges' => [new \Google\Analytics\Data\V1beta\DateRange([
                'start_date' => '7daysAgo',
                'end_date' => 'today',
            ])],
            'metrics' => [new \Google\Analytics\Data\V1beta\Metric(['name' => 'screenPageViews'])],
        ])
    );
    
    echo "✅ Connexion réussie!\n";
    echo "Pageviews: " . $response->getRows()[0]->getMetricValues()[0]->getValue() . "\n";
} catch (\Exception $e) {
    echo "❌ Erreur: " . $e->getMessage() . "\n";
}
?>
```

Lancez :

```bash
php test-ga.php
```

Résultat attendu : `✅ Connexion réussie!`

### 4.3 Vérifier dans le dashboard admin

1. Allez à `/admin/analytics`
2. Si configuré, les metrics s'affichent
3. Si non configuré, un message d'erreur explique la configuration manquante

---

## 📊 Utilisation du Dashboard

### Accès

```
/admin/analytics
```

### Périodes disponibles

- **7 derniers jours** (défaut)
- **30 derniers jours**
- **90 derniers jours**
- **Ce mois-ci**

### Métriques affichées

| Métrique | Description |
|----------|-------------|
| **Vues de page** | Nombre total de pages consultées |
| **Utilisateurs actifs** | Nombre d'utilisateurs uniques |
| **Taux de rebond** | % de sessions avec une seule page |
| **Formulaires** | Tous les formulaires soumis |
| **Formulaires de contact** | Spécifiquement `/contact` |
| **Téléchargements** | PDF, DOC, XLS, etc. |
| **Liens externes** | Clics vers autres domaines |
| **Pages populaires** | Top 5 pages les plus visitées |
| **Sources de trafic** | Top 5 sources (Google, Direct, etc.) |

---

## 🐛 Dépannage

### "GA_CREDENTIALS_PATH non configuré"

```
Assurez-vous que GA_CREDENTIALS_PATH est défini dans :
- docker-compose.yml (dev)
- Variables d'environnement de l'hébergement (prod)
```

### "Fichier credentials.json introuvable"

```
Vérifiez le chemin exact du fichier :
- En dev : config/google-credentials.json
- En prod : voir solution base64 ci-dessus
```

### "Connexion refusée" ou "403 Forbidden"

```
1. Vérifiez que le service account est ajouté à GA4
2. Vérifiez les permissions : Lecteur & Analyste minimum
3. Vérifiez l'ID de propriété (format: 123456789)
4. Attendez 5-10 minutes après l'ajout du service account
```

### Données vides ou "0" partout

```
1. Les données prennent 24-48h pour apparaître
2. Vérifiez que le site collecte les données (GA_MEASUREMENT_ID configuré)
3. Allez sur analytics.google.com et vérifiez le "Temps réel"
```

### Erreur "Could not load the default credentials"

```
Vérifiez que :
1. putenv('GOOGLE_APPLICATION_CREDENTIALS=...') est appelé
2. Le chemin du fichier credentials.json existe
3. Les permissions du fichier permettent la lecture (chmod 644)
```

---

## 🔒 Sécurité

### À faire

✅ Stockez `credentials.json` en sécurité
- Ne le committez jamais dans Git
- Ajoutez-le à `.gitignore`
- Utilisez des variables d'environnement

✅ Limitez les permissions du service account
- Utilisez "Lecteur & Analyste" (read-only) si possible
- Créez un service account dédié à chaque environnement

✅ Rotation des clés
- Créez une nouvelle clé tous les 90 jours
- Supprimez l'ancienne clé après test

### À éviter

❌ Ne sharchez jamais `credentials.json`
❌ Ne l'envoyez pas par email
❌ Ne le publiez pas sur GitHub

---

## 📚 Resources

- [Google Analytics Data API Docs](https://developers.google.com/analytics/devguides/reporting/data/v1)
- [Google Cloud Console](https://console.cloud.google.com/)
- [Google Analytics 4 Admin](https://analytics.google.com/)
- [PHP Analytics Data Client](https://packagist.org/packages/google/analytics-data)

