from django.db import migrations


LE_CLUB_HTML = """
<p>Le <strong>USM Volley</strong> est le club de volleyball de l'Union Salles Mios Volley Ball.</p>
<p>Nous accueillons tous les niveaux, des loisirs à la compétition, pour enfants et adultes.</p>
<p><em>Cette page peut être éditée depuis l'administration.</em></p>
"""

HORAIRES_HTML = """
<h2>Créneaux d'entraînement</h2>
<ul>
  <li><strong>Mardi</strong> 20h00 – 22h00 — Seniors Compétition</li>
  <li><strong>Mercredi</strong> 18h00 – 20h00 — Jeunes (M13 / M15 / M18)</li>
  <li><strong>Vendredi</strong> 20h00 – 22h00 — Compet Loisir</li>
</ul>
<p><em>Modifiable depuis l'administration.</em></p>
"""


def seed(apps, schema_editor):
    PageStatique = apps.get_model("content", "PageStatique")
    MenuItem = apps.get_model("content", "MenuItem")

    # Pages statiques
    le_club, _ = PageStatique.objects.get_or_create(
        slug="le-club",
        defaults={"titre": "Le Club", "contenu": LE_CLUB_HTML, "is_published": True},
    )
    horaires, _ = PageStatique.objects.get_or_create(
        slug="horaires",
        defaults={"titre": "Horaires", "contenu": HORAIRES_HTML, "is_published": True},
    )

    # Menu racine
    infos, _ = MenuItem.objects.get_or_create(
        label="Infos pratiques",
        defaults={"ordre": 10, "link_type": "NONE", "link_value": "", "is_active": True},
    )
    MenuItem.objects.get_or_create(
        label="Le Club",
        parent=infos,
        defaults={"ordre": 10, "link_type": "PAGE", "link_value": "le-club", "is_active": True},
    )
    MenuItem.objects.get_or_create(
        label="Horaires",
        parent=infos,
        defaults={"ordre": 20, "link_type": "PAGE", "link_value": "horaires", "is_active": True},
    )
    MenuItem.objects.get_or_create(
        label="Blog",
        defaults={"ordre": 20, "link_type": "ROUTE", "link_value": "blog_list", "is_active": True},
    )
    MenuItem.objects.get_or_create(
        label="Adhésion",
        defaults={"ordre": 30, "link_type": "URL", "link_value": "/adhesion/", "is_active": True},
    )


def unseed(apps, schema_editor):
    PageStatique = apps.get_model("content", "PageStatique")
    MenuItem = apps.get_model("content", "MenuItem")

    MenuItem.objects.filter(
        label__in=["Infos pratiques", "Le Club", "Horaires", "Blog", "Adhésion"]
    ).delete()
    PageStatique.objects.filter(slug__in=["le-club", "horaires"]).delete()


class Migration(migrations.Migration):
    dependencies = [
        ("content", "0003_alter_post_categorie_alter_post_contenu_menuitem_and_more"),
    ]

    operations = [
        migrations.RunPython(seed, unseed),
    ]
