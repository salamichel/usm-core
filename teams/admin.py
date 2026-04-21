from django.contrib import admin

from .models import EquipeGroupe


@admin.register(EquipeGroupe)
class EquipeGroupeAdmin(admin.ModelAdmin):
    list_display = ("nom", "categorie", "saison", "nb_joueurs")
    list_filter = ("categorie", "saison")
    search_fields = ("nom", "slug")
    prepopulated_fields = {"slug": ("nom",)}
    filter_horizontal = ("joueurs",)
    autocomplete_fields = ("saison",)

    @admin.display(description="Joueurs")
    def nb_joueurs(self, obj: EquipeGroupe) -> int:
        return obj.joueurs.count()
