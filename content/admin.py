from django.contrib import admin

from .models import Document, Event, MenuItem, PageStatique, Photo, Post


@admin.register(Document)
class DocumentAdmin(admin.ModelAdmin):
    list_display = ("titre", "type", "permission", "equipe_groupe", "created_at")
    list_filter = ("type", "permission", "equipe_groupe")
    search_fields = ("titre",)


@admin.register(Post)
class PostAdmin(admin.ModelAdmin):
    list_display = ("titre", "categorie", "date_publication")
    list_filter = ("categorie",)
    search_fields = ("titre", "contenu")
    prepopulated_fields = {"slug": ("titre",)}
    date_hierarchy = "date_publication"


class PhotoInline(admin.TabularInline):
    model = Photo
    extra = 1


@admin.register(Event)
class EventAdmin(admin.ModelAdmin):
    list_display = ("titre", "date_evenement")
    search_fields = ("titre", "description")
    date_hierarchy = "date_evenement"
    inlines = [PhotoInline]


@admin.register(Photo)
class PhotoAdmin(admin.ModelAdmin):
    list_display = ("__str__", "event", "created_at")
    list_filter = ("event",)


@admin.register(PageStatique)
class PageStatiqueAdmin(admin.ModelAdmin):
    list_display = ("titre", "slug", "is_published", "updated_at")
    list_filter = ("is_published",)
    search_fields = ("titre", "contenu")
    prepopulated_fields = {"slug": ("titre",)}


class MenuItemInline(admin.TabularInline):
    model = MenuItem
    fk_name = "parent"
    extra = 0
    fields = ("label", "link_type", "link_value", "ordre", "is_active")
    ordering = ("ordre",)
    verbose_name = "Sous-entrée"
    verbose_name_plural = "Sous-entrées (2e niveau)"


@admin.register(MenuItem)
class MenuItemAdmin(admin.ModelAdmin):
    list_display = ("label", "parent", "link_type", "link_value", "ordre", "is_active")
    list_editable = ("ordre", "is_active")
    list_filter = ("is_active", "link_type", "parent")
    search_fields = ("label", "link_value")
    ordering = ("parent__ordre", "ordre")
    inlines = [MenuItemInline]

    def get_inline_instances(self, request, obj=None):
        # N'afficher l'inline que pour les items racine (pas de parent).
        if obj and obj.parent_id:
            return []
        return super().get_inline_instances(request, obj)
