from decimal import Decimal

from django.conf import settings
from django.db import models

from teams.models import CategorieAdhesion


# Règles métier CLAUDE.md : Tarification
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


def default_preferences() -> dict:
    return {
        "indisponibilites": [],
        "souhaits_equipe": "",
        "choix_coupes": [],
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
            self.montant = PRICING[self.categorie_adhesion]
        super().save(*args, **kwargs)
