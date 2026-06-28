/*!
 * Color mode toggler for Bootstrap's docs / platform interfaces
 * Standardized for the RENAST Platform with dynamic logo swapping
 */
(() => {
    'use strict';

    const getStoredTheme = () => localStorage.getItem('theme');
    const setStoredTheme = theme => localStorage.setItem('theme', theme);

    const getPreferredTheme = () => {
        const storedTheme = getStoredTheme();
        if (storedTheme) {
            return storedTheme;
        }
        return 'auto';
    };

    const adjustLogoSrc = (img, theme) => {
        const src = img.getAttribute('src');
        if (!src) return;

        let activeTheme = theme;
        if (!activeTheme) {
            const themedAncestor = img.closest('[data-bs-theme]');
            if (themedAncestor) {
                activeTheme = themedAncestor.getAttribute('data-bs-theme');
            }
            
            // If the nearest themed ancestor is 'auto', traverse up to find a specific theme
            if (activeTheme === 'auto' && themedAncestor) {
                let parent = themedAncestor.parentElement;
                while (parent) {
                    const higherAncestor = parent.closest('[data-bs-theme]');
                    if (higherAncestor) {
                        const val = higherAncestor.getAttribute('data-bs-theme');
                        if (val && val !== 'auto') {
                            activeTheme = val;
                            break;
                        }
                        parent = higherAncestor.parentElement;
                    } else {
                        break;
                    }
                }
            }
            
            // Fallback to html theme or system preferences
            if (!activeTheme || activeTheme === 'auto') {
                const docTheme = document.documentElement.getAttribute('data-bs-theme');
                activeTheme = (docTheme && docTheme !== 'auto')
                    ? docTheme
                    : (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
            }
        }

        let newSrc = src;
        if (activeTheme === 'dark') {
            if (src.includes('logo-fundo-claro_horizontal.png')) {
                newSrc = src.replace('logo-fundo-claro_horizontal.png', 'logo-fundo-escuro-horizontal.png');
            } else if (src.includes('logo-fundo-claro-vertical.png')) {
                newSrc = src.replace('logo-fundo-claro-vertical.png', 'logo-fundo-escuro-vertical.png');
            } else if (src.includes('logo-renast-horizontal.png')) {
                newSrc = src.replace('logo-renast-horizontal.png', 'logo-renast-horizontal-dark.png');
            }
        } else {
            if (src.includes('logo-fundo-escuro-horizontal.png')) {
                newSrc = src.replace('logo-fundo-escuro-horizontal.png', 'logo-fundo-claro_horizontal.png');
            } else if (src.includes('logo-fundo-escuro-vertical.png')) {
                newSrc = src.replace('logo-fundo-escuro-vertical.png', 'logo-fundo-claro-vertical.png');
            } else if (src.includes('logo-renast-horizontal-dark.png')) {
                newSrc = src.replace('logo-renast-horizontal-dark.png', 'logo-renast-horizontal.png');
            }
        }

        if (newSrc !== src) {
            img.setAttribute('src', newSrc);
        }
    };

    const updateLogoSrcs = () => {
        document.querySelectorAll('img').forEach(img => adjustLogoSrc(img));
    };

    let currentActiveTheme = 'light';

    const setTheme = theme => {
        let activeTheme = theme;
        if (theme === 'auto') {
            activeTheme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        }
        currentActiveTheme = activeTheme;
        document.documentElement.setAttribute('data-bs-theme', activeTheme);
        
        // Update any already loaded images
        if (document.readyState === 'interactive' || document.readyState === 'complete') {
            updateLogoSrcs(activeTheme);
        }
    };

    // Determine initial theme and apply it immediately to prevent flash
    const preferredTheme = getPreferredTheme();
    currentActiveTheme = preferredTheme === 'auto'
        ? (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light')
        : preferredTheme;

    setTheme(preferredTheme);

    // Watch for newly parsed images to swap their source before they render
    const observer = new MutationObserver((mutations) => {
        mutations.forEach((mutations) => {
            mutations.addedNodes.forEach((node) => {
                if (node.nodeType === Node.ELEMENT_NODE) {
                    if (node.tagName === 'IMG') {
                        adjustLogoSrc(node);
                    } else {
                        node.querySelectorAll('img').forEach(img => adjustLogoSrc(img));
                    }
                }
            });
        });
    });
    observer.observe(document.documentElement, { childList: true, subtree: true });

    const showActiveTheme = (theme) => {
        // Reset active state on all toggle buttons
        document.querySelectorAll('[data-bs-theme-value]').forEach(btn => {
            btn.classList.remove('active');
            btn.setAttribute('aria-pressed', 'false');
            const checkIcon = btn.querySelector('.bi-check2');
            if (checkIcon) checkIcon.classList.add('d-none');
        });

        // Set active state on all buttons matching the theme
        document.querySelectorAll(`[data-bs-theme-value="${theme}"]`).forEach(activeBtn => {
            activeBtn.classList.add('active');
            activeBtn.setAttribute('aria-pressed', 'true');
            const checkIcon = activeBtn.querySelector('.bi-check2');
            if (checkIcon) checkIcon.classList.remove('d-none');
            
            // Find and update the corresponding toggle button's icon in the same navbar
            const dropdownMenu = activeBtn.closest('.dropdown-menu');
            if (dropdownMenu) {
                const themeIcon = dropdownMenu.parentElement.querySelector('.theme-icon-active');
                if (themeIcon) {
                    themeIcon.className = 'theme-icon-active';
                    if (theme === 'light') {
                        themeIcon.classList.add('bi', 'bi-sun-fill');
                    } else if (theme === 'dark') {
                        themeIcon.classList.add('bi', 'bi-moon-stars-fill');
                    } else {
                        themeIcon.classList.add('bi', 'bi-circle-half');
                    }
                }
                
                const themeSwitcher = dropdownMenu.parentElement.querySelector('[id^="bd-theme"]');
                if (themeSwitcher) {
                    themeSwitcher.setAttribute('aria-label', `Alternar tema (${theme})`);
                }
            }
        });
    };

    // Listen to OS theme changes if theme is auto
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
        const storedTheme = getStoredTheme();
        if (storedTheme !== 'light' && storedTheme !== 'dark') {
            setTheme(getPreferredTheme());
            showActiveTheme('auto');
        }
    });

    window.addEventListener('DOMContentLoaded', () => {
        const preferredTheme = getPreferredTheme();
        setTheme(preferredTheme); // Updates logos now that DOM is ready
        showActiveTheme(preferredTheme);

        document.querySelectorAll('[data-bs-theme-value]').forEach(toggle => {
            toggle.addEventListener('click', (e) => {
                e.preventDefault();
                const theme = toggle.getAttribute('data-bs-theme-value');
                setStoredTheme(theme);
                setTheme(theme);
                showActiveTheme(theme);
            });
        });
    });
})();
