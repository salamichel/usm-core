"""
Customisation du site admin Django : groupement fonctionnel des apps,
masquage des sections techniques allauth/sites.

Appliqué via UsmVollayConfig.ready() → aucun changement dans les admin.py.
"""

from django.contrib import admin


ADMIN_GROUPS = [
    ("Membres",    ["members"]),
    ("Adhésions",  ["adhesions"]),
    ("Club",       ["teams", "seasons"]),
    ("Contenu",    ["content"]),
    ("Accès",      ["auth"]),
]

ADMIN_HIDDEN = {"account", "sites"}


class USMAdminSite(admin.site.__class__):
    site_header = "USM Volley — Administration"
    site_title  = "USM Volley"
    index_title = "Tableau de bord"

    def get_app_list(self, request, app_label=None):
        original = super().get_app_list(request, app_label)

        if app_label is not None:
            return original

        by_label = {app["app_label"]: app for app in original}

        result = []
        seen: set[str] = set()

        for group_name, labels in ADMIN_GROUPS:
            models: list = []
            for lbl in labels:
                if lbl in by_label:
                    models.extend(by_label[lbl]["models"])
                    seen.add(lbl)
            if not models:
                continue
            base = by_label.get(labels[0], {})
            result.append(
                {
                    **base,
                    "name": group_name,
                    "app_label": labels[0],
                    "has_module_perms": True,
                    "models": sorted(models, key=lambda m: m["name"]),
                }
            )

        # Apps hors groupes définis (ni vues, ni masquées)
        for app in original:
            if app["app_label"] not in seen and app["app_label"] not in ADMIN_HIDDEN:
                result.append(app)

        return result


admin.site.__class__ = USMAdminSite
admin.site.site_header = "USM Volley — Administration"
admin.site.site_title  = "USM Volley"
admin.site.index_title = "Tableau de bord"
