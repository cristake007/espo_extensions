define('tuvtk-theme:views/site/navbar', ['views/site/navbar', 'ui', 'jquery'], function (NavbarView, Ui, $) {
    'use strict';

    return class extends NavbarView {
        afterRender() {
            this.destroyTuvtkCollapsedTooltips();
            super.afterRender();

            if (this.getThemeManager().getName() !== 'Tuvtk' || !this.isSide()) {
                return;
            }

            this.initTuvtkCollapsedTooltips();
        }

        switchMinimizer() {
            this.hideTuvtkCollapsedTooltips();
            super.switchMinimizer();
        }

        initTuvtkCollapsedTooltips() {
            this.tuvtkCollapsedTooltipList = [];

            this.element
                .querySelectorAll('ul.tabs > li:not(.tab-divider) > a')
                .forEach(anchor => {
                    const labelElement = anchor.querySelector(':scope > .short-label[title]');

                    if (!labelElement) {
                        return;
                    }

                    const text = labelElement.getAttribute('title');

                    if (!text) {
                        return;
                    }

                    labelElement.removeAttribute('title');
                    anchor.setAttribute('aria-label', text);

                    const $anchor = $(anchor);
                    const markTooltip = () => {
                        const id = anchor.getAttribute('aria-describedby');
                        const tooltipElement = id ? document.getElementById(id) : null;

                        if (tooltipElement) {
                            tooltipElement.classList.add('tuvtk-navbar-tooltip');
                            tooltipElement.setAttribute('role', 'tooltip');
                        }
                    };

                    const popover = Ui.popover(anchor, {
                        placement: 'right',
                        container: 'body',
                        text: text,
                        noToggleInit: true,
                    }, this);

                    $anchor.on('inserted.bs.popover.tuvtk-navbar', markTooltip);

                    const show = () => {
                        if (!document.body.classList.contains('minimized') ||
                            document.body.classList.contains('side-menu-opened')) {
                            return;
                        }

                        popover.show();
                        markTooltip();
                    };
                    const hide = () => popover.hide();

                    anchor.addEventListener('mouseenter', show);
                    anchor.addEventListener('mouseleave', hide);
                    anchor.addEventListener('focusin', show);
                    anchor.addEventListener('focusout', hide);

                    this.tuvtkCollapsedTooltipList.push({
                        anchor,
                        labelElement,
                        $anchor,
                        popover,
                        markTooltip,
                        show,
                        hide,
                        text,
                    });
                });
        }

        hideTuvtkCollapsedTooltips() {
            (this.tuvtkCollapsedTooltipList || []).forEach(item => item.popover.hide());
        }

        destroyTuvtkCollapsedTooltips() {
            (this.tuvtkCollapsedTooltipList || []).forEach(item => {
                item.anchor.removeEventListener('mouseenter', item.show);
                item.anchor.removeEventListener('mouseleave', item.hide);
                item.anchor.removeEventListener('focusin', item.show);
                item.anchor.removeEventListener('focusout', item.hide);
                item.$anchor.off('.tuvtk-navbar');
                item.labelElement.setAttribute('title', item.text);
                item.popover.destroy();
            });

            this.tuvtkCollapsedTooltipList = [];
        }
    };
});
