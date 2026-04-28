# Étude de faisabilité — Intégration Facebook API

## Contexte

USM Volley souhaite intégrer les APIs Facebook pour :
- **Poster des messages** sur la page/groupe Facebook du club
- **Récupérer des posts** depuis la page/groupe pour les afficher sur le site

Cet document évalue la faisabilité technique compte tenu des contraintes du projet.

---

## Contraintes du projet

### Hébergement
- **InfinityFree** (shared hosting, no SSH, no Composer in production)
- Les dépendances PHP (`vendor/`) sont versionnées dans Git
- Pas d'exécution de scripts post-déploiement
- Limites possibles sur les requêtes HTTP sortantes

### Architecture actuelle
- **Stack minimaliste** : PHP 8.2 + Twig (seule dépendance externe)
- **Pas d'ORM** : accès direct PDO
- **Services** : simples (Validator, Logger, SlugManager, AgendaService)
- **API existante** : reçoit des articles via HTTP POST (CanalBlog)

### Opérations en prod
- Code variationnisé + manual push (pas de CI/CD)
- Aucun outil CLI en prod
- Pas de webhooks natifs (InfinityFree bloque HTTPS entrant)

---

## Options d'intégration

### Option 1 : SDK Facebook officiel (`facebook/graph-sdk`)

**Avantages :**
- Support complet et officiel
- Gestion automatique des tokens, refreshes, paginationq
- Gestion des erreurs standardisée
- Mises à jour fréquentes suivant la v1 de l'API Graph

**Inconvénients :**
- Dépendance externe à maintenir
- Installation via Composer (doit être versionnée dans `vendor/`)
- ~500 KB ajouté (minimal)
- Complexité accrue pour un use case simple

**Installation :**
```bash
composer require facebook/graph-sdk
# Puis commit vendor/facebook/* dans Git
```

**Exemple d'utilisation :**
```php
use Facebook\Facebook;

$fb = new Facebook([
    'app_id'     => getenv('FB_APP_ID'),
    'app_secret' => getenv('FB_APP_SECRET'),
    'default_access_token' => getenv('FB_PAGE_ACCESS_TOKEN'),
]);

try {
    $response = $fb->post('/{page-id}/feed', [
        'message' => 'Nouvelle équipe en ligne!',
    ]);
    // Succès
} catch (Exception $e) {
    // Erreur
}
```

**Coût en maintenance :** Moyen (suivre les mises à jour)

---

### Option 2 : Requêtes HTTP brutes (cURL/file_get_contents)

**Avantages :**
- Zéro dépendance externe
- Contrôle total sur les requêtes
- Très léger
- Aligné avec l'architecture minimaliste

**Inconvénients :**
- Gestion manuelle des erreurs, retries, pagination
- Pas de refresh token automatique
- Code plus verbeux/répétitif
- Risque d'erreurs si pagination mal gérée

**Exemple d'utilisation :**
```php
$token = getenv('FB_PAGE_ACCESS_TOKEN');
$pageId = getenv('FB_PAGE_ID');

$url = "https://graph.facebook.com/v21.0/{$pageId}/feed";
$data = json_encode(['message' => 'Nouvelle équipe!']);

$ctx = stream_context_create([
    'http' => [
        'method'  => 'POST',
        'header'  => "Content-Type: application/json\r\n",
        'content' => $data,
    ],
]);

$response = @file_get_contents(
    "{$url}?access_token={$token}",
    false,
    $ctx
);
```

**Coût en maintenance :** Bas (code simple, peu de dépendances)

---

### Option 3 : Service wrapper personnalisé

**Avantages :**
- Abstraction de la complexité
- Isolation des détails Facebook
- Réutilisable et testable
- Peut utiliser SDK ou HTTP brutes

**Inconvénients :**
- Travail de développement supplémentaire
- Maintenance spécifique au projet

**Architecture :**
```
src/Services/FacebookService.php
├── postMessage($message)
├── getPagePosts($limit)
├── refreshToken()
└── handleErrors()
```

---

## Cas d'usage détaillés

### 1️⃣ Poster les articles/news sur Facebook

**Déclencheur :**
- Création d'un post via `/admin/posts/create`
- Validation du formulaire
- Option « Partager sur Facebook » (checkbox)

**Données à envoyer :**
```json
{
  "message": "Titre du post",
  "link": "https://usm-volley.fr/blog/slug-article",
  "picture": "https://usm-volley.fr/assets/uploads/cover.jpg",
  "description": "Extrait du post..."
}
```

**Implémentation :**
```php
// Dans PostController::store()
if (!empty($_POST['share_to_facebook'])) {
    FacebookService::sharePost($postId);
}
```

**Complexité :** ⭐⭐ (Simple, synchrone)

---

