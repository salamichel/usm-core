from django.db import models


class TypeDocument(models.TextChoices):
    FACTURE = "FACTURE", "Facture"
    CERTIFICAT = "CERTIFICAT", "Certificat médical"
    REGLEMENTAIRE = "REGLEMENTAIRE", "Document réglementaire"
    AUTRE = "AUTRE", "Autre"


class PermissionDocument(models.TextChoices):
    PUBLIC = "PUBLIC", "Public"
    GROUPE_RESTREINT = "GROUPE_RESTREINT", "Équipe/Groupe restreint"
    ADHERENTS_ONLY = "ADHERENTS_ONLY", "Adhérents seulement"
    BUREAU_ONLY = "BUREAU_ONLY", "Bureau seulement"


class Document(models.Model):
    titre = models.CharField(max_length=200)
    fichier = models.FileField(upload_to="documents/%Y/%m/")
    type = models.CharField(max_length=20, choices=TypeDocument.choices)
    permission = models.CharField(
        max_length=20,
        choices=PermissionDocument.choices,
        default=PermissionDocument.BUREAU_ONLY,
    )
    equipe_groupe = models.ForeignKey(
        "teams.EquipeGroupe",
        on_delete=models.SET_NULL,
        null=True,
        blank=True,
        related_name="documents",
    )
    created_at = models.DateTimeField(auto_now_add=True)
    updated_at = models.DateTimeField(auto_now=True)

    class Meta:
        verbose_name = "Document"
        verbose_name_plural = "Documents"
        indexes = [
            models.Index(fields=["equipe_groupe"]),
            models.Index(fields=["permission"]),
        ]
        ordering = ["-created_at"]

    def __str__(self) -> str:
        return self.titre


class Post(models.Model):
    titre = models.CharField(max_length=200)
    slug = models.SlugField(max_length=200, unique=True)
    contenu = models.TextField(help_text="HTML CKEditor")
    categorie = models.CharField(max_length=50)
    date_publication = models.DateTimeField()
    created_at = models.DateTimeField(auto_now_add=True)
    updated_at = models.DateTimeField(auto_now=True)

    class Meta:
        verbose_name = "Article de blog"
        verbose_name_plural = "Articles de blog"
        indexes = [
            models.Index(fields=["slug"]),
            models.Index(fields=["categorie"]),
            models.Index(fields=["date_publication"]),
        ]
        ordering = ["-date_publication"]

    def __str__(self) -> str:
        return self.titre


class Event(models.Model):
    titre = models.CharField(max_length=200)
    description = models.TextField(blank=True)
    date_evenement = models.DateTimeField()
    created_at = models.DateTimeField(auto_now_add=True)
    updated_at = models.DateTimeField(auto_now=True)

    class Meta:
        verbose_name = "Événement"
        verbose_name_plural = "Événements"
        indexes = [models.Index(fields=["date_evenement"])]
        ordering = ["-date_evenement"]

    def __str__(self) -> str:
        return self.titre


class Photo(models.Model):
    event = models.ForeignKey(Event, on_delete=models.CASCADE, related_name="photos")
    image = models.ImageField(upload_to="photos/%Y/%m/")
    alt = models.CharField(max_length=200, blank=True)
    tags_equipes = models.JSONField(
        default=list,
        blank=True,
        help_text="Liste de slugs d'équipes (ex: ['m18f','dep'])",
    )
    created_at = models.DateTimeField(auto_now_add=True)

    class Meta:
        verbose_name = "Photo"
        verbose_name_plural = "Photos"
        indexes = [models.Index(fields=["event"])]
        ordering = ["-created_at"]

    def __str__(self) -> str:
        return self.alt or f"Photo #{self.pk}"
