from django.urls import path

from . import views, views_payment

urlpatterns = [
    path("", views.adhesion_view, name="adhesion"),
    path("pricing/", views.pricing_partial, name="adhesion_pricing"),
    path("<int:adhesion_id>/payer/", views_payment.adhesion_payer, name="adhesion_payer"),
    path(
        "<int:adhesion_id>/paiement-retour/",
        views_payment.adhesion_paiement_retour,
        name="adhesion_paiement_retour",
    ),
    path(
        "<int:adhesion_id>/paiement-erreur/",
        views_payment.adhesion_paiement_erreur,
        name="adhesion_paiement_erreur",
    ),
]
