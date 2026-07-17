define('generator-perioade-cursuri:views/shared/record-ui', [], function () {
    function escapeHtml(value) {
        return String(value === null || value === undefined ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function ensureRecordRegion(element, name) {
        if (!element || typeof element.querySelector !== 'function') {
            return null;
        }

        const existing = element.querySelector('[data-name="' + name + '"]');

        if (existing) {
            return existing;
        }

        const ownerDocument = element.ownerDocument ||
            (typeof document !== 'undefined' ? document : null);

        if (!ownerDocument || typeof ownerDocument.createElement !== 'function') {
            return null;
        }

        const region = ownerDocument.createElement('div');
        const record = element.querySelector('.record') || element;

        region.dataset.name = name;
        record.appendChild(region);

        return region;
    }

    function setActionButtonState(element, action, disabled, disabledTitle) {
        if (!element || typeof element.querySelector !== 'function') {
            return false;
        }

        const button = element.querySelector('[data-action="' + action + '"]');

        if (!button) {
            return false;
        }

        const isDisabled = !!disabled;

        button.disabled = isDisabled;
        button.classList.toggle('disabled', isDisabled);
        button.title = isDisabled ? disabledTitle || '' : '';

        return true;
    }

    function synchronizeHorizontalScroll(container, topSelector, mainSelector) {
        if (!container || typeof container.querySelector !== 'function') {
            return false;
        }

        const topScroller = container.querySelector(topSelector);
        const mainScroller = container.querySelector(mainSelector);
        const table = mainScroller && typeof mainScroller.querySelector === 'function' ?
            mainScroller.querySelector('table') : null;
        const topInner = topScroller ? topScroller.firstElementChild : null;

        if (!topScroller || !mainScroller || !table || !topInner ||
            typeof topScroller.addEventListener !== 'function' ||
            typeof mainScroller.addEventListener !== 'function') {
            return false;
        }

        topInner.style.width = table.scrollWidth + 'px';
        topScroller.addEventListener('scroll', () => {
            mainScroller.scrollLeft = topScroller.scrollLeft;
        });
        mainScroller.addEventListener('scroll', () => {
            topScroller.scrollLeft = mainScroller.scrollLeft;
        });

        return true;
    }

    return {
        escapeHtml,
        ensureRecordRegion,
        setActionButtonState,
        synchronizeHorizontalScroll,
    };
});
