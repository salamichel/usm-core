from django.contrib import admin

from .models import Document, Event, Photo, Post


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
