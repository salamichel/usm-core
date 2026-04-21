"""
Django 5.2 settings for USM Volley POC.

Full-stack (templates + Tailwind) — django-allauth + Django admin.
See /home/user/usm-core/django-poc/README.md for setup.
"""

import os
from pathlib import Path

from dotenv import load_dotenv

BASE_DIR = Path(__file__).resolve().parent.parent
load_dotenv(BASE_DIR / ".env")


SECRET_KEY = os.environ.get(
    "DJANGO_SECRET_KEY",
    "django-insecure-poc-only-do-not-use-in-production",
)
DEBUG = os.environ.get("DJANGO_DEBUG", "1") == "1"
ALLOWED_HOSTS = os.environ.get(
    "DJANGO_ALLOWED_HOSTS",
    "localhost,127.0.0.1,usm.moka-web.net",
).split(",")

CSRF_TRUSTED_ORIGINS = os.environ.get(
    "DJANGO_CSRF_TRUSTED_ORIGINS",
    "https://usm.moka-web.net",
).split(",")


INSTALLED_APPS = [
    "django.contrib.admin",
    "django.contrib.auth",
    "django.contrib.contenttypes",
    "django.contrib.sessions",
    "django.contrib.messages",
    "django.contrib.staticfiles",
    "django.contrib.sites",
    # Third-party
    "allauth",
    "allauth.account",
    "django_htmx",
    "django_ckeditor_5",
    "tailwind",
    "theme",
    "django_browser_reload",
    # Project app (loads admin customisations via AppConfig.ready)
    "usm_volley",
    # Local apps
    "members",
    "seasons",
    "teams",
    "adhesions",
    "content",
    "compte",
]

SITE_ID = 1

MIDDLEWARE = [
    "django.middleware.security.SecurityMiddleware",
    "django.contrib.sessions.middleware.SessionMiddleware",
    "django.middleware.common.CommonMiddleware",
    "django.middleware.csrf.CsrfViewMiddleware",
    "django.contrib.auth.middleware.AuthenticationMiddleware",
    "django.contrib.messages.middleware.MessageMiddleware",
    "django.middleware.clickjacking.XFrameOptionsMiddleware",
    "allauth.account.middleware.AccountMiddleware",
    "django_htmx.middleware.HtmxMiddleware",
    "django_browser_reload.middleware.BrowserReloadMiddleware",
]

ROOT_URLCONF = "usm_volley.urls"

TEMPLATES = [
    {
        "BACKEND": "django.template.backends.django.DjangoTemplates",
        "DIRS": [BASE_DIR / "templates"],
        "APP_DIRS": True,
        "OPTIONS": {
            "context_processors": [
                "django.template.context_processors.request",
                "django.contrib.auth.context_processors.auth",
                "django.contrib.messages.context_processors.messages",
                "content.context_processors.menu",
            ],
        },
    },
]

WSGI_APPLICATION = "usm_volley.wsgi.application"


# Database — PostgreSQL in Docker, SQLite fallback for quick local POC
if os.environ.get("DATABASE_URL"):
    import urllib.parse as up

    url = up.urlparse(os.environ["DATABASE_URL"])
    DATABASES = {
        "default": {
            "ENGINE": "django.db.backends.postgresql",
            "NAME": url.path.lstrip("/"),
            "USER": url.username,
            "PASSWORD": url.password,
            "HOST": url.hostname,
            "PORT": url.port or 5432,
        }
    }
else:
    DATABASES = {
        "default": {
            "ENGINE": "django.db.backends.sqlite3",
            "NAME": BASE_DIR / "db.sqlite3",
        }
    }


AUTH_USER_MODEL = "members.User"

AUTH_PASSWORD_VALIDATORS = [
    {"NAME": "django.contrib.auth.password_validation.UserAttributeSimilarityValidator"},
    {"NAME": "django.contrib.auth.password_validation.MinimumLengthValidator"},
    {"NAME": "django.contrib.auth.password_validation.CommonPasswordValidator"},
    {"NAME": "django.contrib.auth.password_validation.NumericPasswordValidator"},
]

AUTHENTICATION_BACKENDS = [
    "django.contrib.auth.backends.ModelBackend",
    "allauth.account.auth_backends.AuthenticationBackend",
]