### 2️⃣ Poster les résultats de matchs

**Déclencheur :**
- Admin rentre un résultat de match
- Option « Annoncer sur Facebook »

**Données :**
```
La saison 2025-2026 a commencé ! 🏐
Équipe A : 3-1 contre Nantes
Équipe B : 2-3 contre Lyon
🔗 Voir le classement : [lien]
```

**Complexité :** ⭐ (Très simple)

---

### 3️⃣ Afficher les derniers posts Facebook sur le site

**Déclencheur :**
- Page d'accueil (`HomeController`)
- Widget « Actualités Facebook »

**Données à récupérer :**
- Titre / texte
- Image
- Lien
- Date
- Nombre de commentaires/réactions

**Cache :**
- Mettre en cache 1h (évite appels API répétés)
- Stocker en BDD ou fichier

**Exemple d'appel :**
```
GET https://graph.facebook.com/v21.0/{page-id}/feed
?fields=message,picture,link,story,created_time,comments.limit(0).summary(total_count),reactions.limit(0).summary(total_count)
&access_token={token}
&limit=10
```

**Complexité :** ⭐⭐⭐ (pagination, cache, gestion d'erreurs)

---

### 4️⃣ Automatiser : syncer les posts Facebook → articles du site

**Déclencheur :**
- CRON quotidien (impossible sur InfinityFree)
- **Alternative :** Endpoint `/admin/facebook/sync` (manuel, avec Auth)

**Workflow :**
1. Récupérer les N derniers posts Facebook
2. Pour chaque post : vérifier s'il existe en BDD (`facebook_post_id`)
3. Créer un Post local + télécharger l'image
4. Log des syncs

**Problème critique :** InfinityFree **n'a pas de CRON natif**
- Solution : utiliser un service externe (UptimeRobot, etc.) pour faire un appel HTTP POST
- Coût de maintenance : Moyen

**Complexité :** ⭐⭐⭐⭐ (pagination, dédoublonnage, webhooks)

---

## Points critiques

### 🔐 Authentification

**Types de tokens Facebook :**

| Type | Durée | Usage | Risque |
|---|---|---|---|
| App Token | ∞ | API serveur-à-serveur | Critique (stocké en config) |
| Page Token | ∞ | Poster/lire depuis page | Critique |
| User Token | ~60j | Actions utilisateur | Renouvellement régulier |

**Recommandation :** Utiliser **Page Token** (+ durable, scoped à la page)

**Stockage :**
```php
define('FB_PAGE_ACCESS_TOKEN', getenv('FB_PAGE_ACCESS_TOKEN'));
```

**Sécurité :**
- ✅ Jamais en `.env` commité (`.env.local` + gitignore)
- ✅ Stocké en variable d'environnement en prod
- ✅ Limiter les scopes (`publish_pages,pages_read_engagement`)

---

### 📊 Quotas & Limites

**Limites Facebook :**
- **Rate limit :** 180 appels/utilisateur/heure (burst : 6 appels/10 sec)
- **Pagination :** max 100 posts par page
- **Données publiques :** OK si page est publique

**Sur InfinityFree :**
- Connexions sortantes : généralement OK pour HTTPS
- Timeout : ~30 sec (à adapter)
- Pas de webhooks entrants (HTTPS obligatoire, IP fixe requise)

---

### ❌ Cas impossibles sur InfinityFree

1. **Webhooks entrants** (`/webhook/facebook`)
   - InfinityFree n'accepte pas les webhooks (pas d'IP fixe, HTTPS varié)
   - Workaround : polling manuel via endpoint `/admin/facebook/sync`

2. **CRON automatique**
   - Pas de cron natif
   - Workaround : service externe (UptimeRobot gratuit) qui ping `/admin/facebook/sync`

3. **Authentification OAuth utilisateur**
   - Nécessite redirect flow, token refresh
   - Possible mais complexe

---

## Recommandations

### ✅ À implémenter en priorité (faisable)

#### 1️⃣ **Partage manuel d'articles vers Facebook** ⭐⭐

**Effort :** 2-3h  
**Valeur :** Haute (boost visibilité club)  
**Intégration :**
- Ajouter checkbox « Partager sur Facebook » dans `/admin/posts/create`
- Appeler `FacebookService::sharePost($postId)` après création
- Stockage token en variable d'environnement

**Implémentation :**
```php
// src/Services/FacebookService.php
class FacebookService {
    public static function sharePost(int $postId): bool {
        $post = Post::find($postId);
        // Composer le message + image + lien
        // Faire POST sur Graph API
        // Logguer le succès/erreur
    }
}
```

---

#### 2️⃣ **Afficher les N derniers posts Facebook sur l'accueil** ⭐⭐⭐

