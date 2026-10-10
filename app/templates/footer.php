    </main>

    <footer class="bg-dark text-white py-4 mt-5">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5>Contact</h5>
                    <p class="mb-1">University of the Third Age - U3A</p>
                    <p class="mb-1">c/o Settlers Park, Horton Road, Port Alfred</p>
                    <p class="mb-0"><a href="mailto:info@u3aportalfred.org.za">info@u3aportalfred.org.za</a></p>
                    <p class="mb-0"><a href="mailto:membership@u3aportalfred.org.za">membership@u3aportalfred.org.za</a></p>
                    <p class="mb-0"><a href="mailto:webmaster@u3aportalfred.org.za">webmaster@u3aportalfred.org.za</a></p>
                </div>
                <div class="col-md-6 text-md-end">
                    <p class="mb-0 text-white-50"><?php echo htmlspecialchars(COPYRIGHT); ?> &middot; <?php echo date("Y"); ?></p>
                    <?php if (!empty($nav_is_admin)): ?>
                    <p class="mb-0 mt-2 small text-white-50">
                        Admin info: environment <strong><?php echo htmlspecialchars((string) env("APP_ENV")); ?></strong>,
                        database <strong><?php echo htmlspecialchars((string) env("DB_NAME")); ?></strong>,
                        site <?php echo htmlspecialchars((string) env("SITE_URL")); ?>
                    </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </footer>
    <script src="/js/bootstrap.bundle.min.js"></script>
</body>
</html>
