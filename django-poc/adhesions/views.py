from decimal import Decimal

from django.contrib import messages
from django.db import transaction
from django.http import HttpRequest, HttpResponse
from django.shortcuts import redirect, render

from members.models import User
from seasons.models import Saison
from teams.models import CategorieAdhesion

from .forms import AdhesionForm
from .models import Adhesion, get_tarif


def adhesion_view(request: HttpRequest) -> HttpResponse:
    if request.method == "POST":
        form = AdhesionForm(request.POST)
        if form.is_valid():
            data = form.cleaned_data
            saison = data["saison"]
            categorie = data["categorie_adhesion"]
            tarif = get_tarif(saison, categorie) or Decimal("0")
            souhait = data.get("souhait_equipe")
            coupes = list(data.get("choix_coupes") or [])

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
                    saison=saison,
                    defaults={
                        "categorie_adhesion": categorie,
                        "montant": tarif,
                        "preferences": {
                            "indisponibilites": data.get("indisponibilites", []),
                            "souhait_equipe_id": souhait.pk if souhait else None,
                            "souhait_equipe_nom": souhait.nom if souhait else "",
                            "coupes_slugs": [c.slug for c in coupes],
                            "coupes_noms": [c.nom for c in coupes],
                        },
                    },
                )
            messages.success(
                request,
                f"Adhésion enregistrée — montant à régler : {tarif} €",
            )
            return redirect("adhesion")
    else:
        form = AdhesionForm()

    return render(request, "adhesion/form.html", {"form": form})


def pricing_partial(request: HttpRequest) -> HttpResponse:
    """Endpoint AJAX : renvoie le tarif + l'indicateur pour les champs Compet Loisir."""
    categorie = request.GET.get("categorie_adhesion") or ""
    saison_id = request.GET.get("saison") or ""
    saison = None
    if saison_id:
        saison = Saison.objects.filter(pk=saison_id).first()
    if saison is None:
        saison = Saison.objects.filter(is_active=True).first()

    montant = get_tarif(saison, categorie) if categorie else None
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
