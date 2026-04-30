# Google Analytics Integration — USM Volley

## Configuration

### 1. Obtenir une clé Google Analytics

1. Accédez à [Google Analytics](https://analytics.google.com)
2. Créez une nouvelle propriété pour votre site
3. Copiez l'**ID de mesure** (format : `G-XXXXXXXXXX`)

### 2. Configurer l'environnement

Ajouter la variable d'environnement `GA_MEASUREMENT_ID` à votre `.env` ou configuration d'hébergement :

```bash
export GA_MEASUREMENT_ID="G-XXXXXXXXXX"
```

**Sur InfinityFree (production)** :
- Ajouter via le panneau d'administration du compte
- La variable est relue au démarrage

**En développement (Docker)** :
```bash
# Dans docker-compose.yml ou via l'environnement
GA_MEASUREMENT_ID=G-XXXXXXXXXX docker compose up
```

### 3. Vérifier l'intégration

- Ouvrir Developer Tools (F12) → Console
- Recharger la page
- Chercher les messages liés à gtag
- Dans Google Analytics, aller à Rapports → Temps réel

## Événements suivis automatiquement

### 1. **Page Views**
- Chaque page visitée est suivie automatiquement
- Inclut le chemin, le titre, et les paramètres URL

### 2. **Soumissions de formulaires**
- Tous les formulaires (contact, login, posts, etc.)
- Événement : `form_submit`
- Données : `form_id`, `form_class`, `form_action`

### 3. **Clics sur liens externes**
- Liens vers des domaines différents
- Événement : `click_external_link`
- Données : `link_url`, `link_text`

### 4. **Téléchargements de fichiers**
- PDF, DOC, XLS, ZIP, etc.
- Événement : `file_download`
- Données : `file_name`, `file_type`, `file_url`

### 5. **Actions suivies (data-track)**
- Boutons et liens avec attribut `data-track`
- Événement : `tracked_action`
- Données : `action_name`, `action_text`

### 6. **Filtres agenda**
- Changements de filtres (équipe, type, lieu, etc.)
- Événement : `agenda_filter_change`
- Données : `filter_name`, `filter_value`

### 7. **Clics sur équipes**
- Clics sur les cartes d'équipes
- Événement : `equipe_click`
- Données : `equipe_id`, `equipe_name`

### 8. **Profondeur de scroll**
- Positions 25%, 50%, 75%, 100% du contenu
- Événement : `scroll_depth`
- Données : `scroll_percent`

### 9. **Visibilité de page**
- Quand l'utilisateur quitte/revient à la page
- Événement : `page_hidden` / `page_visible`

### 10. **Clics de navigation**
- Clics sur le menu principal
- Événement : `navigation_click`
- Données : `nav_text`, `nav_url`

### 11. **Chargement de page**
- Temps de chargement total et DOM
- Événement : `page_load`
- Données : `load_time_ms`, `dom_content_loaded_ms`

## Ajouter du tracking personnalisé

### Méthode 1 : Attribut data-track

Pour suivre un bouton ou un lien spécifique :

```twig
<button data-track="my_action_name">Cliquer</button>
<a href="#" data-track="my_link_action">Lien</a>
```

L'événement sera automatiquement envoyé avec :
- `action_name` : valeur de `data-track`
- `action_text` : texte du bouton/lien

### Méthode 2 : Code JavaScript personnalisé

Dans un template Twig, ajouter un script :

```twig
{% block scripts %}
<script>
  document.querySelector('#my-element').addEventListener('click', function() {
    if (typeof trackEvent === 'function') {
      trackEvent('my_custom_event', {
        custom_data: 'value',
        user_action: 'something'
      });
    }
  });
</script>
{% endblock %}
```

## Dashboard Google Analytics recommandé

### Rapports essentiels

1. **Vue d'ensemble**
   - Utilisateurs actifs en temps réel
   - Taux de rebond
   - Durée de session moyenne

2. **Acquisition**
   - Trafic par source (direct, organic, referral)
   - Canaux
   - Campagnes

3. **Engagement**
   - Événements les plus importants
   - Pages les plus visitées
   - Entrées et sorties

4. **Conversion**
   - Soumissions de formulaire de contact
   - Téléchargements de fichiers
   - Actions spécifiques

### Créer une vue personnalisée

Pour tracker les actions d'admin :

1. Aller à Admin → Propriété → Filtres
2. Ajouter un filtre pour exclure les visites locales (IP locale)
3. Créer des audiences pour les utilisateurs authentifiés (si GA côté serveur)

## Dépannage

### GA ne suit rien

1. Vérifier que `GA_MEASUREMENT_ID` est défini
2. Vérifier la console : chercher `gtag is not defined`
3. Vérifier que le script Google s'est chargé (onglet Network)
4. Attendre 24-48h pour voir les données dans GA

### Les événements ne s'enregistrent pas

1. Vérifier dans Google Analytics → Rapports temps réel → Événements
2. S'assurer que la clé de mesure est correcte
3. Vérifier qu'aucun plugin ne bloque le script (`uBlock Origin`, etc.)

### Données sensibles envoyées à GA

⚠️ **Important** : Ne PAS tracer :
- Mots de passe
- Tokens CSRF
- Données personnelles sensibles
- Contenu de formulaires complets

Le tracking actuel ne trace que :
- Noms de formulaires (pas les valeurs)
- URLs
- Clics généraux

## Conformité RGPD

Pour être conforme RGPD :

1. **Bandeaux de consentement** (à ajouter)
   - Avertir l'utilisateur que GA est utilisé
   - Obtenir le consentement avant de charger GA

2. **Politique de confidentialité**
   - Mentionner Google Analytics
   - Expliquer les données tracées

3. **Configuration GA**
   - Activer l'anonymisation IP
   - Réduire la rétention des données à 14 mois

## Resources

- [Google Analytics 4 Documentation](https://support.google.com/analytics/answer/10089681)
- [Gtag.js Reference](https://developers.google.com/analytics/devguides/collection/gtagjs)
- [Google Analytics Events](https://support.google.com/analytics/answer/9267744)
