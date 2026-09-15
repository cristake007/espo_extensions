(function () {
    'use strict';

    const managerClass = 'attendance-management-manager';
    const menuSelector = '#navbar li[data-name="AttendanceOverview"]';
    let currentMenu = null;
    let requestSequence = 0;

    const clearVisibility = () => {
        requestSequence++;
        document.body.classList.remove(managerClass);
    };

    const requestVisibility = () => {
        if (!document.querySelector(menuSelector)) {
            clearVisibility();

            return;
        }

        const sequence = ++requestSequence;

        // Default to private while a new or changed session is being checked.
        document.body.classList.remove(managerClass);

        Espo.Ajax.getRequest('AttendanceManagement/overview/access')
            .then(response => {
                if (sequence !== requestSequence) {
                    return;
                }

                document.body.classList.toggle(managerClass, response.isManager === true);
            })
            .catch(() => {
                if (sequence === requestSequence) {
                    document.body.classList.remove(managerClass);
                }
            });
    };

    const updateVisibility = () => {
        const menu = document.querySelector(menuSelector);

        if (!menu) {
            if (currentMenu !== null || document.body.classList.contains(managerClass)) {
                currentMenu = null;
                clearVisibility();
            }

            return;
        }

        if (menu === currentMenu) {
            return;
        }

        currentMenu = menu;
        requestVisibility();
    };

    const observer = new MutationObserver(updateVisibility);

    observer.observe(document.documentElement, {childList: true, subtree: true});
    window.addEventListener('load', updateVisibility, {once: true});
    window.addEventListener('hashchange', requestVisibility);
    updateVisibility();
})();
