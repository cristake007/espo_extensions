(function () {
    'use strict';

    const managerClass = 'attendance-management-manager';
    const menuSelector = '#navbar a[data-name="AttendanceOverview"]';
    let requestStarted = false;

    const updateVisibility = () => {
        if (requestStarted || !document.querySelector(menuSelector)) {
            return;
        }

        requestStarted = true;
        observer.disconnect();

        Espo.Ajax.getRequest('AttendanceManagement/overview/access')
            .then(response => {
                document.body.classList.toggle(managerClass, response.isManager === true);
            })
            .catch(() => {
                document.body.classList.remove(managerClass);
            });
    };

    const observer = new MutationObserver(updateVisibility);

    observer.observe(document.documentElement, {childList: true, subtree: true});
    window.addEventListener('load', updateVisibility, {once: true});
    updateVisibility();
})();
