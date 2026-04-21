from django.urls import path

from . import views_webhook

urlpatterns = [
    path("", views_webhook.webhook_helloasso, name="helloasso_webhook"),
]
