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
</body>
</html>
