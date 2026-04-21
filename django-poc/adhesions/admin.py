from django.contrib import admin
from django.utils.html import format_html

from .models import Adhesion, Coupe, TarifAdhesion


@admin.register(TarifAdhesion)
class TarifAdhesionAdmin(admin.ModelAdmin):
    list_display = ("saison", "categorie", "montant")
    list_filter = ("saison", "categorie")
    list_editable = ("montant",)
    list_display_links = ("saison",)
    autocomplete_fields = ("saison",)


@admin.register(Coupe)
class CoupeAdmin(admin.ModelAdmin):
    list_display = ("nom", "genre_requis", "is_active", "description")
    list_filter = ("genre_requis", "is_active")
    search_fields = ("nom", "slug", "description")
    list_editable = ("is_active",)
    prepopulated_fields = {"slug": ("nom",)}


@admin.register(Adhesion)
class AdhesionAdmin(admin.ModelAdmin):
    list_display = (
        "beneficiaire_nom",
        "user",
        "saison",
        "categorie_adhesion",
        "montant",
        "statut_paiement",
        "helloasso_order_link",
        "created_at",
    )
    list_filter = ("statut_paiement", "categorie_adhesion", "saison")
    search_fields = (
        "user__email",
        "user__first_name",
        "user__last_name",
        "membre_famille__first_name",
        "membre_famille__last_name",
        "transaction_id",
        "helloasso_payment_id",
        "helloasso_order_id",
        "helloasso_checkout_intent_id",
    )
    autocomplete_fields = ("user", "saison", "membre_famille")
    readonly_fields = (
        "created_at",
        "updated_at",
        "preferences_display",
        "helloasso_checkout_intent_id",
        "helloasso_payment_id",
        "helloasso_order_id",
        "helloasso_payer_email",
        "helloasso_webhook_id",
        "helloasso_metadata",
        "last_webhook_at",
        "helloasso_receipt_link",
        "helloasso_admin_link",
    )
    fieldsets = (
        ("Bénéficiaire", {"fields": ("user", "membre_famille", "saison", "categorie_adhesion")}),
        ("Paiement", {"fields": ("montant", "statut_paiement", "transaction_id")}),
        (
            "HelloAsso",
            {
                "fields": (
                    "helloasso_receipt_link",
                    "helloasso_admin_link",
                    "helloasso_checkout_intent_id",
                    "helloasso_payment_id",
                    "helloasso_order_id",
                    "helloasso_payer_email",
                    "helloasso_webhook_id",
                    "last_webhook_at",
                    "helloasso_metadata",
                ),
                "classes": ("collapse",),
            },
        ),
        (
            "Préférences",
            {
                "fields": ("preferences_display", "preferences"),
                "description": "Le bloc formaté est en lecture seule ; le JSON brut reste éditable si nécessaire.",
            },
        ),
        ("Dates", {"fields": ("created_at", "updated_at")}),
    )

    @admin.display(description="Préférences (formaté)")
    def preferences_display(self, obj: Adhesion):
        prefs = obj.preferences or {}
        indispo = prefs.get("indisponibilites") or []
        souhait_nom = prefs.get("souhait_equipe_nom") or "—"
        coupes_noms = prefs.get("coupes_noms") or []
        return format_html(
            "<dl style='margin:0;line-height:1.7'>"
            "<dt><strong>Indisponibilités :</strong></dt><dd>{}</dd>"
            "<dt><strong>Souhait d'équipe :</strong></dt><dd>{}</dd>"
            "<dt><strong>Coupes :</strong></dt><dd>{}</dd>"
            "</dl>",
            ", ".join(indispo) or "—",
            souhait_nom,
            ", ".join(coupes_noms) or "—",
        )

    @admin.display(description="Commande HelloAsso")
    def helloasso_order_link(self, obj: Adhesion):
        url = obj.helloasso_admin_url
        if not url:
            return "—"
        return format_html('<a href="{}" target="_blank">#{}</a>', url, obj.helloasso_order_id)

    @admin.display(description="Attestation de paiement")
    def helloasso_receipt_link(self, obj: Adhesion):
        if not obj.helloasso_payment_receipt_url:
            return "—"
        return format_html(
            '<a href="{}" target="_blank">📄 Voir l\'attestation</a>',
            obj.helloasso_payment_receipt_url,
        )

    @admin.display(description="Admin HelloAsso")
    def helloasso_admin_link(self, obj: Adhesion):
        url = obj.helloasso_admin_url
        if not url:
            return "—"
        return format_html(
            '<a href="{}" target="_blank">🔗 Ouvrir la commande dans HelloAsso</a>',
            url,
        )
