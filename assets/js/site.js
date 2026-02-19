(function () {
    var header = document.getElementById('site-header');
    var toggle = document.querySelector('.nav-toggle');
    var nav = document.querySelector('.nav');

    if (toggle && nav) {
        toggle.addEventListener('click', function () {
            var open = toggle.getAttribute('aria-expanded') === 'true';
            toggle.setAttribute('aria-expanded', !open);
            nav.setAttribute('aria-hidden', open);
        });
        nav.querySelectorAll('a').forEach(function (a) {
            a.addEventListener('click', function () {
                toggle.setAttribute('aria-expanded', 'false');
                nav.setAttribute('aria-hidden', 'true');
            });
        });
    }

    if (header) {
        var hero = document.querySelector('.header-hero');
        if (hero && 'IntersectionObserver' in window) {
            var observer = new IntersectionObserver(
                function (entries) {
                    entries.forEach(function (entry) {
                        if (entry.isIntersecting) {
                            header.classList.add('header-overlay');
                        } else {
                            header.classList.remove('header-overlay');
                        }
                    });
                },
                { root: null, rootMargin: '0px', threshold: 0.1 }
            );
            observer.observe(hero);
        }
    }
})();
