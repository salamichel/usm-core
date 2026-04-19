from django.contrib import admin
from django.contrib.auth.admin import UserAdmin as BaseUserAdmin

from .models import User


@admin.register(User)
class UserAdmin(BaseUserAdmin):
    ordering = ("email",)
    list_display = ("email", "first_name", "last_name", "role", "city", "is_active")
    list_filter = ("role", "gender", "is_active", "is_staff")
    search_fields = ("email", "first_name", "last_name", "phone", "city")
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
