from django.urls import path

from . import views

urlpatterns = [
    path("", views.dashboard, name="mon_compte"),
    path("profil/", views.profil, name="mon_compte_profil"),
    path("adhesions/", views.mes_adhesions, name="mon_compte_adhesions"),
    path("documents/", views.mes_documents, name="mon_compte_documents"),
    path("documents/<int:pk>/", views.document_download, name="mon_compte_document"),
]
