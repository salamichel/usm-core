from django.apps import AppConfig


class UsmVollayConfig(AppConfig):
    default_auto_field = "django.db.models.BigAutoField"
    name = "usm_volley"

    def ready(self):
        import usm_volley.admin_config  # noqa: F401 — apply admin customisations
