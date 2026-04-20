from decimal import Decimal
from typing import Optional

from django.conf import settings
from django.db import models

from teams.models import CategorieAdhesion


# Règles métier CLAUDE.md : Tarification — utilisée en fallback si pas de TarifAdhesion en base.
PRICING: dict[str, Decimal] = {
    CategorieAdhesion.SANS_COMPETITION: Decimal("60.00"),
    CategorieAdhesion.COMPETITION_VOLLEY: Decimal("100.00"),
    CategorieAdhesion.COMPETLIB: Decimal("100.00"),
    CategorieAdhesion.COMPETITION_DEP: Decimal("150.00"),
}


class StatutPaiement(models.TextChoices):
    EN_ATTENTE = "EN_ATTENTE", "En attente"
    VALIDE = "VALIDE", "Validé"
    REMBOURSE = "REMBOURSE", "Remboursé"


class GenreRequis(models.TextChoices):
    TOUS = "TOUS", "Tous"
    MASCULIN = "MASCULIN", "Masculin uniquement"
    FEMININ = "FEMININ", "Féminin uniquement"


class TarifAdhesion(models.Model):
    """Tarif par (saison, catégorie), administrable depuis le back-office."""

    saison = models.ForeignKey(
        "seasons.Saison",
        on_delete=models.CASCADE,
        related_name="tarifs",
    )
    categorie = models.CharField(max_length=20, choices=CategorieAdhesion.choices)
    montant = models.DecimalField(max_digits=10, decimal_places=2)

    class Meta:
        verbose_name = "Tarif d'adhésion"
        verbose_name_plural = "Tarifs d'adhésion"
        constraints = [
            models.UniqueConstraint(
                fields=["saison", "categorie"],
                name="unique_tarif_per_saison_categorie",
            )
        ]
        ordering = ["saison__label", "categorie"]

    def __str__(self) -> str:
        return f"{self.saison.label} — {self.get_categorie_display()} : {self.montant} €"


class Coupe(models.Model):
    """Coupes administrables (alimentent les choix Compet Loisir)."""

    nom = models.CharField(max_length=100)
    slug = models.SlugField(max_length=100, unique=True)
    description = models.CharField(max_length=255, blank=True)
    genre_requis = models.CharField(
        max_length=10,
        choices=GenreRequis.choices,
        default=GenreRequis.TOUS,
        help_text="Restriction éventuelle sur le genre du joueur",
    )
    is_active = models.BooleanField(default=True)
    created_at = models.DateTimeField(auto_now_add=True)

    class Meta:
        verbose_name = "Coupe"
        verbose_name_plural = "Coupes"
        ordering = ["nom"]

    def __str__(self) -> str:
        return self.nom


def get_tarif(saison, categorie: str) -> Optional[Decimal]:
    """Retourne le tarif pour (saison, catégorie) — DB d'abord, fallback PRICING."""
    if saison is not None:
        try:
            return TarifAdhesion.objects.get(saison=saison, categorie=categorie).montant
        except TarifAdhesion.DoesNotExist:
            pass
    return PRICING.get(categorie)


def default_preferences() -> dict:
    return {
        "indisponibilites": [],
        "souhait_equipe_id": None,
        "souhait_equipe_nom": "",
        "coupes_slugs": [],
        "coupes_noms": [],
    }


class Adhesion(models.Model):
    user = models.ForeignKey(
        settings.AUTH_USER_MODEL,
        on_delete=models.CASCADE,
        related_name="adhesions",
    )
    saison = models.ForeignKey(
        "seasons.Saison",
        on_delete=models.CASCADE,
        related_name="adhesions",
    )
    categorie_adhesion = models.CharField(
        max_length=20,
        choices=CategorieAdhesion.choices,
    )
    montant = models.DecimalField(max_digits=10, decimal_places=2)
    statut_paiement = models.CharField(
        max_length=12,
        choices=StatutPaiement.choices,
        default=StatutPaiement.EN_ATTENTE,
    )
    transaction_id = models.CharField(max_length=100, blank=True)
    preferences = models.JSONField(default=default_preferences, blank=True)
    created_at = models.DateTimeField(auto_now_add=True)
    updated_at = models.DateTimeField(auto_now=True)

    class Meta:
        verbose_name = "Adhésion"
        verbose_name_plural = "Adhésions"
        constraints = [
            models.UniqueConstraint(
                fields=["user", "saison"],
                name="unique_adhesion_per_season",
            )
        ]
        indexes = [
            models.Index(fields=["user"]),
            models.Index(fields=["saison"]),
        ]

    def __str__(self) -> str:
        return f"{self.user} — {self.saison.label} — {self.get_categorie_adhesion_display()}"

    def save(self, *args, **kwargs):
        if not self.montant:
            tarif = get_tarif(self.saison, self.categorie_adhesion)
            if tarif is not None:
                self.montant = tarif
        super().save(*args, **kwargs)
