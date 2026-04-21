from django.db import models


class Saison(models.Model):
    label = models.CharField(max_length=20, unique=True, help_text="ex: 2024-2025")
    is_active = models.BooleanField(default=False)
    created_at = models.DateTimeField(auto_now_add=True)
    updated_at = models.DateTimeField(auto_now=True)

    class Meta:
        verbose_name = "Saison"
        verbose_name_plural = "Saisons"
        ordering = ["-label"]

    def __str__(self) -> str:
        return self.label
