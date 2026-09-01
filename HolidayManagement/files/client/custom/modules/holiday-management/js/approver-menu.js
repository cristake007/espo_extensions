(function () {
    'use strict';

    const approverClass = 'holiday-management-approver';
    const menuSelector = '#navbar a[data-name="holidayApprovals"]';
    let requestStarted = false;

    const updateVisibility = () => {
        if (requestStarted || !document.querySelector(menuSelector)) {
            return;
        }

        requestStarted = true;
        observer.disconnect();

        Espo.Ajax.getRequest('HolidayManagement/approvalQueue')
            .then(response => {
                document.body.classList.toggle(approverClass, response.isApprover === true);
            })
            .catch(() => {
                document.body.classList.remove(approverClass);
            });
    };

    const observer = new MutationObserver(updateVisibility);

    observer.observe(document.documentElement, {childList: true, subtree: true});
    window.addEventListener('load', updateVisibility, {once: true});
    updateVisibility();
})();
