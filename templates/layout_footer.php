<footer class="site-footer">
    <div class="footer-inner">
        <p class="footer-name"><?php echo htmlspecialchars($footer['company_name'] ?? ''); ?></p>
        <p class="footer-address"><?php echo nl2br(htmlspecialchars($footer['address'] ?? '')); ?></p>
        <?php if (!empty($footer['footer_note'])): ?><p class="footer-note"><?php output_body_text($footer['footer_note']); ?></p><?php endif; ?>
        <p>
            <a href="tel:<?php echo preg_replace('/[^+0-9]/', '', $footer['phone'] ?? ''); ?>"><?php echo htmlspecialchars($footer['phone'] ?? ''); ?></a>
            <br>
            <a href="mailto:<?php echo htmlspecialchars($footer['email'] ?? ''); ?>"><?php echo htmlspecialchars($footer['email'] ?? ''); ?></a>
        </p>
        <p class="footer-legal">
            <a href="<?php echo htmlspecialchars(page_url('impressum')); ?>"><?php echo htmlspecialchars($footer['impressum_label'] ?? 'Impressum'); ?></a>
            &nbsp;·&nbsp;
            <a href="<?php echo htmlspecialchars(page_url('datenschutz')); ?>"><?php echo htmlspecialchars($footer['datenschutz_label'] ?? 'Datenschutz'); ?></a>
        </p>
    </div>
</footer>
<script src="assets/js/site.js"></script>
</body>
</html>
