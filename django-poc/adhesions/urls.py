from django.urls import path

from . import views

urlpatterns = [
    path("", views.adhesion_view, name="adhesion"),
    path("pricing/", views.pricing_partial, name="adhesion_pricing"),
]
