/* ==========================================
   🍔 NAV-TOGGLE — Toggle del sidebar móvil (SPEC-0041)
   JS vainilla, sin librerías. Solo manipula clases CSS.
   ========================================== */

(function () {
    'use strict';

    function initNavToggle() {
        var toggle = document.querySelector('.ns-nav__toggle');
        var backdrop = document.querySelector('.ns-nav__backdrop');
        var nav = document.querySelector('.ns-nav');

        if (!toggle || !nav) {
            return;
        }

        function openNav() {
            document.body.classList.add('ns-nav--open');
            toggle.classList.add('is-open');
            toggle.setAttribute('aria-expanded', 'true');
        }

        function closeNav() {
            document.body.classList.remove('ns-nav--open');
            toggle.classList.remove('is-open');
            toggle.setAttribute('aria-expanded', 'false');
        }

        function isOpen() {
            return document.body.classList.contains('ns-nav--open');
        }

        toggle.addEventListener('click', function () {
            if (isOpen()) {
                closeNav();
            } else {
                openNav();
            }
        });

        if (backdrop) {
            backdrop.addEventListener('click', closeNav);
        }

        // Cerrar al pulsar Escape
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && isOpen()) {
                closeNav();
            }
        });

        // Cerrar al navegar (click en un link del nav)
        var links = nav.querySelectorAll('.ns-nav__link');
        for (var i = 0; i < links.length; i++) {
            links[i].addEventListener('click', function () {
                if (window.matchMedia('(max-width: 767px)').matches) {
                    closeNav();
                }
            });
        }

        // Al redimensionar de móvil a desktop, resetear el estado
        window.addEventListener('resize', function () {
            if (window.innerWidth >= 768 && isOpen()) {
                closeNav();
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initNavToggle);
    } else {
        initNavToggle();
    }
})();
