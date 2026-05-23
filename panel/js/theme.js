(function () {
    'use strict';

    var COLOR_KEY = 'faoxima_color';
    var THEME_KEY = 'faoxima_theme';
    var DEFAULT_COLOR = 'blue';
    var DEFAULT_THEME = 'dark';
    var ALLOWED_COLORS = ['red', 'blue', 'purple', 'yellow', 'orange', 'green'];


    try {
        if (!localStorage.getItem(COLOR_KEY)) {
            if (legacyColor) { localStorage.setItem(COLOR_KEY, legacyColor); }
        }
        if (!localStorage.getItem(THEME_KEY)) {
            if (legacyTheme) { localStorage.setItem(THEME_KEY, legacyTheme); }
        }
    } catch (e) {  }

    function applyColor(color) {
        if (ALLOWED_COLORS.indexOf(color) === -1) color = DEFAULT_COLOR;
        document.documentElement.setAttribute('data-color', color);
        try { localStorage.setItem(COLOR_KEY, color); } catch (e) {}

        var swatches = document.querySelectorAll('.swatch');
        for (var i = 0; i < swatches.length; i++) {
            if (swatches[i].getAttribute('data-color') === color) {
                swatches[i].classList.add('active');
            } else {
                swatches[i].classList.remove('active');
            }
        }

        try {
            document.dispatchEvent(new CustomEvent('faoxima:themechange', { detail: { color: color } }));
        } catch (e) {}
    }


    var SVG_MOON = '<svg class="svg-icon svg-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>';
    var SVG_SUN  = '<svg class="svg-icon svg-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>';

    function applyTheme(theme) {
        if (theme !== 'light' && theme !== 'dark') theme = DEFAULT_THEME;
        document.documentElement.setAttribute('data-theme', theme);
        try { localStorage.setItem(THEME_KEY, theme); } catch (e) {}
        var icon = document.getElementById('theme-toggle-icon');
        var label = document.getElementById('theme-toggle-label');
        if (icon) {
            icon.innerHTML = (theme === 'light') ? SVG_SUN : SVG_MOON;
        }
        if (label) {
            label.textContent = theme === 'light' ? 'حالت شب' : 'حالت روز';
        }

        try {
            document.dispatchEvent(new CustomEvent('faoxima:themechange', { detail: { theme: theme } }));
        } catch (e) {}
    }


    var savedColor = DEFAULT_COLOR;
    var savedTheme = DEFAULT_THEME;
    try {
        savedColor = localStorage.getItem(COLOR_KEY) || DEFAULT_COLOR;
        savedTheme = localStorage.getItem(THEME_KEY) || DEFAULT_THEME;
    } catch (e) {}
    document.documentElement.setAttribute('data-color', savedColor);
    document.documentElement.setAttribute('data-theme', savedTheme);


    window.FaoximaTheme = {
        setColor: applyColor,
        setTheme: applyTheme,
        toggleTheme: function () {
            var current = document.documentElement.getAttribute('data-theme') || DEFAULT_THEME;
            applyTheme(current === 'light' ? 'dark' : 'light');
        }
    };


    function ready() {
        applyColor(savedColor);
        applyTheme(savedTheme);


        var swatches = document.querySelectorAll('.swatch');
        for (var i = 0; i < swatches.length; i++) {
            (function (sw) {
                sw.addEventListener('click', function (ev) {
                    ev.stopPropagation();
                    applyColor(sw.getAttribute('data-color'));
                });
            })(swatches[i]);
        }


        var profileWrap = document.querySelector('.profile-wrap');
        if (profileWrap) {
            var trigger = profileWrap.querySelector('.profile-trigger');
            if (trigger) {
                trigger.addEventListener('click', function (ev) {
                    ev.stopPropagation();
                    profileWrap.classList.toggle('open');
                });
            }
            document.addEventListener('click', function (ev) {
                if (!profileWrap.contains(ev.target)) profileWrap.classList.remove('open');
            });

            var menu = profileWrap.querySelector('.profile-menu');
            if (menu) {
                menu.addEventListener('click', function (ev) {
                    if (ev.target.closest('.swatch') || ev.target.closest('.no-close')) {
                        ev.stopPropagation();
                    }
                });
            }
        }


        var toggleBtn = document.getElementById('sidebar-toggle');
        if (toggleBtn) {
            toggleBtn.addEventListener('click', function () {
                if (window.innerWidth <= 992) {
                    document.body.classList.toggle('sidebar-open');
                } else {
                    document.body.classList.toggle('sidebar-collapsed');
                }
            });
        }
        var overlay = document.querySelector('.sidebar-overlay');
        if (overlay) {
            overlay.addEventListener('click', function () {
                document.body.classList.remove('sidebar-open');
            });
        }


        var path = (location.pathname.split('/').pop() || 'index.php').toLowerCase();
        var links = document.querySelectorAll('.sidebar-menu a');
        for (var j = 0; j < links.length; j++) {
            var href = (links[j].getAttribute('href') || '').toLowerCase();
            if (!href) continue;
            if (path === href ||
                (path.indexOf('useredit') !== -1 && href.indexOf('users.php') !== -1) ||
                (path === 'user.php' && href === 'users.php') ||
                (path === 'productedit.php' && href === 'product.php')) {
                links[j].classList.add('active');
            }
        }


        var themeToggleBtn = document.getElementById('theme-toggle');
        if (themeToggleBtn) {
            themeToggleBtn.addEventListener('click', function (ev) {
                ev.stopPropagation();
                window.FaoximaTheme.toggleTheme();
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', ready);
    } else {
        ready();
    }
})();


window.openModal = function (id) {
    var m = document.getElementById(id);
    if (!m) return;
    m.style.display = 'flex';
    setTimeout(function () { m.classList.add('active'); }, 10);
};
window.closeModal = function (id) {
    var m = document.getElementById(id);
    if (!m) return;
    m.classList.remove('active');
    setTimeout(function () { m.style.display = 'none'; }, 250);
};
document.addEventListener('click', function (ev) {
    if (ev.target.classList && ev.target.classList.contains('modal-overlay')) {
        ev.target.classList.remove('active');
        setTimeout(function () { ev.target.style.display = 'none'; }, 250);
    }
});

