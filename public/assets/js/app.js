(() => {
    const menuToggle = document.querySelector('.app-menu-toggle');
    const menuOverlay = document.querySelector('.app-sidebar-overlay');
    const sidebar = document.querySelector('.app-sidebar');
    const printReceipt = document.getElementById('print-receipt');
    const sidebarLinks = document.querySelectorAll('.app-sidebar a[href]');
    const sidebarScrollStorageKey = 'app-sidebar-scroll-top';
    document.querySelectorAll('form[action*="/logout"]').forEach((form) => {
        form.addEventListener('submit', () => sessionStorage.removeItem(sidebarScrollStorageKey));
    });
    document.querySelectorAll('form[data-confirm]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (! window.confirm(form.dataset.confirm)) event.preventDefault();
        });
    });
    const mobileSidebar = window.matchMedia('(max-width: 900px)');
    const setMenuOpen = (isOpen) => {
        const showMobileSidebar = mobileSidebar.matches && isOpen;
        document.documentElement.classList.toggle('sidebar-open', showMobileSidebar);
        menuToggle?.setAttribute('aria-expanded', String(isOpen));
        menuToggle?.setAttribute('aria-label', isOpen ? 'Tutup menu' : 'Buka menu');
        sidebar?.setAttribute('aria-hidden', String(mobileSidebar.matches && !isOpen));
        if (sidebar) sidebar.inert = mobileSidebar.matches && !isOpen;
        menuOverlay?.setAttribute('aria-hidden', String(!showMobileSidebar));
    };
    setMenuOpen(false);
    const savedSidebarScrollTop = sessionStorage.getItem(sidebarScrollStorageKey);
    const navigationEntry = performance.getEntriesByType('navigation')[0];
    if (navigationEntry?.type === 'reload') {
        sessionStorage.removeItem(sidebarScrollStorageKey);
        if (sidebar) sidebar.scrollTop = 0;
    } else if (sidebar && savedSidebarScrollTop !== null) {
        sidebar.scrollTop = Number(savedSidebarScrollTop);
    }
    menuToggle?.addEventListener('click', () => {
        setMenuOpen(menuToggle.getAttribute('aria-expanded') !== 'true');
    });
    menuOverlay?.addEventListener('click', () => setMenuOpen(false));
    sidebarLinks.forEach((link) => link.addEventListener('click', () => {
        if (sidebar) sessionStorage.setItem(sidebarScrollStorageKey, String(sidebar.scrollTop));
        setMenuOpen(false);
    }));
    mobileSidebar.addEventListener('change', () => setMenuOpen(false));
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') setMenuOpen(false);
    });
    printReceipt?.addEventListener('click', () => window.print());
})();
