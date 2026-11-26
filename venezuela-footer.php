<footer class="site-footer">
  <div class="wrap">
    <p><strong>Alquimia Digital</strong> — Venezuela</p>
    <p>Software original, entrega digital y soporte técnico por correo.</p>
    <p><a href="<?= htmlspecialchars(wa_link('Hola')) ?>" target="_blank">Ventas por WhatsApp: +58 412-5859613</a></p>
    <?php if (ve_support_url() !== ''): ?><p><a href="<?= htmlspecialchars(ve_support_url()) ?>"<?= strpos(ve_support_url(), 'http') === 0 ? ' target="_blank"' : '' ?>>Soporte técnico: <?= htmlspecialchars(ve_support_email() ?: 'abrir un ticket') ?></a></p><?php endif; ?>
    <div class="social-row">
      <a href="https://www.facebook.com/DigitalAlquimiaLatam" target="_blank">Facebook</a>
      <a href="https://www.instagram.com/digital.alquimia" target="_blank">Instagram</a>
    </div>
    <p>© 2026 Alquimia Digital</p>
  </div>
</footer>
