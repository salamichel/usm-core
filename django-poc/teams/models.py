from django.conf import settings
from django.db import models


class CategorieAdhesion(models.TextChoices):
    SANS_COMPETITION = "SANS_COMPETITION", "Sans compétition"
    COMPETITION_VOLLEY = "COMPETITION_VOLLEY", "Compétition Volley"
    COMPETLIB = "COMPETLIB", "Compétition Loisir"
    COMPETITION_DEP = "COMPETITION_DEP", "Compétition DEP"


class EquipeGroupe(models.Model):
    nom = models.CharField(max_length=50, help_text="ex: M18F, DEP, CompetLib")
    slug = models.SlugField(max_length=50, unique=True)
    categorie = models.CharField(max_length=20, choices=CategorieAdhesion.choices)
    saison = models.ForeignKey(
        "seasons.Saison",
        on_delete=models.CASCADE,
        related_name="equipes",
    )
    description = models.TextField(blank=True)
    joueurs = models.ManyToManyField(
        settings.AUTH_USER_MODEL,
        related_name="equipes",
        blank=True,
    )
    created_at = models.DateTimeField(auto_now_add=True)
    updated_at = models.DateTimeField(auto_now=True)

    class Meta:
        verbose_name = "Équipe / Groupe"
        verbose_name_plural = "Équipes / Groupes"
        indexes = [
            models.Index(fields=["saison"]),
            models.Index(fields=["slug"]),
        ]
        ordering = ["saison__label", "nom"]

    def __str__(self) -> str:
        return f"{self.nom} ({self.saison.label})"
