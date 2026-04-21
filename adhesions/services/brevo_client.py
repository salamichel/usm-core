"""Client Brevo : emails transactionnels via l'API v3."""
from __future__ import annotations

import logging

from django.conf import settings
from django.template.loader import render_to_string
from sib_api_v3_sdk.rest import ApiException

logger = logging.getLogger(__name__)


class BrevoError(Exception):
    """Erreur générique Brevo."""


def _get_client():
    """Retourne un client Brevo configuré."""
    from sib_api_v3_sdk import ApiClient, Configuration

    config = Configuration()
    config.api_key["api-key"] = settings.BREVO_API_KEY
    return ApiClient(config)


def _send(*, to_email: str, to_name: str, subject: str, html_content: str) -> None:
    """Envoie un email via l'API Brevo."""
    from sib_api_v3_sdk import SendSmtpEmail, TransactionalEmailsApi

    api_client = _get_client()
    api = TransactionalEmailsApi(api_client)

    email_obj = SendSmtpEmail(
        to=[{"email": to_email, "name": to_name}],
        sender={"email": settings.DEFAULT_FROM_EMAIL, "name": "USM Volley"},
        subject=subject,
        html_content=html_content,
    )

    result = api.send_transac_email(email_obj)
    logger.info(f"Email '{subject}' envoyé à {to_email}: {result}")


def send_adhesion_created(
    email: str,
    first_name: str,
    last_name: str,
    amount: float,
    category: str,
    season: str,
) -> None:
    if not settings.BREVO_API_KEY:
        logger.warning("BREVO_API_KEY non configuré, email non envoyé")
        return

    context = {
        "first_name": first_name,
        "last_name": last_name,
        "amount": f"{amount:.2f}",
        "category": category,
        "season": season,
    }

    try:
        html = render_to_string("emails/adhesion_created.html", context)
        _send(
            to_email=email,
            to_name=f"{first_name} {last_name}",
            subject=f"Votre adhésion USM Volley — {season}",
            html_content=html,
        )
    except ApiException as e:
        logger.error(f"Brevo API error lors de l'envoi à {email}: {e}")
    except Exception as e:
        logger.error(f"Erreur Brevo lors de l'envoi à {email}: {e}")


def send_payment_confirmed(
    email: str,
    first_name: str,
    last_name: str,
    amount: float,
    payment_id: str,
) -> None:
    if not settings.BREVO_API_KEY:
        logger.warning("BREVO_API_KEY non configuré, email non envoyé")
        return

    context = {
        "first_name": first_name,
        "last_name": last_name,
        "amount": f"{amount:.2f}",
        "payment_id": payment_id,
    }

    try:
        html = render_to_string("emails/payment_confirmed.html", context)
        _send(
            to_email=email,
            to_name=f"{first_name} {last_name}",
            subject="Paiement confirmé — USM Volley",
            html_content=html,
        )
    except ApiException as e:
        logger.error(f"Brevo API error lors de l'envoi à {email}: {e}")
    except Exception as e:
        logger.error(f"Erreur Brevo lors de l'envoi à {email}: {e}")
