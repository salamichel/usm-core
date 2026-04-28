#!/bin/bash

# Script de test pour l'API de création d'articles
# Usage: ./tests/test-api.sh [base_url]
# Exemple: ./tests/test-api.sh http://localhost:8080

BASE_URL="${1:-http://localhost:8080}"
ENDPOINT="$BASE_URL/api/articles"

echo "🧪 Test de l'API d'articles"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "Endpoint: $ENDPOINT"
echo ""

# Test 1: Requête valide
echo "Test 1️⃣  — Création d'article valide"
curl -X POST "$ENDPOINT" \
  -H "Content-Type: application/json" \
  -d '{
    "canalblog_id": "test-001",
    "title": "Article de test",
    "content": "<p>Contenu HTML de test</p>",
    "published_at": "2025-04-27T12:00:00+02:00",
    "cover_image": "https://picsum.photos/400/300?random=1"
  }' \
  -w "\nStatus: %{http_code}\n" -s | jq .
echo ""

# Test 2: Requête sans champ requis
echo "Test 2️⃣  — Requête sans champ requis (title)"
curl -X POST "$ENDPOINT" \
  -H "Content-Type: application/json" \
  -d '{
    "canalblog_id": "test-002",
    "content": "<p>Test</p>",
    "published_at": "2025-04-27T12:00:00+02:00"
  }' \
  -w "\nStatus: %{http_code}\n" -s | jq .
echo ""

# Test 3: Créer le même article (dédoublonnage)
echo "Test 3️⃣  — Création avec canalblog_id déjà existant (dédoublonnage)"
curl -X POST "$ENDPOINT" \
  -H "Content-Type: application/json" \
  -d '{
    "canalblog_id": "test-001",
    "title": "Autre titre",
    "content": "<p>Autre contenu</p>",
    "published_at": "2025-04-27T12:00:00+02:00"
  }' \
  -w "\nStatus: %{http_code}\n" -s | jq .
echo ""

# Test 4: Requête GET (method not allowed)
echo "Test 4️⃣  — Requête GET (method not allowed)"
curl -X GET "$ENDPOINT" \
  -w "\nStatus: %{http_code}\n" -s | jq .
echo ""

# Test 5: JSON invalide
echo "Test 5️⃣  — JSON invalide"
curl -X POST "$ENDPOINT" \
  -H "Content-Type: application/json" \
  -d 'pas du json valide' \
  -w "\nStatus: %{http_code}\n" -s | jq .
echo ""

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "✅ Tests terminés"
