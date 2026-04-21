import logging

from allauth.account.adapter import DefaultAccountAdapter
from django.conf import settings
from django.template.loader import render_to_string

logger = logging.getLogger(__name__)

_TEMPLATE_MAP = {
    "account/email/email_confirmation_signup": (
        "emails/email_confirmation.html",
        "Confirmez votre adresse email — USM Volley",
    ),
    "account/email/email_confirmation": (
        "emails/email_confirmation.html",
        "Confirmez votre adresse email — USM Volley",
    ),
    "account/email/password_reset_by_key": (
        "emails/password_reset.html",
        "Réinitialisation de mot de passe — USM Volley",
    ),
}


class BrevoAccountAdapter(DefaultAccountAdapter):
    def send_mail(self, template_prefix, email, context):
        if not settings.BREVO_API_KEY or template_prefix not in _TEMPLATE_MAP:
            # Pas de clé Brevo (dev) ou type d'email inconnu → comportement par défaut
            super().send_mail(template_prefix, email, context)
            return

        template_name, subject = _TEMPLATE_MAP[template_prefix]
        user = context.get("user")
        to_name = (f"{user.first_name} {user.last_name}".strip() or email) if user else email

        try:
            from adhesions.services.brevo_client import _send
            html = render_to_string(template_name, context)
            _send(to_email=email, to_name=to_name, subject=subject, html_content=html)
        except Exception as e:
            logger.error(f"Brevo error pour {template_prefix} vers {email}: {e}")
