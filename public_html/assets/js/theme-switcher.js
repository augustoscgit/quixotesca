/*!
 * RENAST light-theme bootstrapper.
 *
 * Dark mode is intentionally disabled during the visual cleanup.
 * Keep this small file as the future hook for a derived dark theme.
 */
(() => {
    'use strict';

    const storageKey = 'renast-theme';
    const legacyStorageKey = 'theme';
    const activeTheme = 'light';

    const forceLightTheme = () => {
        document.documentElement.setAttribute('data-bs-theme', activeTheme);

        try {
            localStorage.setItem(storageKey, activeTheme);
            localStorage.setItem(legacyStorageKey, activeTheme);
        } catch (error) {
            // Storage can be unavailable in strict browser modes; visual theme remains light.
        }
    };

    const preferLightLogo = (img) => {
        const src = img.getAttribute('src');
        if (!src) {
            return;
        }

        let newSrc = src;
        if (src.includes('logo-fundo-escuro-horizontal.png')) {
            newSrc = src.replace('logo-fundo-escuro-horizontal.png', 'logo-fundo-claro_horizontal.png');
        } else if (src.includes('logo-fundo-escuro-vertical.png')) {
            newSrc = src.replace('logo-fundo-escuro-vertical.png', 'logo-fundo-claro-vertical.png');
        } else if (src.includes('logo-renast-horizontal-dark.png')) {
            newSrc = src.replace('logo-renast-horizontal-dark.png', 'logo-renast-horizontal.png');
        }

        if (newSrc !== src) {
            img.setAttribute('src', newSrc);
        }
    };

    const normalizeLogos = (root = document) => {
        root.querySelectorAll('img').forEach(preferLightLogo);
    };

    const disableThemeControls = (root = document) => {
        root.querySelectorAll('[data-bs-theme-value], [id^="bd-theme"], .theme-toggle-btn').forEach((element) => {
            const dropdown = element.closest('.dropdown');
            const target = dropdown || element;
            target.hidden = true;
            target.setAttribute('aria-hidden', 'true');
            target.classList.add('d-none');
        });
    };

    forceLightTheme();

    const observer = new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
            mutation.addedNodes.forEach((node) => {
                if (!(node instanceof Element)) {
                    return;
                }
                if (node.tagName === 'IMG') {
                    preferLightLogo(node);
                }
                normalizeLogos(node);
                disableThemeControls(node);
            });
        });
    });

    observer.observe(document.documentElement, { childList: true, subtree: true });

    window.addEventListener('DOMContentLoaded', () => {
        forceLightTheme();
        normalizeLogos();
        disableThemeControls();
    });
})();
