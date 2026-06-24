</main>
<footer class="site-footer">
    <div class="container">
        <div class="footer-col">
            <h4>FreshMart</h4>
            <ul>
                <li><a href="<?= url('/') ?>">Home</a></li>
                <li><a href="<?= url('/shop/browse.php') ?>">Browse Products</a></li>
            </ul>
        </div>
        <div class="footer-col">
            <h4>For Retailers</h4>
            <ul>
                <li><a href="<?= url('/become-retailer.php') ?>">Become a Retailer</a></li>
                <li><a href="<?= url('/auth/login.php') ?>">Retailer Login</a></li>
            </ul>
        </div>
        <div class="footer-col">
            <h4>Learn More</h4>
            <ul>
                <li><a href="<?= url('/shop/freshness.php') ?>">About Freshness Levels</a></li>
            </ul>
        </div>
    </div>
    <div class="container">
        <div class="footer-bottom">
            &copy; <?= date('Y') ?> FreshMart &middot; Final Year Project &middot; Made in Malaysia 🇲🇾
        </div>
    </div>
</footer>

<script>
/* Recommendation carousel: arrow scrolling + edge/empty state.
   Works for any number of .reco-carousel-wrap blocks on the page. */
(function () {
    function setup(wrap) {
        var car  = wrap.querySelector('.reco-carousel');
        var prev = wrap.querySelector('.reco-arrow[data-reco-dir="prev"]');
        var next = wrap.querySelector('.reco-arrow[data-reco-dir="next"]');
        if (!car) return;

        function update() {
            var max = car.scrollWidth - car.clientWidth;
            var scrollable = max > 4;
            wrap.classList.toggle('reco-no-scroll', !scrollable);
            wrap.classList.toggle('reco-end', !scrollable || car.scrollLeft >= max - 2);
            if (prev) prev.hidden = !scrollable || car.scrollLeft <= 2;
            if (next) next.hidden = !scrollable || car.scrollLeft >= max - 2;
        }
        function page(dir) {
            car.scrollBy({ left: dir * car.clientWidth * 0.8, behavior: 'smooth' });
        }
        if (prev) prev.addEventListener('click', function () { page(-1); });
        if (next) next.addEventListener('click', function () { page(1); });
        car.addEventListener('scroll', update, { passive: true });
        window.addEventListener('resize', update);
        update();
    }
    function init() {
        document.querySelectorAll('.reco-carousel-wrap').forEach(setup);
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
</script>
<script>
/* Mobile slide-in navigation: open/close + body scroll lock. */
(function () {
    var drawer   = document.querySelector('.mobile-nav');
    var backdrop = document.querySelector('.mobile-nav-backdrop');
    var openBtn  = document.querySelector('[data-mobile-open]');
    if (!drawer || !backdrop) return;

    function open() {
        drawer.classList.add('open');
        backdrop.classList.add('open');
        document.body.style.overflow = 'hidden';
        if (openBtn) openBtn.setAttribute('aria-expanded', 'true');
    }
    function close() {
        drawer.classList.remove('open');
        backdrop.classList.remove('open');
        document.body.style.overflow = '';
        if (openBtn) openBtn.setAttribute('aria-expanded', 'false');
    }
    if (openBtn) openBtn.addEventListener('click', open);
    document.querySelectorAll('[data-mobile-close]').forEach(function (el) {
        el.addEventListener('click', close);
    });
    // close when a drawer link is tapped, or on Escape
    drawer.querySelectorAll('a').forEach(function (a) { a.addEventListener('click', close); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') close(); });
})();
</script>
<script>
/* Reveal-on-scroll: fade/slide elements with .reveal into view. */
(function () {
    var els = document.querySelectorAll('.reveal');
    if (!els.length) return;
    if (!('IntersectionObserver' in window)) {
        els.forEach(function (e) { e.classList.add('in'); });
        return;
    }
    var io = new IntersectionObserver(function (entries) {
        entries.forEach(function (en) {
            if (en.isIntersecting) { en.target.classList.add('in'); io.unobserve(en.target); }
        });
    }, { threshold: 0.12 });
    els.forEach(function (e) { io.observe(e); });
})();
</script>
</body>
</html>
