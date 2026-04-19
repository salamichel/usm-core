from datetime import date

from django.contrib.auth.models import AbstractUser, BaseUserManager
from django.db import models


class Gender(models.TextChoices):
    MASCULIN = "MASCULIN", "Masculin"
    FEMININ = "FEMININ", "Féminin"
    AUTRE = "AUTRE", "Autre"


class Role(models.TextChoices):
    ADHERENT = "ADHERENT", "Adhérent"
    ENTRAINEUR = "ENTRAINEUR", "Entraîneur"
    BUREAU = "BUREAU", "Bureau"


class UserManager(BaseUserManager):
    use_in_migrations = True

    def _create_user(self, email, password, **extra):
        if not email:
            raise ValueError("Email is required")
        email = self.normalize_email(email)
        user = self.model(email=email, **extra)
        user.set_password(password)
        user.save(using=self._db)
        return user

    def create_user(self, email, password=None, **extra):
        extra.setdefault("is_staff", False)
        extra.setdefault("is_superuser", False)
        return self._create_user(email, password, **extra)

    def create_superuser(self, email, password=None, **extra):
        extra.setdefault("is_staff", True)
        extra.setdefault("is_superuser", True)
        extra.setdefault("role", Role.BUREAU)
        return self._create_user(email, password, **extra)


class User(AbstractUser):
    username = None
    email = models.EmailField("email", unique=True)

    date_of_birth = models.DateField(null=True, blank=True)
    gender = models.CharField(max_length=10, choices=Gender.choices, default=Gender.AUTRE)
    phone = models.CharField(max_length=20, blank=True)
    address = models.CharField(max_length=255, blank=True)
    city = models.CharField(max_length=100, blank=True)
    zip_code = models.CharField(max_length=10, blank=True)
    emergency_contact = models.CharField(max_length=100, blank=True)
    emergency_phone = models.CharField(max_length=20, blank=True)
    role = models.CharField(max_length=12, choices=Role.choices, default=Role.ADHERENT)

    USERNAME_FIELD = "email"
    REQUIRED_FIELDS = []

    objects = UserManager()

    def __str__(self) -> str:
        return f"{self.first_name} {self.last_name} <{self.email}>".strip()

    @property
    def age(self) -> int | None:
        if not self.date_of_birth:
            return None
        today = date.today()
        return (
            today.year
            - self.date_of_birth.year
            - ((today.month, today.day) < (self.date_of_birth.month, self.date_of_birth.day))
        )
