<header class="site-header">
  <div class="wrap nav-row">
    <a class="logo" href="./">
      <img src="imagenes/logo.png" alt="Alquimia Digital">
    </a>
    <nav class="nav-links">
      <a href="./#catalogo">Catálogo</a>
      <a href="./#como-comprar">¿Cómo comprar?</a>
      <a href="./#resenas">Reseñas de Clientes</a>
    </nav>
    <div class="nav-actions">
      <?php $cartN = function_exists('ve_cart_count') ? ve_cart_count() : 0; ?>
      <a class="cart-link" href="venezuela-carrito.php" aria-label="Carrito de compras<?= $cartN ? ' (' . $cartN . ')' : '' ?>">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="9" cy="20" r="1.4"/><circle cx="18" cy="20" r="1.4"/><path d="M2 3h3l2.6 12.2a1.5 1.5 0 0 0 1.5 1.2h8.6a1.5 1.5 0 0 0 1.5-1.1L21 8H6"/></svg>
        <?php if ($cartN): ?><span class="cart-badge"><?= (int) $cartN ?></span><?php endif; ?>
      </a>
      <span class="flag-icon" title="Venezuela">
        <svg width="30" height="20" viewBox="0 0 48 32" xmlns="http://www.w3.org/2000/svg">
          <rect width="48" height="32" rx="5" fill="#00247D"/>
          <rect width="48" height="10.67" rx="5" fill="#FFCC00"/>
          <rect y="10.67" width="48" height="10.67" fill="#00247D"/>
          <path d="M0 27 a5 5 0 0 0 5 5 h38 a5 5 0 0 0 5 -5 v-5.67 h-48 z" fill="#CF142B"/>
          <g fill="#FFFFFF">
            <circle cx="14" cy="15" r="1.3"/><circle cx="18" cy="13" r="1.3"/><circle cx="22" cy="12" r="1.3"/>
            <circle cx="26" cy="12" r="1.3"/><circle cx="30" cy="13" r="1.3"/><circle cx="34" cy="15" r="1.3"/>
          </g>
          <rect width="48" height="32" rx="5" fill="none" stroke="rgba(0,0,0,0.15)" stroke-width="1"/>
        </svg>
      </span>
      <a class="btn-wa" href="<?= htmlspecialchars(wa_link('Hola, quiero más información sobre sus productos')) ?>" target="_blank">
        <span class="wa-led <?= is_business_open() ? 'online' : 'offline' ?>"></span>
        💬 WhatsApp
        <span class="wa-status"><?= is_business_open() ? 'En línea ahora' : 'Fuera de horario' ?></span>
      </a>
    </div>
  </div>
</header>
