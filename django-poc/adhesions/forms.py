from datetime import date

from django import forms
from django.core.exceptions import ValidationError

from members.models import Gender, User
from seasons.models import Saison
from teams.models import CategorieAdhesion

INDISPO_CHOICES = [
    ("mardi", "Mardi soir"),
    ("mercredi", "Mercredi soir"),
    ("vendredi", "Vendredi soir"),
]

COUPE_CHOICES = [
    ("challenge_loisir_mixte", "Challenge Loisir Mixte"),
    ("coupe_heitz", "Coupe Heitz (filet 2m24 — Féminin)"),
    ("coupe_aico", "Coupe Aïco (filet 2m43 — Masculin)"),
]

SOUHAIT_EQUIPE_CHOICES = [
    ("", "—"),
    ("L1", "L1"),
    ("L2", "L2"),
    ("L3", "L3"),
    ("L4", "L4"),
    ("INDIFFERENT", "Indifférent"),
]


class AdhesionForm(forms.Form):
    first_name = forms.CharField(label="Prénom", max_length=150)
    last_name = forms.CharField(label="Nom", max_length=150)
    email = forms.EmailField(label="Email")
    date_of_birth = forms.DateField(
        label="Date de naissance",
        widget=forms.DateInput(attrs={"type": "date"}),
    )
    gender = forms.ChoiceField(label="Genre", choices=Gender.choices)

    saison = forms.ModelChoiceField(
        label="Saison",
        queryset=Saison.objects.filter(is_active=True),
    )
    categorie_adhesion = forms.ChoiceField(
        label="Catégorie d'adhésion",
        choices=CategorieAdhesion.choices,
    )

    indisponibilites = forms.MultipleChoiceField(
        label="Indisponibilités",
        choices=INDISPO_CHOICES,
        widget=forms.CheckboxSelectMultiple,
        required=False,
    )
    souhaits_equipe = forms.ChoiceField(
        label="Souhait d'équipe (Compet Loisir uniquement)",
        choices=SOUHAIT_EQUIPE_CHOICES,
        required=False,
    )
    choix_coupes = forms.MultipleChoiceField(
        label="Coupes (Compet Loisir uniquement)",
        choices=COUPE_CHOICES,
        widget=forms.CheckboxSelectMultiple,
        required=False,
    )

    def clean(self):
        cleaned = super().clean()
        categorie = cleaned.get("categorie_adhesion")
        dob = cleaned.get("date_of_birth")
        gender = cleaned.get("gender")
        coupes = cleaned.get("choix_coupes") or []

        # Règle: Sans Compétition bloqué si <15 ans
        if categorie == CategorieAdhesion.SANS_COMPETITION and dob:
            today = date.today()
            age = today.year - dob.year - ((today.month, today.day) < (dob.month, dob.day))
            if age < 15:
                raise ValidationError(
                    "La catégorie « Sans Compétition » est réservée aux plus de 15 ans."
                )

        # Règle: Coupe Heitz = Féminin, Coupe Aïco = Masculin
        if "coupe_heitz" in coupes and gender != Gender.FEMININ:
            raise ValidationError("La Coupe Heitz (filet 2m24) est réservée aux joueuses.")
        if "coupe_aico" in coupes and gender != Gender.MASCULIN:
            raise ValidationError("La Coupe Aïco (filet 2m43) est réservée aux joueurs.")

        return cleaned
