<?php
/**
 * HabitFlow - Layout Footer
 */
?>
        <!-- App Content Ends -->
        </div>

        <!-- Footer -->
        <footer class="py-3 px-4 bg-white border-top text-center text-md-start">
            <div class="container-fluid d-flex flex-column flex-md-row justify-content-between align-items-center">
                <div class="text-muted small mb-2 mb-md-0">
                    &copy; <?= date('Y') ?> <strong><?= APP_NAME ?></strong> - <?= APP_TITLE ?>.
                </div>
                <div class="text-muted small">
                    <span class="badge bg-light text-secondary border">XAMPP PHP & MySQL</span>
                    <span class="ms-2">v<?= APP_VERSION ?></span>
                </div>
            </div>
        </footer>
    </main>
</div>

<!-- Bootstrap 5 Bundle with Popper JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>

<!-- Chart.js CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

<!-- HabitFlow Custom Scripts -->
<script src="<?= BASE_URL ?>/assets/js/app.js?v=<?= APP_VERSION ?>"></script>

</body>
</html>
