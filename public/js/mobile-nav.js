// User and Department pages on phones: opens and closes the slide-in menu
// (the sidebar). Above 768px the menu button is hidden and nothing changes.
// Styles: ccs/mobile-shell.css.
(function () {
    const body = document.body;
    const openBtn = document.getElementById('mobileMenuBtn');
    const closeBtn = document.getElementById('sidebarCloseBtn');
    const backdrop = document.getElementById('sidebarBackdrop');
    const sidebar = document.getElementById('appSidebar');
    if (!openBtn || !sidebar) return;

    const phone = window.matchMedia('(max-width: 768px)');

    function setOpen(open) {
        body.classList.toggle('sidebar-open', open);
        openBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
        if (open) {
            // Reading the size applies the "visible" style now, so the menu
            // can take focus straight away.
            void sidebar.offsetWidth;
            (sidebar.querySelector('.nav-item.active') || closeBtn || sidebar).focus({ preventScroll: true });
        }
    }

    openBtn.addEventListener('click', () => setOpen(true));
    if (closeBtn) closeBtn.addEventListener('click', () => { setOpen(false); openBtn.focus(); });
    if (backdrop) backdrop.addEventListener('click', () => setOpen(false));

    // Choosing a page closes the menu (the page then loads).
    sidebar.querySelectorAll('.nav-item').forEach(link => link.addEventListener('click', () => setOpen(false)));

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && body.classList.contains('sidebar-open')) {
            setOpen(false);
            openBtn.focus();
        }
    });

    // Rotating or resizing to a wider screen closes it.
    phone.addEventListener('change', (event) => { if (!event.matches) setOpen(false); });
})();