**Effort :** 4-6h  
**Valeur :** Moyenne (mais dynamique)  
**Intégration :**
- Service `FacebookService::getPagePosts($limit, $cache_ttl)`
- Cache BDD ou fichier (1h)
- Template widget réutilisable
- Fallback gracieux si Facebook inaccessible

---

### ⚠️ À considérer (plus complexe)

#### 3️⃣ **Synchronisation semi-automatique** ⭐⭐⭐⭐

**Effort :** 8-12h  
**Valeur :** Haute (mais infrastructure)  
**Intégration :**
- Endpoint `/admin/facebook/sync` (nécessite Auth)
- CRON via service externe (UptimeRobot)
- Dédoublonnage via `facebook_post_id`
- Téléchargement images

**Problèmes :**
- Dépendance à UptimeRobot (free tier: max 50 appels/mois)
- Coût de maintenance (monitoring, alertes)

---

### ❌ À éviter

#### 4️⃣ **Webhooks Facebook entrants**
- ❌ Impossible sur InfinityFree
- Pas d'IP fixe, pas de tunneling

---

## Stack recommandé

### Option A : Requêtes HTTP brutes + Service wrapper

```
src/Services/FacebookService.php
├── Pas de dépendance externe
├── Requêtes cURL ou file_get_contents
├── Gestion d'erreurs simple
└── Cas simples (partage, lecture)

Effort : 3-4h
Maintenance : Basse
Scalabilité : OK jusqu'à ~10 appels/jour
```

### Option B : SDK Facebook + Service wrapper

```
vendor/facebook/graph-sdk
src/Services/FacebookService.php
├── SDK officiel pour pagination, retries
├── Wrapper personnalisé pour cas métier
└── Plus robuste

Effort : 4-5h (+ installation SDK)
Maintenance : Moyenne
Scalabilité : Excellent
```

---

## Checklist d'implémentation

### Phase 1 : Configuration
- [ ] Créer une app Facebook (facebook.com/developers)
- [ ] Générer un **Page Token** (long-lived, idéalement)
- [ ] Tester le token manuellement (`curl -X GET "https://graph.facebook.com/v21.0/me?access_token={token}"`)
- [ ] Stocker token en variable d'environnement (InfinityFree panel ou `.env`)

### Phase 2 : Implémentation basique (partage)
- [ ] Créer `src/Services/FacebookService.php`
- [ ] Implémenter `sharePost($postId, $message, $image_url, $link)`
- [ ] Ajouter logs et gestion d'erreurs
- [ ] Tester localement (Docker)

### Phase 3 : Lecture de posts
- [ ] Implémenter `getPagePosts($limit = 10)`
- [ ] Ajouter cache (1h TTL)
- [ ] Template widget pour accueil
- [ ] Fallback si Facebook down

### Phase 4 : Bonus (si temps)
- [ ] Endpoint `/admin/facebook/sync`
- [ ] Config UptimeRobot pour polling quotidien
- [ ] Dédoublonnage via `facebook_post_id` en BDD

---

## Estimation temps (en heures)

| Feature | Effort | Notes |
|---|---|---|
| Configuration app Facebook | 1-2h | Administratif |
| Service HTTP brutes | 3h | Partage + lecture basique |
| Service avec SDK | 4h | Installation + wrapper |
| Widget affichage | 2h | Twig + cache |
| Sync semi-auto | 6h | Endpoint + cronjob |
| Tests + déploiement | 2-3h | - |
| **Total minimal** | **6-8h** | Partage + affichage |
| **Total complet** | **15-20h** | Avec sync |

---

## Risques & mitigations

| Risque | Probabilité | Mitigation |
|---|---|---|
| Token expiré | Haute | Vérifier token avant chaque appel, page tokens = durée long |
| Rate limiting | Basse | Cacher les résultats (1h), limiter à 1-2 appels/jour |
| Page supprimée/inaccessible | Basse | Try/catch, log erreur, fallback gracieux |
| Dépendance Facebook down | Basse | Cache + fallback HTML statique |
| Changement API Graph | Moyenne | Suivre notifications Facebook, tests réguliers |

---

## Conclusion

### ✅ Verdict : **Faisable et recommandé**

**Recommandation :** Commencer par **Option A (requêtes HTTP) ou Option B (SDK)** selon l'équipe :

- **Option A (HTTP brutes)** : Si minimalisme prioritaire, peu d'appels
- **Option B (SDK)** : Si robustesse prioritaire, sync future possible

**Priorités :**
1. **Phase 1** : Partage d'articles (haute valeur, faible complexité)
2. **Phase 2** : Affichage posts (valeur moyenne, complexité moyenne)
3. **Phase 3** : Sync automatique (valeur haute, complexité haute, dépend infra)

**Timeline :** 1-2 sprints pour les phases 1-2 (complet et en prod)
