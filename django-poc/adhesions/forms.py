from datetime import date

from django import forms
from django.core.exceptions import ValidationError

from members.models import Gender
from seasons.models import Saison
from teams.models import CategorieAdhesion, EquipeGroupe

from .models import Coupe, GenreRequis

INDISPO_CHOICES = [
    ("Mardi soir", "Mardi soir"),
    ("Mercredi soir", "Mercredi soir"),
    ("Vendredi soir", "Vendredi soir"),
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
    souhait_equipe = forms.ModelChoiceField(
        label="Souhait d'équipe (Compet Loisir uniquement)",
        queryset=EquipeGroupe.objects.none(),
        required=False,
        empty_label="— Indifférent —",
    )
    choix_coupes = forms.ModelMultipleChoiceField(
        label="Coupes (Compet Loisir uniquement)",
        queryset=Coupe.objects.filter(is_active=True),
        widget=forms.CheckboxSelectMultiple,
        required=False,
    )

    def __init__(self, *args, **kwargs):
        super().__init__(*args, **kwargs)

        # Présélection de la saison active
        active = Saison.objects.filter(is_active=True).first()
        if active and not self.is_bound:
            self.fields["saison"].initial = active.pk

        # Souhait d'équipe : équipes Compet Loisir de la saison active
        if active:
            self.fields["souhait_equipe"].queryset = EquipeGroupe.objects.filter(
                categorie=CategorieAdhesion.COMPETLIB,
                saison=active,
            )

    def clean(self):
        cleaned = super().clean()
        categorie = cleaned.get("categorie_adhesion")
        dob = cleaned.get("date_of_birth")
        gender = cleaned.get("gender")
        coupes = cleaned.get("choix_coupes") or []

        # Règle : Sans Compétition bloqué si < 15 ans
        if categorie == CategorieAdhesion.SANS_COMPETITION and dob:
            today = date.today()
            age = today.year - dob.year - ((today.month, today.day) < (dob.month, dob.day))
            if age < 15:
                raise ValidationError(
                    "La catégorie « Sans Compétition » est réservée aux plus de 15 ans."
                )

        # Règle : genre requis sur coupes (administré via Coupe.genre_requis)
        for coupe in coupes:
            if coupe.genre_requis == GenreRequis.FEMININ and gender != Gender.FEMININ:
                raise ValidationError(f"La « {coupe.nom} » est réservée aux joueuses.")
            if coupe.genre_requis == GenreRequis.MASCULIN and gender != Gender.MASCULIN:
                raise ValidationError(f"La « {coupe.nom} » est réservée aux joueurs.")

        return cleaned
