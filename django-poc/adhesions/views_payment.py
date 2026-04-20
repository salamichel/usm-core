"""Vues pour déclencher et gérer le paiement HelloAsso d'une adhésion."""
import logging

from django.contrib import messages
from django.contrib.auth.decorators import login_required
from django.http import HttpRequest, HttpResponse, HttpResponseRedirect
from django.shortcuts import get_object_or_404, render
from django.urls import reverse
from django.views.decorators.http import require_http_methods

from .models import Adhesion, StatutPaiement
from .services.helloasso_client import (
    HelloAssoError,
    HelloAssoNotConfigured,
    create_checkout_intent,
)

logger = logging.getLogger(__name__)


@login_required(login_url="account_login")
@require_http_methods(["POST"])
def adhesion_payer(request: HttpRequest, adhesion_id: int) -> HttpResponse:
    """Crée un checkout intent HelloAsso et redirige l'utilisateur vers la page de paiement."""
    adhesion = get_object_or_404(Adhesion, pk=adhesion_id, user=request.user)

    if adhesion.statut_paiement == StatutPaiement.VALIDE:
        messages.info(request, "Cette adhésion est déjà payée.")
        return HttpResponseRedirect(reverse("mon_compte_adhesions"))

    beneficiaire = adhesion.beneficiaire_nom
    item_name = f"Adhésion {beneficiaire} — {adhesion.saison.label}"

    base = f"{request.scheme}://{request.get_host()}"
    back_url = base + reverse("mon_compte_adhesions")
    return_url = base + reverse("adhesion_paiement_retour", args=[adhesion.pk])
    error_url = base + reverse("adhesion_paiement_erreur", args=[adhesion.pk])

    try:
        intent = create_checkout_intent(
            amount=adhesion.montant,
            item_name=item_name,
            back_url=back_url,
            return_url=return_url,
            error_url=error_url,
            payer_email=request.user.email,
            payer_first_name=request.user.first_name,
            payer_last_name=request.user.last_name,
            metadata={"adhesion_id": adhesion.pk, "user_id": request.user.pk},
        )
    except HelloAssoNotConfigured as e:
        logger.error("HelloAsso non configuré: %s", e)
        messages.error(
            request,
            "Le paiement en ligne n'est pas encore configuré. Contactez le bureau.",
        )
        return HttpResponseRedirect(reverse("mon_compte_adhesions"))
    except HelloAssoError as e:
        logger.exception("HelloAsso checkout échoué pour adhesion %s: %s", adhesion.pk, e)
        messages.error(
            request,
            "Impossible d'initier le paiement HelloAsso. Réessayez plus tard.",
        )
        return HttpResponseRedirect(reverse("mon_compte_adhesions"))

    # Persiste l'order_id pour corrélation (le payment_id sera mis à jour via webhook)
    adhesion.helloasso_order_id = intent.id
    adhesion.save(update_fields=["helloasso_order_id", "updated_at"])

    return HttpResponseRedirect(intent.redirect_url)


@login_required(login_url="account_login")
def adhesion_paiement_retour(request: HttpRequest, adhesion_id: int) -> HttpResponse:
    """Page de retour après paiement HelloAsso réussi (statut final via webhook)."""
    adhesion = get_object_or_404(Adhesion, pk=adhesion_id, user=request.user)
    return render(request, "adhesion/paiement_retour.html", {"adhesion": adhesion})


@login_required(login_url="account_login")
def adhesion_paiement_erreur(request: HttpRequest, adhesion_id: int) -> HttpResponse:
    """Page affichée si HelloAsso renvoie une erreur de paiement."""
    adhesion = get_object_or_404(Adhesion, pk=adhesion_id, user=request.user)
    return render(request, "adhesion/paiement_erreur.html", {"adhesion": adhesion})
