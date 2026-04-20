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
    # ── Responsable (payeur) ──────────────────────────────────────────────────
    first_name = forms.CharField(label="Prénom", max_length=150)
    last_name = forms.CharField(label="Nom", max_length=150)
    email = forms.EmailField(label="Email")
    date_of_birth = forms.DateField(
        label="Date de naissance",
        widget=forms.DateInput(attrs={"type": "date"}, format="%Y-%m-%d"),
    )
    gender = forms.ChoiceField(label="Genre", choices=Gender.choices)

    # ── Membre de la famille (optionnel) ─────────────────────────────────────
    pour_membre_famille = forms.BooleanField(
        label="Cette adhésion est pour un membre de la famille",
        required=False,
        widget=forms.CheckboxInput(attrs={"id": "pour_membre_famille"}),
    )
    membre_prenom = forms.CharField(label="Prénom du membre", max_length=150, required=False)
    membre_nom = forms.CharField(label="Nom du membre", max_length=150, required=False)
    membre_date_naissance = forms.DateField(
        label="Date de naissance du membre",
        widget=forms.DateInput(attrs={"type": "date"}, format="%Y-%m-%d"),
        required=False,
    )
    membre_genre = forms.ChoiceField(
        label="Genre du membre",
        choices=[("", "—")] + Gender.choices,
        required=False,
    )

    # ── Saison & catégorie ────────────────────────────────────────────────────
    saison = forms.ModelChoiceField(
        label="Saison",
        queryset=Saison.objects.filter(is_active=True),
    )
    categorie_adhesion = forms.ChoiceField(
        label="Catégorie d'adhésion",
        choices=CategorieAdhesion.choices,
    )

    # ── Préférences ───────────────────────────────────────────────────────────
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

    def __init__(self, *args, user=None, **kwargs):
        super().__init__(*args, **kwargs)

        # Pré-remplir si l'utilisateur est connecté
        if user and not self.is_bound:
            self.fields["first_name"].initial = user.first_name
            self.fields["last_name"].initial = user.last_name
            self.fields["email"].initial = user.email
            self.fields["date_of_birth"].initial = user.date_of_birth
            self.fields["gender"].initial = user.gender

        active = Saison.objects.filter(is_active=True).first()
        if active and not self.is_bound:
            self.fields["saison"].initial = active.pk

        if active:
            self.fields["souhait_equipe"].queryset = EquipeGroupe.objects.filter(
                categorie=CategorieAdhesion.COMPETLIB,
                saison=active,
            )

    def _get_effective_gender(self) -> str:
        """Retourne le genre du bénéficiaire réel (membre ou responsable)."""
        data = self.cleaned_data
        if data.get("pour_membre_famille"):
            return data.get("membre_genre", "")
        return data.get("gender", "")

    def _get_effective_dob(self):
        data = self.cleaned_data
        if data.get("pour_membre_famille"):
            return data.get("membre_date_naissance")
        return data.get("date_of_birth")

    def clean(self):
        cleaned = super().clean()
        pour_membre = cleaned.get("pour_membre_famille")

        # Validation des champs obligatoires si c'est pour un membre
        if pour_membre:
            for field in ("membre_prenom", "membre_nom", "membre_date_naissance", "membre_genre"):
                if not cleaned.get(field):
                    label = self.fields[field].label
                    self.add_error(field, f"Ce champ est obligatoire pour un membre de la famille.")

        categorie = cleaned.get("categorie_adhesion")
        dob = self._get_effective_dob()
        gender = self._get_effective_gender()
        coupes = cleaned.get("choix_coupes") or []

        # Règle : Sans Compétition bloqué si < 15 ans (appliqué au bénéficiaire)
        if categorie == CategorieAdhesion.SANS_COMPETITION and dob:
            today = date.today()
            age = today.year - dob.year - ((today.month, today.day) < (dob.month, dob.day))
            if age < 15:
                raise ValidationError(
                    "La catégorie « Sans Compétition » est réservée aux plus de 15 ans."
                )

        # Règle : genre requis sur coupes — appliqué au bénéficiaire
        for coupe in coupes:
            if coupe.genre_requis == GenreRequis.FEMININ and gender != Gender.FEMININ:
                raise ValidationError(f"La « {coupe.nom} » est réservée aux joueuses.")
            if coupe.genre_requis == GenreRequis.MASCULIN and gender != Gender.MASCULIN:
                raise ValidationError(f"La « {coupe.nom} » est réservée aux joueurs.")

        return cleaned
