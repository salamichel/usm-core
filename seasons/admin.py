from django.contrib import admin

from .models import Saison


@admin.register(Saison)
class SaisonAdmin(admin.ModelAdmin):
    list_display = ("label", "is_active", "created_at")
    list_filter = ("is_active",)
    search_fields = ("label",)
