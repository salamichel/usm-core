from django.contrib import messages
from django.db import transaction
from django.http import HttpRequest, HttpResponse
from django.shortcuts import redirect, render

from members.models import User
from teams.models import CategorieAdhesion

from .forms import AdhesionForm
from .models import PRICING, Adhesion


def adhesion_view(request: HttpRequest) -> HttpResponse:
    if request.method == "POST":
        form = AdhesionForm(request.POST)
        if form.is_valid():
            data = form.cleaned_data
            with transaction.atomic():
                user, _ = User.objects.get_or_create(
                    email=data["email"],
                    defaults={
                        "first_name": data["first_name"],
                        "last_name": data["last_name"],
                        "date_of_birth": data["date_of_birth"],
                        "gender": data["gender"],
                    },
                )
                Adhesion.objects.update_or_create(
                    user=user,
                    saison=data["saison"],
                    defaults={
                        "categorie_adhesion": data["categorie_adhesion"],
                        "montant": PRICING[data["categorie_adhesion"]],
                        "preferences": {
                            "indisponibilites": data.get("indisponibilites", []),
                            "souhaits_equipe": data.get("souhaits_equipe", ""),
                            "choix_coupes": data.get("choix_coupes", []),
                        },
                    },
                )
            messages.success(
                request,
                f"Adhésion enregistrée — montant à régler : {PRICING[data['categorie_adhesion']]} €",
            )
            return redirect("adhesion")
    else:
        form = AdhesionForm()

    return render(request, "adhesion/form.html", {"form": form, "pricing": PRICING})


def pricing_partial(request: HttpRequest) -> HttpResponse:
    """HTMX endpoint — retourne le tarif + les champs conditionnels quand la catégorie change."""
    categorie = request.GET.get("categorie_adhesion") or ""
    montant = PRICING.get(categorie)
    show_compet_loisir = categorie == CategorieAdhesion.COMPETLIB
    return render(
        request,
        "adhesion/_pricing_partial.html",
        {
            "categorie": categorie,
            "montant": montant,
            "show_compet_loisir": show_compet_loisir,
        },
    )
