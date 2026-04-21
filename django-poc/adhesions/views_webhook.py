import hashlib
import hmac
import json
import logging
from decimal import Decimal

from django.conf import settings
from django.http import JsonResponse
from django.utils import timezone
from django.views.decorators.csrf import csrf_exempt
from django.views.decorators.http import require_http_methods

from .models import Adhesion, StatutPaiement
from .services.brevo_client import send_payment_confirmed

# En mode DEBUG, autoriser les webhooks sans signature (utile pour sandbox/dev)
SKIP_SIGNATURE_IN_DEBUG = True

logger = logging.getLogger(__name__)

AUTHORIZED_STATES = {"authorized", "Authorized"}
REFUNDED_STATES = {"refunded", "Refunded"}


@require_http_methods(["POST"])
@csrf_exempt
def webhook_helloasso(request):
    """Reçoit les notifications HelloAsso et synchronise le statut de l'adhésion."""
    payload_raw = request.body.decode("utf-8")
    signature = request.headers.get("X-HelloAsso-Signature", "")

    # Vérifier la signature
    if signature:
        if not _verify_signature(payload_raw, signature):
            logger.warning("HelloAsso webhook signature invalide")
            return JsonResponse({"error": "Invalid signature"}, status=401)
    else:
        # Pas de signature
        if not (settings.DEBUG and SKIP_SIGNATURE_IN_DEBUG):
            logger.warning("HelloAsso webhook sans signature (en prod/staging, c'est une erreur)")
            logger.debug("Headers reçus: %s", dict(request.headers))
            return JsonResponse({"error": "No signature"}, status=401)
        logger.warning("HelloAsso webhook sans signature (accepté en DEBUG mode)")

    try:
        payload = json.loads(payload_raw)
    except json.JSONDecodeError:
        logger.error("HelloAsso webhook JSON invalide")
        return JsonResponse({"error": "Invalid JSON"}, status=400)

    webhook_id = payload.get("id")
    if not webhook_id:
        return JsonResponse({"error": "Missing webhook id"}, status=400)

    if Adhesion.objects.filter(helloasso_webhook_id=webhook_id).exists():
        logger.info("HelloAsso webhook %s déjà traité", webhook_id)
        return JsonResponse({"ok": True, "idempotent": True}, status=200)

    try:
        _process_payment(payload)
    except Adhesion.DoesNotExist:
        # Adhésion absente : on renvoie 202 pour que HelloAsso retente plus tard
        return JsonResponse({"error": "Adhesion not found"}, status=202)
    except Exception:
        logger.exception("HelloAsso webhook %s erreur de traitement", webhook_id)
        return JsonResponse({"error": "Processing failed"}, status=202)

    return JsonResponse({"ok": True}, status=200)


def _verify_signature(payload_raw: str, signature: str) -> bool:
    """Vérifie la signature HMAC SHA256 envoyée par HelloAsso."""
    secret = settings.HELLOASSO_WEBHOOK_SECRET.encode()
    expected = hmac.new(secret, payload_raw.encode(), hashlib.sha256).hexdigest()

    # HelloAsso peut envoyer soit "sha256=<hex>" soit "<hex>"
    candidates = [expected, f"sha256={expected}"]
    return any(hmac.compare_digest(signature, c) for c in candidates)


def _process_payment(payload: dict) -> None:
    data = payload.get("data", {}) or {}
    payer = data.get("payer", {}) or {}

    payment_id = data.get("id")
    payer_email = payer.get("email")
    amount_cents = data.get("amount")
    state = data.get("state")
    metadata = data.get("metadata") or {}
    order_id = (data.get("order") or {}).get("id") or data.get("orderId")

    if not (payment_id and payer_email and amount_cents is not None):
        raise ValueError("Payload HelloAsso incomplet")

    amount = Decimal(amount_cents) / Decimal(100)

    adhesion = Adhesion.objects.get(
        user__email__iexact=payer_email,
        montant=amount,
    )

    if state in AUTHORIZED_STATES:
        new_status = StatutPaiement.VALIDE
    elif state in REFUNDED_STATES:
        new_status = StatutPaiement.REMBOURSE
    else:
        new_status = StatutPaiement.EN_ATTENTE

    adhesion.helloasso_payment_id = payment_id
    adhesion.helloasso_order_id = str(order_id) if order_id else None
    adhesion.helloasso_webhook_id = payload.get("id")
    adhesion.helloasso_payer_email = payer_email
    adhesion.helloasso_metadata = metadata
    adhesion.last_webhook_at = timezone.now()
    adhesion.statut_paiement = new_status
    adhesion.save(
        update_fields=[
            "helloasso_payment_id",
            "helloasso_order_id",
            "helloasso_webhook_id",
            "helloasso_payer_email",
            "helloasso_metadata",
            "last_webhook_at",
            "statut_paiement",
            "updated_at",
        ]
    )

    if state in AUTHORIZED_STATES:
        send_payment_confirmed(
            email=adhesion.user.email,
            first_name=adhesion.user.first_name,
            last_name=adhesion.user.last_name,
            amount=adhesion.montant,
            payment_id=payment_id,
        )

    logger.info(
        "Adhésion %s synchronisée: statut=%s payment_id=%s",
        adhesion.id, new_status, payment_id,
    )
