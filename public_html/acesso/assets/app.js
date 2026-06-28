(function () {
    var banner = document.getElementById('cookieBanner');
    var button = document.getElementById('acceptCookies');
    if (!banner || !button) {
        return;
    }

    if (localStorage.getItem('acesso_cookie_notice') !== 'accepted') {
        banner.classList.add('show');
    }

    button.addEventListener('click', function () {
        localStorage.setItem('acesso_cookie_notice', 'accepted');
        banner.classList.remove('show');
    });
})();

(function () {
    document.addEventListener('submit', function (event) {
        var form = event.target;
        if (!form || !form.querySelector) {
            return;
        }

        var button = form.querySelector('button[type="submit"][data-loading-text]');
        if (!button || button.disabled) {
            return;
        }

        button.dataset.originalText = button.innerHTML;
        button.disabled = true;
        button.innerHTML = '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>' + button.dataset.loadingText;
    });
})();
