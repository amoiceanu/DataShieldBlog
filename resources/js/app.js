document.addEventListener('DOMContentLoaded', () => {
    const menu = document.querySelector('.menu-toggle');
    const nav = document.querySelector('#navLinks');
    menu?.addEventListener('click', () => {
        const open = nav.classList.toggle('open');
        menu.setAttribute('aria-expanded', String(open));
        menu.setAttribute('aria-label', open ? 'Închide meniul' : 'Deschide meniul');
        menu.innerHTML = `<i class="bi ${open ? 'bi-x-lg' : 'bi-list'}" aria-hidden="true"></i>`;
    });

    const overlay = document.querySelector('.search-overlay');
    const searchInput = document.querySelector('#overlaySearch');
    const openSearch = () => {
        overlay.classList.add('open');
        overlay.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        window.setTimeout(() => searchInput.focus(), 50);
    };
    const closeSearch = () => {
        overlay.classList.remove('open');
        overlay.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    };
    document.querySelectorAll('.search-toggle').forEach(button => button.addEventListener('click', openSearch));
    document.querySelector('.search-close')?.addEventListener('click', closeSearch);
    overlay?.addEventListener('click', event => { if (event.target === overlay) closeSearch(); });
    document.addEventListener('keydown', event => { if (event.key === 'Escape') closeSearch(); });

    const notice = document.querySelector('.cookie-notice');
    if (notice && !localStorage.getItem('datashield-cookie-notice')) notice.hidden = false;
    document.querySelector('.cookie-accept')?.addEventListener('click', () => {
        localStorage.setItem('datashield-cookie-notice', 'seen');
        notice.hidden = true;
    });
});
