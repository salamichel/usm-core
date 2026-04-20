"""Client HelloAsso : OAuth2 client_credentials + création de checkout intents."""
from __future__ import annotations

import logging
import time
import urllib.parse
import urllib.request
import json
from dataclasses import dataclass
from decimal import Decimal
from typing import Optional

from django.conf import settings

from helloasso_python import (
    ApiClient,
    Configuration,
    CheckoutApi,
    HelloAssoApiV5ModelsCartsInitCheckoutBody,
    HelloAssoApiV5ModelsCartsCheckoutPayer,
)

logger = logging.getLogger(__name__)


class HelloAssoError(Exception):
    """Erreur générique HelloAsso (auth ou API)."""


class HelloAssoNotConfigured(HelloAssoError):
    """Les credentials HelloAsso ne sont pas renseignés dans .env."""


@dataclass
class CheckoutIntent:
    """Réponse d'un checkout intent créé."""
    id: str
    redirect_url: str


# Cache mémoire du token (process-level, suffisant pour un POC)
_token_cache: dict = {"access_token": None, "expires_at": 0}


def _oauth_base_host() -> str:
    """Dérive l'URL OAuth2 depuis l'API host (ex: api.helloasso.com → api.helloasso.com/oauth2/token)."""
    api_host = settings.HELLOASSO_API_HOST.rstrip("/")
    # "https://api.helloasso.com/v5" → "https://api.helloasso.com/oauth2/token"
    if api_host.endswith("/v5"):
        api_host = api_host[:-3]
    return f"{api_host}/oauth2/token"


def _fetch_token() -> str:
    """Appelle HelloAsso OAuth2 (grant_type=client_credentials) et retourne un access_token."""
    if not settings.HELLOASSO_CLIENT_ID or not settings.HELLOASSO_CLIENT_SECRET:
        raise HelloAssoNotConfigured(
            "HELLOASSO_CLIENT_ID / HELLOASSO_CLIENT_SECRET manquants dans .env"
        )

    data = urllib.parse.urlencode({
        "grant_type": "client_credentials",
        "client_id": settings.HELLOASSO_CLIENT_ID,
        "client_secret": settings.HELLOASSO_CLIENT_SECRET,
    }).encode()

    req = urllib.request.Request(
        _oauth_base_host(),
        data=data,
        headers={"Content-Type": "application/x-www-form-urlencoded"},
        method="POST",
    )
    try:
        with urllib.request.urlopen(req, timeout=10) as resp:
            body = json.loads(resp.read().decode())
    except Exception as e:
        logger.error("HelloAsso OAuth2 token failed: %s", e)
        raise HelloAssoError(f"OAuth2 token request failed: {e}") from e

    access_token = body.get("access_token")
    expires_in = int(body.get("expires_in", 1800))
    if not access_token:
        raise HelloAssoError(f"Réponse OAuth2 invalide: {body}")

    # Cache jusqu'à 60s avant l'expiration pour éviter les races
    _token_cache["access_token"] = access_token
    _token_cache["expires_at"] = time.time() + expires_in - 60
    return access_token


def _get_access_token() -> str:
    """Retourne un access_token valide (cache en mémoire, refresh si expiré)."""
    if _token_cache["access_token"] and time.time() < _token_cache["expires_at"]:
        return _token_cache["access_token"]
    return _fetch_token()


def _api_client() -> ApiClient:
    cfg = Configuration(host=settings.HELLOASSO_API_HOST)
    cfg.access_token = _get_access_token()
    return ApiClient(cfg)


def create_checkout_intent(
    *,
    amount: Decimal,
    item_name: str,
    back_url: str,
    return_url: str,
    error_url: str,
    payer_email: str,
    payer_first_name: str,
    payer_last_name: str,
    metadata: Optional[dict] = None,
) -> CheckoutIntent:
    """Crée un checkout intent HelloAsso et retourne l'URL de redirection à suivre."""
    if not settings.HELLOASSO_ORGANIZATION_SLUG:
        raise HelloAssoNotConfigured("HELLOASSO_ORGANIZATION_SLUG manquant dans .env")

    amount_cents = int((amount * 100).quantize(Decimal("1")))

    payer = HelloAssoApiV5ModelsCartsCheckoutPayer(
        first_name=payer_first_name or "Adherent",
        last_name=payer_last_name or "USM",
        email=payer_email,
    )

    body = HelloAssoApiV5ModelsCartsInitCheckoutBody(
        total_amount=amount_cents,
        initial_amount=amount_cents,
        item_name=item_name[:255],
        back_url=back_url,
        return_url=return_url,
        error_url=error_url,
        contains_donation=False,
        payer=payer,
        metadata=metadata or {},
    )

    with _api_client() as client:
        api = CheckoutApi(client)
        resp = api.organizations_organization_slug_checkout_intents_post(
            organization_slug=settings.HELLOASSO_ORGANIZATION_SLUG,
            body=body,
        )

    # Réponse : { id, redirectUrl }
    redirect_url = getattr(resp, "redirect_url", None) or getattr(resp, "redirectUrl", None)
    intent_id = getattr(resp, "id", None)
    if not redirect_url or not intent_id:
        raise HelloAssoError(f"Réponse checkout invalide: {resp}")

    return CheckoutIntent(id=str(intent_id), redirect_url=str(redirect_url))
