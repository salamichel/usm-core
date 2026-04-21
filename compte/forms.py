from django import forms

from members.models import User


class ProfilForm(forms.ModelForm):
    class Meta:
        model = User
        fields = (
            "first_name",
            "last_name",
            "date_of_birth",
            "gender",
            "phone",
            "address",
            "city",
            "zip_code",
            "emergency_contact",
            "emergency_phone",
        )
        widgets = {
            "date_of_birth": forms.DateInput(attrs={"type": "date"}, format="%Y-%m-%d"),
        }
        labels = {
            "first_name": "Prénom",
            "last_name": "Nom",
            "date_of_birth": "Date de naissance",
            "gender": "Genre",
            "phone": "Téléphone",
            "address": "Adresse",
            "city": "Ville",
            "zip_code": "Code postal",
            "emergency_contact": "Contact d'urgence",
            "emergency_phone": "Téléphone d'urgence",
        }
