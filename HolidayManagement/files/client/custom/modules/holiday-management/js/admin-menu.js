(function () {
    'use strict';

    const fallbackSelector = '#navbar a[data-name="holidayAdministration"]';
    const nativeSelector = '#navbar a[data-name="admin"]';

    const updateVisibility = () => {
        const fallback = document.querySelector(fallbackSelector);

        if (!fallback) {
            return;
        }

        const nativeAdmin = document.querySelector(nativeSelector);
        const menuItem = fallback.closest('li') || fallback;
        const duplicate = Boolean(nativeAdmin);

        menuItem.style.display = duplicate ? 'none' : '';
        fallback.setAttribute('aria-hidden', duplicate ? 'true' : 'false');
    };

    const observer = new MutationObserver(updateVisibility);

    observer.observe(document.documentElement, {childList: true, subtree: true});
    window.addEventListener('load', updateVisibility, {once: true});
    updateVisibility();
})();
