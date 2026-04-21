from django.urls import path

from .views import PageStatiqueDetailView

urlpatterns = [
    path("<slug:slug>/", PageStatiqueDetailView.as_view(), name="page_detail"),
]
