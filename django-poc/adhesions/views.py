from decimal import Decimal

from django.contrib import messages
from django.db import transaction
from django.http import HttpRequest, HttpResponse
from django.shortcuts import redirect, render

from members.models import MembreFamille, User
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
            pour_membre = data.get("pour_membre_famille")

            with transaction.atomic():
                # Responsable (payeur) — créé ou récupéré par email
                user, _ = User.objects.get_or_create(
                    email=data["email"],
                    defaults={
                        "first_name": data["first_name"],
                        "last_name": data["last_name"],
                        "date_of_birth": data["date_of_birth"],
                        "gender": data["gender"],
                    },
                )

                # Bénéficiaire : le responsable lui-même ou un membre de sa famille
                membre_famille = None
                if pour_membre:
                    membre_famille, _ = MembreFamille.objects.get_or_create(
                        responsable=user,
                        first_name=data["membre_prenom"],
                        last_name=data["membre_nom"],
                        date_of_birth=data["membre_date_naissance"],
                        defaults={"gender": data["membre_genre"]},
                    )

                prefs = {
                    "indisponibilites": data.get("indisponibilites", []),
                    "souhait_equipe_id": souhait.pk if souhait else None,
                    "souhait_equipe_nom": souhait.nom if souhait else "",
                    "coupes_slugs": [c.slug for c in coupes],
                    "coupes_noms": [c.nom for c in coupes],
                }

                if membre_famille:
                    Adhesion.objects.update_or_create(
                        membre_famille=membre_famille,
                        saison=saison,
                        defaults={
                            "user": user,
                            "categorie_adhesion": categorie,
                            "montant": tarif,
                            "preferences": prefs,
                        },
                    )
                else:
                    Adhesion.objects.update_or_create(
                        user=user,
                        saison=saison,
                        membre_famille=None,
                        defaults={
                            "categorie_adhesion": categorie,
                            "montant": tarif,
                            "preferences": prefs,
                        },
                    )

            beneficiaire = f"{data['membre_prenom']} {data['membre_nom']}" if pour_membre else data["first_name"]
            messages.success(
                request,
                f"Adhésion de {beneficiaire} enregistrée — montant à régler : {tarif} €",
            )
            return redirect("adhesion")
    else:
        form = AdhesionForm()

    return render(request, "adhesion/form.html", {"form": form})


def pricing_partial(request: HttpRequest) -> HttpResponse:
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
