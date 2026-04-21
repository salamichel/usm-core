from django.core.exceptions import ValidationError
from django.db import models
from django.urls import NoReverseMatch, reverse
from django_ckeditor_5.fields import CKEditor5Field


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
    contenu = CKEditor5Field("Contenu", config_name="default")
    categorie = models.CharField(max_length=50, blank=True)
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

    def get_absolute_url(self) -> str:
        return reverse("blog_detail", args=[self.slug])


class PageStatique(models.Model):
    titre = models.CharField("titre", max_length=200)
    slug = models.SlugField("slug", max_length=200, unique=True)
    contenu = CKEditor5Field("Contenu", config_name="default")
    is_published = models.BooleanField("publiée", default=False)
    created_at = models.DateTimeField(auto_now_add=True)
    updated_at = models.DateTimeField(auto_now=True)

    class Meta:
        verbose_name = "Page statique"
        verbose_name_plural = "Pages statiques"
        ordering = ["titre"]
        indexes = [
            models.Index(fields=["slug"]),
            models.Index(fields=["is_published"]),
        ]

    def __str__(self) -> str:
        return self.titre

    def get_absolute_url(self) -> str:
        return reverse("page_detail", args=[self.slug])


class MenuLinkType(models.TextChoices):
    PAGE = "PAGE", "Page statique"
    ROUTE = "ROUTE", "Route nommée"
    URL = "URL", "URL brute"
    NONE = "NONE", "Aucun (parent seulement)"


class MenuItem(models.Model):
    label = models.CharField("label", max_length=80)
    parent = models.ForeignKey(
        "self",
        null=True,
        blank=True,
        on_delete=models.CASCADE,
        related_name="children",
        verbose_name="parent",
        help_text="Attention : la suppression d'un parent supprime ses enfants.",
    )
    ordre = models.PositiveIntegerField("ordre", default=0)
    is_active = models.BooleanField("actif", default=True)
    link_type = models.CharField(
        "type de lien",
        max_length=10,
        choices=MenuLinkType.choices,
        default=MenuLinkType.NONE,
    )
    link_value = models.CharField(
        "valeur du lien",
        max_length=255,
        blank=True,
        help_text=(
            "PAGE : slug de la page statique • "
            "ROUTE : nom de route Django (ex: blog_list) • "
            "URL : URL brute (ex: /adhesion/) • "
            "NONE : laisser vide"
        ),
    )

    class Meta:
        verbose_name = "Entrée de menu"
        verbose_name_plural = "Menu — entrées"
        ordering = ["parent__ordre", "parent_id", "ordre", "id"]

    def __str__(self) -> str:
        return f"{self.parent.label} › {self.label}" if self.parent_id else self.label

    def get_url(self) -> str:
        if self.link_type == MenuLinkType.PAGE and self.link_value:
            return reverse("page_detail", args=[self.link_value])
        if self.link_type == MenuLinkType.ROUTE and self.link_value:
            try:
                return reverse(self.link_value)
            except NoReverseMatch:
                return "#"
        if self.link_type == MenuLinkType.URL:
            return self.link_value or "#"
        return "#"

    def clean(self):
        # Max 2 niveaux : un enfant ne peut pas avoir un parent lui-même enfant.
        if self.parent_id and self.parent and self.parent.parent_id:
            raise ValidationError(
                "Profondeur maximale du menu : 2 niveaux. "
                "Cet item ne peut pas être rattaché à un sous-menu."
            )

        if self.link_type == MenuLinkType.PAGE:
            if not self.link_value:
                raise ValidationError({"link_value": "Indiquez le slug de la page statique."})
            if not PageStatique.objects.filter(slug=self.link_value).exists():
                raise ValidationError(
                    {"link_value": f"Aucune page statique avec le slug « {self.link_value} »."}
                )
        elif self.link_type == MenuLinkType.ROUTE:
            if not self.link_value:
                raise ValidationError({"link_value": "Indiquez le nom de la route."})
            try:
                reverse(self.link_value)
            except NoReverseMatch:
                raise ValidationError(
                    {"link_value": f"La route « {self.link_value} » n'existe pas."}
                )
        elif self.link_type == MenuLinkType.URL and not self.link_value:
            raise ValidationError({"link_value": "Indiquez l'URL."})


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
