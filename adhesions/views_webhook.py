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
        logger.debug("HelloAsso webhook payload: %s", json.dumps(payload, indent=2))
    except json.JSONDecodeError:
        logger.error("HelloAsso webhook JSON invalide: %s", payload_raw)
        return JsonResponse({"error": "Invalid JSON"}, status=400)

    # HelloAsso format réel : pas d'id racine, on utilise data.id (payment_id) pour l'idempotence
    data = payload.get("data", {}) or {}
    webhook_id = str(data.get("id") or "")
    if not webhook_id:
        logger.error("HelloAsso webhook sans data.id: %s", payload)
        return JsonResponse({"error": "Missing payment id"}, status=400)

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


def _extract_adhesion_id(payload: dict) -> int | None:
    """Cherche adhesion_id dans plusieurs emplacements possibles de la payload."""
    candidates = [
        payload.get("metadata"),
        (payload.get("data") or {}).get("metadata"),
        ((payload.get("data") or {}).get("order") or {}).get("metadata"),
    ]
    for meta in candidates:
        if isinstance(meta, dict) and meta.get("adhesion_id"):
            try:
                return int(meta["adhesion_id"])
            except (ValueError, TypeError):
                continue
    return None


def _resolve_adhesion(payload: dict) -> Adhesion:
    """
    Trouve l'adhésion correspondant au webhook via plusieurs stratégies :
    1. metadata.adhesion_id (plusieurs locations possibles) ← le plus fiable
    2. helloasso_order_id (stocké via querystring de retour)
    3. helloasso_checkout_intent_id (depuis data.checkoutIntentId si présent)
    4. email + montant (fallback ambigu, peut échouer si plusieurs matches)
    """
    data = payload.get("data", {}) or {}

    # 1. metadata.adhesion_id dans n'importe quelle location
    adhesion_id = _extract_adhesion_id(payload)
    if adhesion_id:
        try:
            adhesion = Adhesion.objects.get(pk=adhesion_id)
            logger.info("Resolved adhesion %s via metadata.adhesion_id", adhesion.pk)
            return adhesion
        except Adhesion.DoesNotExist:
            logger.warning("metadata.adhesion_id=%s pointe sur une adhésion inexistante", adhesion_id)

    # 2. helloasso_order_id (depuis data.order.id — set sur la querystring de retour)
    order_id = (data.get("order") or {}).get("id") or data.get("orderId")
    if order_id:
        adhesion = Adhesion.objects.filter(helloasso_order_id=str(order_id)).first()
        if adhesion:
            logger.info("Resolved adhesion %s via helloasso_order_id=%s", adhesion.pk, order_id)
            return adhesion

    # 3. checkoutIntentId si renvoyé dans le payload
    checkout_intent_id = data.get("checkoutIntentId") or data.get("checkoutIntentID")
    if checkout_intent_id:
        adhesion = Adhesion.objects.filter(
            helloasso_checkout_intent_id=str(checkout_intent_id)
        ).first()
        if adhesion:
            logger.info(
                "Resolved adhesion %s via helloasso_checkout_intent_id=%s",
                adhesion.pk, checkout_intent_id,
            )
            return adhesion

    # 4. Fallback : email + montant (ambigu si plusieurs adhésions au même prix)
    payer_email = (data.get("payer") or {}).get("email")
    amount_cents = data.get("amount")
    if payer_email and amount_cents is not None:
        try:
            amount = Decimal(str(amount_cents)) / Decimal(100)
        except Exception:
            logger.error("Impossible de convertir amount_cents=%r", amount_cents)
            amount = None

        if amount is not None:
            matches = list(
                Adhesion.objects.filter(
                    user__email__iexact=payer_email,
                    montant=amount,
                    statut_paiement=StatutPaiement.EN_ATTENTE,
                ).order_by("created_at")
            )
            if len(matches) == 1:
                logger.info(
                    "Resolved adhesion %s via email+montant (fallback unique)", matches[0].pk
                )
                return matches[0]
            if len(matches) > 1:
                logger.error(
                    "Lookup ambigu: %d adhésions EN_ATTENTE pour %s à %.2f € — webhook non traité. Payload=%s",
                    len(matches), payer_email, amount, payload,
                )
                raise Adhesion.MultipleObjectsReturned(
                    f"{len(matches)} adhésions EN_ATTENTE match pour {payer_email} à {amount} €"
                )

    logger.error("Impossible de résoudre l'adhésion pour ce webhook. Payload=%s", payload)
    raise Adhesion.DoesNotExist("Aucune adhésion ne correspond au webhook")


def _process_payment(payload: dict) -> None:
    data = payload.get("data", {}) or {}
    payer = data.get("payer", {}) or {}

    payment_id = data.get("id")
    payer_email = payer.get("email")
    state = data.get("state")
    metadata = payload.get("metadata") or {}
    order_id = (data.get("order") or {}).get("id") or data.get("orderId")
    payment_receipt_url = data.get("paymentReceiptUrl")

    logger.info(
        "Processing payment: payment_id=%s, email=%s, state=%s",
        payment_id, payer_email, state,
    )

    if not payment_id:
        logger.error("Payload HelloAsso sans payment_id: %s", payload)
        raise ValueError("Payload HelloAsso sans payment_id")

    adhesion = _resolve_adhesion(payload)

    logger.info(
        "Found adhesion %s (user=%s, montant=%.2f €, status=%s)",
        adhesion.pk, adhesion.user.email, adhesion.montant, adhesion.statut_paiement,
    )

    if state in AUTHORIZED_STATES:
        new_status = StatutPaiement.VALIDE
    elif state in REFUNDED_STATES:
        new_status = StatutPaiement.REMBOURSE
    else:
        new_status = StatutPaiement.EN_ATTENTE

    logger.info("Setting adhesion %s status to %s", adhesion.pk, new_status)

    adhesion.helloasso_payment_id = payment_id
    adhesion.helloasso_order_id = str(order_id) if order_id else adhesion.helloasso_order_id
    adhesion.helloasso_webhook_id = str(payment_id)
    adhesion.helloasso_payer_email = payer_email
    adhesion.helloasso_metadata = metadata or adhesion.helloasso_metadata
    adhesion.helloasso_payment_receipt_url = payment_receipt_url or adhesion.helloasso_payment_receipt_url
    adhesion.last_webhook_at = timezone.now()
    adhesion.statut_paiement = new_status
    adhesion.save(
        update_fields=[
            "helloasso_payment_id",
            "helloasso_order_id",
            "helloasso_webhook_id",
            "helloasso_payer_email",
            "helloasso_metadata",
            "helloasso_payment_receipt_url",
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
