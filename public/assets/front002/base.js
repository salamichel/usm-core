document.addEventListener('DOMContentLoaded', function () {

  /* ── Burger / menu mobile ─────────────────────────────────────────────── */
  var toggle = document.getElementById('mobile-menu-toggle');
  var menu   = document.getElementById('mobile-menu');

  if (toggle && menu) {
    toggle.addEventListener('click', function () {
      var isOpen = !menu.classList.contains('hidden');
      menu.classList.toggle('hidden');
      menu.setAttribute('aria-hidden', isOpen ? 'true' : 'false');
      toggle.setAttribute('aria-expanded', isOpen ? 'false' : 'true');
    });

    // Fermer le menu mobile quand on clique sur un lien
    menu.querySelectorAll('a').forEach(function (a) {
      a.addEventListener('click', function () {
        menu.classList.add('hidden');
        menu.setAttribute('aria-hidden', 'true');
        toggle.setAttribute('aria-expanded', 'false');
      });
    });
  }

  /* ── Sous-menus mobiles (accordéons) ─────────────────────────────────── */
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

  /* ── Dropdown utilisateur (desktop) ──────────────────────────────────── */
  var userWrapper = document.getElementById('header-user-wrapper');
  var userToggle  = document.getElementById('member-menu');

  if (userWrapper) {
    var userBtn = document.getElementById('member-menu-toggle');

    function openUserMenu() {
      userWrapper.setAttribute('data-open', '');
      if (userBtn) userBtn.setAttribute('aria-expanded', 'true');
      if (userToggle) userToggle.setAttribute('aria-hidden', 'false');
    }

    function closeUserMenu() {
      userWrapper.removeAttribute('data-open');
      if (userBtn) userBtn.setAttribute('aria-expanded', 'false');
      if (userToggle) userToggle.setAttribute('aria-hidden', 'true');
    }

    function toggleUserMenu() {
      userWrapper.hasAttribute('data-open') ? closeUserMenu() : openUserMenu();
    }

    if (userBtn) {
      userBtn.addEventListener('click', function (e) {
        e.stopPropagation();
        toggleUserMenu();
      });
    }

    // Fermer si clic en dehors
    document.addEventListener('click', function (e) {
      if (userWrapper && !userWrapper.contains(e.target)) {
        closeUserMenu();
      }
    });

    // Fermer à l'appui sur Echap
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') closeUserMenu();
    });
  }

  if (typeof lucide !== 'undefined') { lucide.createIcons(); }
});
