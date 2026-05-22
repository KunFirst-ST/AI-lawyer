<?php $year = date('Y'); ?>
</main>
<footer class="border-top bg-white py-4 mt-5">
    <div class="container">
        <div class="d-flex flex-column flex-lg-row justify-content-between gap-3 small text-muted">
            <div>
                <div class="fw-semibold text-dark">&copy; <?= e((string) $year) ?> AI Lawyer Matching Platform</div>
                <div>AI วิเคราะห์เบื้องต้น ไม่ใช่คำปรึกษาทางกฎหมายจากทนายโดยตรง</div>
            </div>
            <div class="footer-links d-flex flex-wrap gap-3">
                <a href="<?= e(url('/public/lawyers.php')) ?>">ค้นหาทนาย</a>
                <a href="<?= e(url('/public/faq.php')) ?>">FAQ</a>
                <a href="<?= e(url('/public/contact.php')) ?>">ติดต่อ</a>
                <a href="<?= e(url('/public/privacy.php')) ?>">ความเป็นส่วนตัว</a>
                <a href="<?= e(url('/public/terms.php')) ?>">เงื่อนไข</a>
                <a href="<?= e(url('/public/health.php')) ?>">Health</a>
            </div>
        </div>
    </div>
</footer>
<script src="<?= e(url('/assets/vendor/bootstrap/js/bootstrap.bundle.min.js')) ?>"></script>
<script src="<?= e(url('/assets/js/auth-ui.js')) ?>"></script>
<script src="<?= e(url('/assets/js/admin-ui.js')) ?>"></script>
<script src="<?= e(url('/assets/js/conversation-ui.js')) ?>"></script>
<script src="<?= e(url('/assets/js/call-ui.js')) ?>"></script>
</body>
</html>
