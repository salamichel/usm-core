from django.db import migrations


DEFAULT_COUPES = [
    {
        "nom": "Challenge Loisir Mixte",
        "slug": "challenge-loisir-mixte",
        "description": "Compétition mixte loisir",
        "genre_requis": "TOUS",
    },
    {
        "nom": "Coupe Heitz",
        "slug": "coupe-heitz",
        "description": "Filet 2m24 — réservée aux joueuses",
        "genre_requis": "FEMININ",
    },
    {
        "nom": "Coupe Aïco",
        "slug": "coupe-aico",
        "description": "Filet 2m43 — réservée aux joueurs",
        "genre_requis": "MASCULIN",
    },
]


def seed_coupes(apps, schema_editor):
    Coupe = apps.get_model("adhesions", "Coupe")
    for data in DEFAULT_COUPES:
        Coupe.objects.update_or_create(slug=data["slug"], defaults=data)


def unseed_coupes(apps, schema_editor):
    Coupe = apps.get_model("adhesions", "Coupe")
    Coupe.objects.filter(slug__in=[c["slug"] for c in DEFAULT_COUPES]).delete()


class Migration(migrations.Migration):

    dependencies = [
        ("adhesions", "0003_coupe_tarifadhesion"),
    ]

    operations = [
        migrations.RunPython(seed_coupes, reverse_code=unseed_coupes),
    ]
