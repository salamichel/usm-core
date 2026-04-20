from .models import MenuItem


def menu(request):
    """Expose les entrées de menu racine (+ enfants prefetchés) à tous les templates."""
    roots = (
        MenuItem.objects
        .filter(is_active=True, parent__isnull=True)
        .prefetch_related("children")
        .order_by("ordre", "id")
    )
    return {"menu_items": roots}
