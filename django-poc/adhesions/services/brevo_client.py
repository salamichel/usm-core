"""Client Brevo : emails transactionnels via l'API v3."""
from __future__ import annotations

import logging

from django.conf import settings
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


def send_adhesion_created(
    email: str,
    first_name: str,
    last_name: str,
    amount: float,
    category: str,
    season: str,
) -> None:
    """
    Envoie un email de confirmation d'adhésion créée.

    Args:
        email: Email de l'adhérent
        first_name: Prénom
        last_name: Nom
        amount: Montant en €
        category: Catégorie d'adhésion (ex: "Compétition Volley")
        season: Saison (ex: "2024-2025")
    """
    if not settings.BREVO_API_KEY:
        logger.warning("BREVO_API_KEY non configuré, email non envoyé")
        return

    try:
        from sib_api_v3_sdk import SendSmtpEmail, TransactionalEmailsApi

        api_client = _get_client()
        api = TransactionalEmailsApi(api_client)

        email_obj = SendSmtpEmail(
            to=[{"email": email, "name": f"{first_name} {last_name}"}],
            template_id=int(settings.BREVO_TEMPLATE_ADHESION_CREATED),
            params={
                "first_name": first_name,
                "last_name": last_name,
                "amount": f"{amount:.2f}",
                "category": category,
                "season": season,
            },
        )

        api.send_transac_email(email_obj)
        logger.info(f"Email adhésion créée envoyé à {email}")
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
    """
    Envoie un email de confirmation de paiement.

    Args:
        email: Email de l'adhérent
        first_name: Prénom
        last_name: Nom
        amount: Montant en €
        payment_id: ID du paiement HelloAsso
    """
    if not settings.BREVO_API_KEY:
        logger.warning("BREVO_API_KEY non configuré, email non envoyé")
        return

    try:
        from sib_api_v3_sdk import SendSmtpEmail, TransactionalEmailsApi

        api_client = _get_client()
        api = TransactionalEmailsApi(api_client)

        email_obj = SendSmtpEmail(
            to=[{"email": email, "name": f"{first_name} {last_name}"}],
            template_id=int(settings.BREVO_TEMPLATE_PAYMENT_CONFIRMED),
            params={
                "first_name": first_name,
                "last_name": last_name,
                "amount": f"{amount:.2f}",
                "payment_id": payment_id,
            },
        )

        api.send_transac_email(email_obj)
        logger.info(f"Email paiement confirmé envoyé à {email}")
    except ApiException as e:
        logger.error(f"Brevo API error lors de l'envoi à {email}: {e}")
    except Exception as e:
        logger.error(f"Erreur Brevo lors de l'envoi à {email}: {e}")
