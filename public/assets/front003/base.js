document.addEventListener('DOMContentLoaded', function () {
  var toggle = document.getElementById('mobile-menu-toggle');
  var menu = document.getElementById('mobile-menu');

  if (toggle && menu) {
    toggle.addEventListener('click', function () {
      var isOpen = !menu.classList.contains('hidden');
      menu.classList.toggle('hidden');
      menu.setAttribute('aria-hidden', isOpen ? 'true' : 'false');
      toggle.setAttribute('aria-expanded', isOpen ? 'false' : 'true');
    });
    menu.querySelectorAll('a').forEach(function (a) {
      a.addEventListener('click', function () {
        menu.classList.add('hidden');
        menu.setAttribute('aria-hidden', 'true');
        toggle.setAttribute('aria-expanded', 'false');
      });
    });
  }

  document.querySelectorAll('[data-submenu-toggle]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var target = document.getElementById(btn.getAttribute('data-submenu-toggle'));
      if (target) {
        var isOpen = !target.classList.contains('hidden');
        target.classList.toggle('hidden');
        btn.setAttribute('aria-expanded', isOpen ? 'false' : 'true');
      }
      var caret = btn.querySelector('.caret');
      if (caret) caret.classList.toggle('rotate-180');
    });
  });

  if (typeof lucide !== 'undefined') { lucide.createIcons(); }
});
