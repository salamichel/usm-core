from django.contrib import messages
from django.contrib.auth.decorators import login_required
from django.db.models import Q
from django.http import HttpRequest, HttpResponse, Http404
from django.shortcuts import get_object_or_404, redirect, render

from adhesions.models import Adhesion
from content.models import Document, PermissionDocument
from members.models import Role
from seasons.models import Saison

from .forms import ProfilForm


def _docs_queryset(user):
    """Retourne les documents accessibles à l'utilisateur selon ses permissions."""
    q = Q(permission=PermissionDocument.PUBLIC)

    if user.is_authenticated:
        has_adhesion = user.adhesions.filter(membre_famille__isnull=True).exists()
        is_membre_actif = has_adhesion or user.role in (Role.ENTRAINEUR, Role.BUREAU)

        if is_membre_actif:
            q |= Q(permission=PermissionDocument.ADHERENTS_ONLY)

        # Documents restreints à une équipe dont l'utilisateur fait partie
        q |= Q(
            permission=PermissionDocument.GROUPE_RESTREINT,
            equipe_groupe__joueurs=user,
        )

        if user.role == Role.BUREAU:
            q |= Q(permission=PermissionDocument.BUREAU_ONLY)

    return Document.objects.filter(q).select_related("equipe_groupe").distinct()


@login_required(login_url="account_login")
def dashboard(request: HttpRequest) -> HttpResponse:
    user = request.user
    saison_active = Saison.objects.filter(is_active=True).first()

    # Adhésion de l'utilisateur pour la saison active
    adhesion_active = None
    if saison_active:
        adhesion_active = (
            user.adhesions.filter(saison=saison_active, membre_famille__isnull=True)
            .select_related("saison")
            .first()
        )

    # Adhésions des membres de la famille pour la saison active
    adhesions_famille = []
    if saison_active:
        adhesions_famille = (
            Adhesion.objects.filter(user=user, saison=saison_active)
            .exclude(membre_famille__isnull=True)
            .select_related("membre_famille", "saison")
        )

    # Dernières adhésions (toutes saisons)
    historique = (
        user.adhesions.all()
        .select_related("saison", "membre_famille")
        .order_by("-created_at")[:5]
    )

    return render(
        request,
        "compte/dashboard.html",
        {
            "adhesion_active": adhesion_active,
            "adhesions_famille": adhesions_famille,
            "saison_active": saison_active,
            "membres_famille": user.famille.all(),
            "historique": historique,
        },
    )


@login_required(login_url="account_login")
def profil(request: HttpRequest) -> HttpResponse:
    if request.method == "POST":
        form = ProfilForm(request.POST, instance=request.user)
        if form.is_valid():
            form.save()
            messages.success(request, "Profil mis à jour.")
            return redirect("mon_compte_profil")
    else:
        form = ProfilForm(instance=request.user)

    return render(request, "compte/profil.html", {"form": form})


@login_required(login_url="account_login")
def mes_adhesions(request: HttpRequest) -> HttpResponse:
    user = request.user

    # Toutes les adhésions du responsable (lui-même + famille), triées par saison desc
    adhesions = (
        Adhesion.objects.filter(user=user)
        .select_related("saison", "membre_famille")
        .order_by("-saison__label", "created_at")
    )

    return render(request, "compte/adhesions.html", {"adhesions": adhesions})


@login_required(login_url="account_login")
def mes_documents(request: HttpRequest) -> HttpResponse:
    docs = _docs_queryset(request.user)
    # Regroupement par type pour l'affichage
    docs_by_type: dict = {}
    for doc in docs:
        docs_by_type.setdefault(doc.get_type_display(), []).append(doc)

    return render(request, "compte/documents.html", {"docs_by_type": docs_by_type})


@login_required(login_url="account_login")
def document_download(request: HttpRequest, pk: int) -> HttpResponse:
    """Sert le fichier après vérification des permissions."""
    qs = _docs_queryset(request.user)
    doc = get_object_or_404(qs, pk=pk)

    from django.http import FileResponse
    try:
        return FileResponse(doc.fichier.open("rb"), as_attachment=True, filename=doc.titre)
    except (FileNotFoundError, ValueError):
        raise Http404("Fichier introuvable.")
