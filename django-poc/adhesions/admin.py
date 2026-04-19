from django.contrib import admin

from .models import Adhesion


@admin.register(Adhesion)
class AdhesionAdmin(admin.ModelAdmin):
    list_display = (
        "user",
        "saison",
        "categorie_adhesion",
        "montant",
        "statut_paiement",
        "created_at",
    )
    list_filter = ("statut_paiement", "categorie_adhesion", "saison")
    search_fields = ("user__email", "user__first_name", "user__last_name", "transaction_id")
    autocomplete_fields = ("user", "saison")
    readonly_fields = ("created_at", "updated_at")
