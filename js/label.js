/** SPDX-License-Identifier: AGPL-3.0-or-later */
(() => {
    'use strict';
    const placeButtons = () => {
        const header = document.querySelector('.main-header');
        if (!header) return;
        const buttons = [...document.querySelectorAll('.plugin-barcode-buttons')]
            .find((candidate) => !header.contains(candidate));
        if (!buttons || buttons.closest('.modal')) return;
        // Replace stale buttons when AJAX reloads the asset form.
        header.querySelectorAll('.plugin-barcode-buttons').forEach((old) => old.remove());
        const actions = header.querySelector('#single-action')?.closest('.btn-group');
        if (actions) actions.before(buttons);
        else header.append(buttons);
    };
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', placeButtons);
    } else {
        placeButtons();
    }
    // Tabs can be reloaded without a full page navigation.
    new MutationObserver(placeButtons).observe(document.documentElement, {childList: true, subtree: true});
})();
