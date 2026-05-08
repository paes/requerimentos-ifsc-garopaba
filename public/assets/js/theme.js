(function () {
    function applyTheme(t) {
        document.documentElement.setAttribute('data-theme', t || 'default');
        document.querySelectorAll('.theme-btn').forEach(function (b) {
            b.classList.toggle('active', b.dataset.t === (t || 'default'));
        });
    }

    // Aplica imediatamente para evitar flash de tema errado
    applyTheme(localStorage.getItem('req_theme'));

    document.addEventListener('DOMContentLoaded', function () {
        applyTheme(localStorage.getItem('req_theme'));
        document.querySelectorAll('.theme-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var t = btn.dataset.t;
                localStorage.setItem('req_theme', t);
                applyTheme(t);
            });
        });
    });
})();
