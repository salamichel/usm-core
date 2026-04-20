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
    """
    Une adhésion par bénéficiaire par saison.
    - membre_famille = NULL  →  l'adhérent (user) adhère pour lui-même
    - membre_famille = FK    →  l'adhérent adhère pour un membre de sa famille
      (enfant, conjoint…). Le responsable légal/payeur reste `user`.
    """

    user = models.ForeignKey(
        settings.AUTH_USER_MODEL,
        on_delete=models.CASCADE,
        related_name="adhesions",
        verbose_name="Responsable / payeur",
    )
    membre_famille = models.ForeignKey(
        "members.MembreFamille",
        on_delete=models.CASCADE,
        null=True,
        blank=True,
        related_name="adhesions",
        verbose_name="Membre de la famille (si pas le responsable)",
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

    # HelloAsso — renseignés via webhook
    helloasso_payment_id = models.CharField(max_length=255, null=True, blank=True, unique=True)
    helloasso_order_id = models.CharField(max_length=255, null=True, blank=True)
    helloasso_payer_email = models.EmailField(null=True, blank=True)
    helloasso_webhook_id = models.CharField(max_length=255, null=True, blank=True, unique=True)
    helloasso_metadata = models.JSONField(null=True, blank=True, default=dict)
    last_webhook_at = models.DateTimeField(null=True, blank=True)

    created_at = models.DateTimeField(auto_now_add=True)
    updated_at = models.DateTimeField(auto_now=True)

    class Meta:
        verbose_name = "Adhésion"
        verbose_name_plural = "Adhésions"
        constraints = [
            # Un user ne peut adhérer qu'une fois par saison pour lui-même
            models.UniqueConstraint(
                fields=["user", "saison"],
                condition=models.Q(membre_famille__isnull=True),
                name="unique_adhesion_user_per_season",
            ),
            # Un membre de la famille ne peut avoir qu'une adhésion par saison
            models.UniqueConstraint(
                fields=["membre_famille", "saison"],
                condition=models.Q(membre_famille__isnull=False),
                name="unique_adhesion_membre_per_season",
            ),
        ]
        indexes = [
            models.Index(fields=["user"]),
            models.Index(fields=["saison"]),
            models.Index(fields=["membre_famille"]),
            models.Index(fields=["helloasso_webhook_id"]),
            models.Index(fields=["helloasso_payment_id"]),
        ]

    def __str__(self) -> str:
        beneficiaire = str(self.membre_famille) if self.membre_famille else self.user.get_full_name() or str(self.user)
        return f"{beneficiaire} — {self.saison.label} — {self.get_categorie_adhesion_display()}"

    @property
    def beneficiaire_nom(self) -> str:
        if self.membre_famille:
            return str(self.membre_famille)
        return self.user.get_full_name() or self.user.email

    def save(self, *args, **kwargs):
        if not self.montant:
            tarif = get_tarif(self.saison, self.categorie_adhesion)
            if tarif is not None:
                self.montant = tarif
        super().save(*args, **kwargs)
