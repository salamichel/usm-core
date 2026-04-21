from django.utils import timezone
from django.views.generic import DetailView, ListView

from .models import PageStatique, Post


class PostListView(ListView):
    model = Post
    template_name = "blog/post_list.html"
    context_object_name = "posts"
    paginate_by = 10

    def get_queryset(self):
        return Post.objects.filter(date_publication__lte=timezone.now())


class PostDetailView(DetailView):
    model = Post
    template_name = "blog/post_detail.html"
    context_object_name = "post"

    def get_queryset(self):
        return Post.objects.filter(date_publication__lte=timezone.now())


class PageStatiqueDetailView(DetailView):
    model = PageStatique
    template_name = "pages/page_detail.html"
    context_object_name = "page"

    def get_queryset(self):
        return PageStatique.objects.filter(is_published=True)
