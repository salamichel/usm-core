from django.contrib import admin
from django.contrib.auth.admin import UserAdmin as BaseUserAdmin

from .models import MembreFamille, User


class MembreFamilleInline(admin.TabularInline):
    model = MembreFamille
    extra = 0
    fields = ("first_name", "last_name", "date_of_birth", "gender")
    verbose_name = "Membre de la famille"
    verbose_name_plural = "Membres de la famille"


@admin.register(User)
class UserAdmin(BaseUserAdmin):
    ordering = ("email",)
    list_display = ("email", "first_name", "last_name", "role", "city", "is_active", "nb_famille")
    list_filter = ("role", "gender", "is_active", "is_staff")
    search_fields = ("email", "first_name", "last_name", "phone", "city")
    inlines = [MembreFamilleInline]
    fieldsets = (
        (None, {"fields": ("email", "password")}),
        (
            "Identité",
            {"fields": ("first_name", "last_name", "date_of_birth", "gender")},
        ),
        (
            "Contact",
            {
                "fields": (
                    "phone",
                    "address",
                    "city",
                    "zip_code",
                    "emergency_contact",
                    "emergency_phone",
                )
            },
        ),
        ("Rôle & accès", {"fields": ("role", "is_active", "is_staff", "is_superuser", "groups")}),
        ("Dates", {"fields": ("last_login", "date_joined")}),
    )
    add_fieldsets = (
        (
            None,
            {
                "classes": ("wide",),
                "fields": ("email", "password1", "password2", "first_name", "last_name", "role"),
            },
        ),
    )

    @admin.display(description="Famille")
    def nb_famille(self, obj: User) -> str:
        n = obj.famille.count()
        return f"{n} membre{'s' if n > 1 else ''}" if n else "—"


@admin.register(MembreFamille)
class MembreFamilleAdmin(admin.ModelAdmin):
    list_display = ("first_name", "last_name", "responsable", "date_of_birth", "gender")
    list_filter = ("gender",)
    search_fields = ("first_name", "last_name", "responsable__email", "responsable__last_name")
    autocomplete_fields = ("responsable",)
    readonly_fields = ("age_display",)

    @admin.display(description="Âge")
    def age_display(self, obj: MembreFamille) -> str:
        return f"{obj.age} ans" if obj.age is not None else "—"