# django-allauth — email-based auth, no username
ACCOUNT_USER_MODEL_USERNAME_FIELD = None
ACCOUNT_LOGIN_METHODS = {"email"}
ACCOUNT_SIGNUP_FIELDS = ["email*", "password1*", "password2*"]
ACCOUNT_EMAIL_VERIFICATION = "optional"
ACCOUNT_UNIQUE_EMAIL = True
LOGIN_REDIRECT_URL = "/"
ACCOUNT_LOGOUT_REDIRECT_URL = "/"

# Email — console backend for POC, Brevo SMTP/API swap later
EMAIL_BACKEND = os.environ.get(
    "EMAIL_BACKEND", "django.core.mail.backends.console.EmailBackend"
)
DEFAULT_FROM_EMAIL = os.environ.get("DEFAULT_FROM_EMAIL", "noreply@usmvolley.fr")


LANGUAGE_CODE = "fr-fr"
TIME_ZONE = "Europe/Paris"
USE_I18N = True
USE_TZ = True


STATIC_URL = "static/"
STATIC_ROOT = BASE_DIR / "staticfiles"
STATICFILES_DIRS = [BASE_DIR / "static"]

MEDIA_URL = "media/"
MEDIA_ROOT = BASE_DIR / "media"

DEFAULT_AUTO_FIELD = "django.db.models.BigAutoField"


# django-tailwind — build JIT via Tailwind CLI (plus de CDN en prod)
TAILWIND_APP_NAME = "theme"
INTERNAL_IPS = ["127.0.0.1"]
NPM_BIN_PATH = os.environ.get("NPM_BIN_PATH", "/opt/node22/bin/npm")


# django-ckeditor-5 — éditeur WYSIWYG pour Post.contenu / PageStatique.contenu
CKEDITOR_5_UPLOAD_PATH = "uploads/"
CKEDITOR_5_FILE_STORAGE = "django.core.files.storage.FileSystemStorage"
CKEDITOR_5_FILE_UPLOAD_PERMISSION = "staff"  # seuls les staff peuvent uploader
CKEDITOR_5_CONFIGS = {
    "default": {
        "toolbar": [
            "heading", "|",
            "bold", "italic", "link", "|",
            "bulletedList", "numberedList", "blockQuote", "|",
            "imageUpload", "insertTable", "|",
            "undo", "redo",
        ],
        "image": {
            "toolbar": [
                "imageTextAlternative", "|",
                "imageStyle:alignLeft", "imageStyle:alignRight",
                "imageStyle:alignCenter", "imageStyle:side", "|",
            ],
            "styles": ["full", "side", "alignLeft", "alignRight", "alignCenter"],
        },
        "table": {
            "contentToolbar": [
                "tableColumn", "tableRow", "mergeTableCells",
            ],
        },
    },
}


# HelloAsso — SDK pour appels sortants + secret pour vérifier les webhooks entrants
HELLOASSO_API_HOST = os.environ.get("HELLOASSO_API_HOST", "https://api.helloasso.com/v5")
HELLOASSO_CLIENT_ID = os.environ.get("HELLOASSO_CLIENT_ID", "")
HELLOASSO_CLIENT_SECRET = os.environ.get("HELLOASSO_CLIENT_SECRET", "")
HELLOASSO_WEBHOOK_SECRET = os.environ.get("HELLOASSO_WEBHOOK_SECRET", "dev-webhook-secret")
HELLOASSO_ORGANIZATION_SLUG = os.environ.get("HELLOASSO_ORGANIZATION_SLUG", "usm-volley")

# URL publique HTTPS du site (requis par HelloAsso pour BackUrl/ReturnUrl/ErrorUrl)
# Ex: https://usm.moka-web.net ou un tunnel ngrok en dev
PUBLIC_BASE_URL = os.environ.get("PUBLIC_BASE_URL", "")

# Brevo — Transactional email API
BREVO_API_KEY = os.environ.get("BREVO_API_KEY", "")
# Template IDs: définir dans le dashboard Brevo
BREVO_TEMPLATE_ADHESION_CREATED = os.environ.get("BREVO_TEMPLATE_ADHESION_CREATED", "1")
BREVO_TEMPLATE_PAYMENT_CONFIRMED = os.environ.get("BREVO_TEMPLATE_PAYMENT_CONFIRMED", "2")
