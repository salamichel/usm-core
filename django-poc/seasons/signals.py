from django.db.models.signals import pre_save
from django.dispatch import receiver

from .models import Saison


@receiver(pre_save, sender=Saison)
def deactivate_other_seasons(sender, instance, **kwargs):
    """Si is_active=True, désactiver toutes les autres saisons."""
    if instance.is_active:
        # Désactiver toutes les saisons sauf celle-ci
        Saison.objects.exclude(pk=instance.pk).update(is_active=False)
