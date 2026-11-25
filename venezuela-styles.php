<style>
  :root {
    --bg: #FAF7F0; --surface: #FFFFFF; --surface-tint: #F1ECDD;
    --gold: #B9862A; --gold-bright: #D4A03E; --teal: #2A7D74;
    --ash: #7A7566; --line: rgba(28,26,20,0.10);
    --text: #211E17; --text-dim: #5C5749; --radius-card: 14px;
    --serif: 'Outfit', -apple-system, sans-serif;
    --sans: 'Manrope', -apple-system, sans-serif;
    --dark: #3E5C76; --dark-2: #4C7093;
    --text-on-dark: #F2EFE6; --text-on-dark-dim: #C9D6E0;
    --line-on-dark: rgba(245,241,230,0.18);
  }
  * { box-sizing: border-box; }
  html { scroll-behavior: smooth; }
  body { margin: 0; background: var(--bg); color: var(--text); font-family: var(--sans); line-height: 1.5; }
  a { color: inherit; } img { max-width: 100%; display: block; }
  .wrap { max-width: 1100px; margin: 0 auto; padding: 0 24px; }

  /* header */
  header.site-header { position: sticky; top: 0; z-index: 50; background: var(--dark); }
  .nav-row { display: flex; align-items: center; justify-content: space-between; padding: 14px 0; gap: 16px; }
  .nav-links { display: flex; align-items: center; gap: 24px; }
  .nav-links a { text-decoration: none; color: var(--text-on-dark-dim); font-size: 0.88rem; transition: color 0.15s ease; }
  .nav-links a:hover { color: var(--text-on-dark); }
  .logo { display: flex; align-items: center; gap: 8px; text-decoration: none; }
  .logo img { height: 40px; width: auto; }
  .flag-icon { display: flex; align-items: center; }
  .nav-actions { display: flex; align-items: center; gap: 14px; }
  .btn-wa {
    background: #25D366; color: #fff; text-decoration: none; padding: 9px 16px;
    border-radius: 20px; font-size: 0.85rem; font-weight: 600; display: flex; align-items: center; gap: 7px;
  }
  .btn-wa:hover { background: #1EBE59; }
  .wa-led { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
  .wa-led.online { background: #B6FFCF; animation: wa-pulse 1.8s infinite; }
  .wa-led.offline { background: rgba(255,255,255,0.5); }
  .wa-status { font-size: 0.72rem; font-weight: 500; opacity: 0.9; border-left: 1px solid rgba(255,255,255,0.4); padding-left: 7px; margin-left: 2px; }
  @keyframes wa-pulse {
    0% { box-shadow: 0 0 0 0 rgba(182,255,207,0.7); }
    70% { box-shadow: 0 0 0 6px rgba(182,255,207,0); }
    100% { box-shadow: 0 0 0 0 rgba(182,255,207,0); }
  }

  /* banner */
  .ve-banner { width: 100%; padding: 24px 0 0; background: var(--bg); }
  .ve-banner-inner { max-width: 70%; margin: 0 auto; border-radius: 14px; overflow: hidden; line-height: 0; }
  .ve-banner img { width: 100%; height: auto; }

  /* whatsapp strip */
  .wa-strip { background: var(--surface-tint); border-bottom: 1px solid var(--line); padding: 14px 0; }
  .wa-strip-row { display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap; }
  .wa-strip-text { font-size: 0.9rem; color: var(--text-dim); }
  .wa-cta {
    display: inline-flex; align-items: center; gap: 8px;
    background: #25D366; color: #fff; text-decoration: none;
    padding: 11px 22px; border-radius: 30px; font-weight: 600; font-size: 0.9rem; white-space: nowrap;
  }
  .wa-cta:hover { background: #1EBE59; }

  /* brands carousel */
  .brands { padding: 20px 0; }
  .brands-inner {
    max-width: 70%; margin: 0 auto; background: #FFFFFF; border: 1px solid var(--line);
    border-radius: 14px; padding: 18px 24px; overflow: hidden;
  }
  .brands-track { display: flex; align-items: center; gap: 56px; width: max-content; animation: brands-scroll 12s linear infinite; }
  @media (hover: hover) and (pointer: fine) {
    .brands:hover .brands-track { animation-play-state: paused; }
  }
  .brands-track img { height: 24px; width: auto; flex-shrink: 0; opacity: 0.85; }
  @keyframes brands-scroll { from { transform: translateX(0); } to { transform: translateX(-50%); } }

  /* trust */
  .section { padding: 30px 0; }
  #catalogo { padding-top: 16px; }
  .section.on-paper { background: var(--surface-tint); }
  .trust-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; }
  .trust-card {
    background: var(--surface); border: 1px solid var(--line); border-radius: 12px;
    padding: 18px 14px; text-align: center; font-size: 1.6rem;
  }
  .trust-card span { display: block; font-size: 0.8rem; color: var(--text-dim); margin-top: 8px; font-weight: 500; }

  .section-head { text-align: center; max-width: 560px; margin: 0 auto 20px; }
  .section-head .section-note { margin: 4px 0; }
  .section-title { font-family: var(--serif); font-weight: 600; font-size: clamp(1.4rem, 2.4vw, 1.9rem); margin: 0 0 6px; }
  .section-note { color: var(--ash); font-size: 0.9rem; margin: 0; }

  /* product grid (catálogo) */
  .products { display: grid; grid-template-columns: repeat(5, 1fr); gap: 16px; }
  .product-card {
    background: var(--surface); border: 1px solid var(--line); border-radius: var(--radius-card);
    overflow: hidden; text-decoration: none; color: inherit;
    transition: border-color 0.15s ease, transform 0.15s ease, box-shadow 0.15s ease;
    display: flex; flex-direction: column;
  }
  .product-card:hover { border-color: rgba(185,134,42,0.5); transform: translateY(-2px); box-shadow: 0 10px 24px rgba(28,26,20,0.08); }
  .product-card-image {
    aspect-ratio: 1/1; background: var(--surface-tint); display: flex; align-items: center; justify-content: center; overflow: hidden;
  }
  .product-card-image img { width: 100%; height: 100%; object-fit: contain; }
  .product-card-body { padding: 16px 18px 18px; display: flex; flex-direction: column; gap: 6px; flex: 1; }
  .product-card-meta { font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.03em; color: var(--teal); font-weight: 600; }
  .product-card-body h3 { font-family: var(--serif); font-weight: 600; font-size: 1.05rem; margin: 0; }
  .product-card-desc { font-size: 0.82rem; color: var(--ash); margin: 0; }
  .product-card-price { margin-top: auto; padding-top: 10px; display: flex; align-items: baseline; gap: 8px; flex-wrap: wrap; }
  .from-label { font-size: 0.72rem; color: var(--ash); }
  .price-usd { font-family: var(--serif); font-weight: 600; font-size: 1.15rem; }
  .usd-tag { font-family: var(--sans); font-weight: 500; font-size: 0.62rem; color: var(--ash); vertical-align: middle; }
  .price-bs { font-size: 0.72rem; color: var(--ash); }

  /* cómo comprar */
  .how-grid { display: grid; grid-template-columns: 1.1fr 0.9fr; gap: 32px; align-items: start; }
  .how-steps { display: flex; flex-direction: column; gap: 22px; }
  .how-step { display: flex; gap: 16px; align-items: flex-start; }
  .how-step-num {
    width: 34px; height: 34px; border-radius: 50%; background: var(--gold); color: #FFFBF3;
    display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.95rem; flex-shrink: 0;
  }
  .how-step h4 { font-family: var(--serif); font-weight: 600; font-size: 1rem; margin: 0 0 4px; }
  .how-step p { font-size: 0.86rem; color: var(--text-dim); margin: 0; }
  .how-types { display: flex; flex-direction: column; gap: 16px; }
  .how-type-card { background: var(--surface); border: 1px solid var(--line); border-radius: 12px; padding: 18px 20px; }
  .how-type-card h4 { font-family: var(--serif); font-weight: 600; font-size: 1rem; margin: 0 0 8px; }
  .how-type-time { font-size: 0.82rem; font-weight: 600; color: var(--teal); margin: 0 0 10px; }
  .how-type-time span { font-weight: 400; color: var(--ash); }
  .how-type-card ul { margin: 0; padding-left: 18px; font-size: 0.85rem; color: var(--text-dim); }
  .how-type-card li { margin-bottom: 4px; }
  .how-type-note { text-align: center; font-size: 0.78rem; color: var(--ash); margin: 4px 0 0; }

  /* reseñas */
  .reviews-carousel { position: relative; display: flex; align-items: center; gap: 8px; }
  .reviews-pager { display: flex; overflow-x: auto; scroll-snap-type: x mandatory; scroll-behavior: smooth; flex: 1; scrollbar-width: none; }
  .reviews-pager::-webkit-scrollbar { display: none; }
  .review-page {
    flex: 0 0 100%; scroll-snap-align: start; display: grid;
    grid-template-columns: repeat(3, 1fr); gap: 16px; padding: 4px 2px;
  }
  .review-card {
    background: var(--surface); border: 1px solid var(--line);
    border-radius: 12px; padding: 18px 20px; display: flex; flex-direction: column;
  }
  .review-stars { color: var(--gold); letter-spacing: 2px; margin-bottom: 8px; }
  .review-text { font-size: 0.88rem; color: var(--text-dim); font-style: italic; margin: 0 0 10px; }
  .review-product { font-size: 0.78rem; color: var(--teal); font-weight: 600; margin: 0 0 12px; }
  .review-footer { display: flex; justify-content: space-between; align-items: baseline; margin-top: auto; gap: 8px; }
  .review-author { font-size: 0.78rem; color: var(--ash); margin: 0; font-weight: 600; }
  .review-date { font-size: 0.72rem; color: var(--ash); margin: 0; white-space: nowrap; }
  .reviews-arrow {
    flex-shrink: 0; width: 34px; height: 34px; border-radius: 50%; background: var(--surface);
    border: 1px solid var(--line); font-size: 1.3rem; color: var(--text); cursor: pointer;
    display: flex; align-items: center; justify-content: center; line-height: 1;
  }
  .reviews-arrow:hover { border-color: var(--gold); color: var(--gold); }
  .reviews-dots { display: flex; justify-content: center; gap: 8px; margin-top: 16px; }
  .reviews-dot { width: 8px; height: 8px; border-radius: 50%; background: var(--line); cursor: pointer; transition: background 0.15s ease; }
  .reviews-dot.active { background: var(--gold); }
  .reviews-note { text-align: center; font-size: 0.78rem; color: var(--ash); margin-top: 18px; }
  footer.site-footer { background: var(--dark); color: var(--text-on-dark-dim); text-align: center; padding: 34px 0; font-size: 0.85rem; margin-top: 20px; }
  footer.site-footer a { color: var(--text-on-dark); text-decoration: none; }
  footer.site-footer p { margin: 4px 0; }
  .social-row { display: flex; justify-content: center; gap: 18px; margin: 10px 0; }
  .social-row a { font-weight: 600; }
  .social-row a:hover { color: var(--gold-bright); }

  @media (max-width: 900px) {
    .products { grid-template-columns: repeat(3, 1fr); }
    .ve-banner-inner { max-width: 88%; }
    .brands-inner { max-width: 88%; }
    .nav-links { display: none; }
    .how-grid { grid-template-columns: 1fr; }
    .review-card { flex-basis: 240px; }
    .review-page { grid-template-columns: repeat(2, 1fr); }
  }
  @media (max-width: 560px) {
    .products { grid-template-columns: repeat(2, 1fr); }
    .wa-strip-row { flex-direction: column; align-items: flex-start; }
    .ve-banner-inner { max-width: 100%; border-radius: 0; }
    .brands-inner { max-width: 100%; border-radius: 0; }
    .review-page { grid-template-columns: 1fr; }
    .logo img { height: 24px; }
    .nav-actions { gap: 8px; }
    .btn-wa { padding: 8px 12px; font-size: 0.78rem; }
    .wa-status { display: none; }
    .flag-icon svg { width: 24px; height: 16px; }
  }
  @media (prefers-reduced-motion: reduce) {
    * { animation: none !important; transition: none !important; }
  }

  /* ── buscador, filtros y estado de productos (catálogo) ── */
  .ve-tools { margin: 0 auto 20px; display: flex; flex-direction: column; gap: 12px; }
  .ve-search { position: relative; width: 100%; max-width: 560px; margin: 0 auto; }
  .ve-search > svg { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); pointer-events: none; color: var(--ash); }
  .ve-search input { width: 100%; padding: 12px 18px 12px 44px; border: 1px solid var(--line); border-radius: 30px; font-family: var(--sans); font-size: 0.95rem; background: var(--surface); color: var(--text); outline: none; }
  .ve-search input:focus { border-color: var(--gold); box-shadow: 0 0 0 3px rgba(185,134,42,0.15); }
  .ve-suggest { position: absolute; left: 0; right: 0; top: calc(100% + 6px); background: #fff; border: 1px solid var(--line); border-radius: 14px; box-shadow: 0 14px 34px rgba(28,26,20,0.14); z-index: 40; overflow: hidden; display: none; }
  .ve-suggest.open { display: block; }
  .ve-suggest a { display: flex; align-items: center; gap: 12px; padding: 9px 14px; text-decoration: none; color: var(--text); font-size: 0.88rem; }
  .ve-suggest a:hover, .ve-suggest a.act { background: var(--surface-tint); }
  .ve-suggest img { width: 38px; height: 38px; object-fit: contain; background: var(--surface-tint); border-radius: 8px; flex-shrink: 0; }
  .ve-suggest .sg-t { flex: 1; font-weight: 600; line-height: 1.25; }
  .ve-suggest .sg-p { font-size: 0.78rem; color: var(--ash); white-space: nowrap; }
  .ve-filters { display: flex; flex-wrap: wrap; gap: 8px; justify-content: center; align-items: center; }
  .ve-filters select { padding: 8px 30px 8px 14px; border: 1px solid var(--line); border-radius: 20px; background: var(--surface); font-family: var(--sans); font-size: 0.82rem; color: var(--text); cursor: pointer; }
  .ve-filters select.on { border-color: var(--gold); background: rgba(185,134,42,0.08); font-weight: 600; }
  .ve-clear { background: none; border: none; color: var(--gold); font-family: var(--sans); font-size: 0.82rem; font-weight: 600; cursor: pointer; display: none; }
  .ve-count { text-align: center; font-size: 0.8rem; color: var(--ash); }
  .ve-empty { display: none; text-align: center; color: var(--ash); padding: 30px 10px; grid-column: 1 / -1; }
  .product-card-image { position: relative; }
  .product-card.is-soldout .product-card-image img { opacity: 0.45; }
  .soldout-tag { position: absolute; top: 10px; left: 10px; background: #8a4b4b; color: #fff; font-size: 0.7rem; font-weight: 700; padding: 3px 10px; border-radius: 20px; }
  .rv-verified { display: inline-flex; align-items: center; gap: 4px; font-size: 0.72rem; font-weight: 600; color: var(--teal); }
  .rv-verified svg { flex-shrink: 0; }

  /* ── pedido y pago ── */
  .buy-box { background: var(--surface); border: 1px solid var(--line); border-radius: 14px; padding: 18px; margin-bottom: 18px; }
  .buy-box h3 { font-family: var(--serif); font-weight: 600; font-size: 1.05rem; margin: 0 0 12px; }
  .variant-item.selectable { cursor: pointer; transition: border-color 0.15s ease, background 0.15s ease; }
  .variant-item.selectable:has(input:checked) { border-color: var(--gold); background: rgba(185,134,42,0.06); }
  .variant-item.selectable.is-off { opacity: 0.5; cursor: not-allowed; }
  .variant-item input[type=radio] { accent-color: var(--gold); width: 18px; height: 18px; margin-right: 10px; flex-shrink: 0; }
  .variant-left { display: flex; align-items: center; }
  .field { margin-bottom: 12px; }
  .field label { display: block; font-size: 0.8rem; font-weight: 600; color: var(--text-dim); margin-bottom: 5px; }
  .field input[type=text], .field input[type=email], .field input[type=tel], .field input[type=file], .field textarea, .field select {
    width: 100%; padding: 11px 13px; border: 1px solid var(--line); border-radius: 10px; font-family: var(--sans); font-size: 0.92rem; background: #fff; color: var(--text); }
  .field input:focus, .field textarea:focus { outline: none; border-color: var(--gold); }
  .field small { display: block; color: var(--ash); font-size: 0.75rem; margin-top: 4px; }
  .hp { position: absolute; left: -9999px; height: 0; overflow: hidden; }
  .pay-methods { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 14px; }
  .pay-method { border: 1px solid var(--line); border-radius: 12px; padding: 12px 14px; cursor: pointer; background: #fff; display: flex; gap: 10px; align-items: flex-start; }
  .pay-method:has(input:checked) { border-color: var(--gold); background: rgba(185,134,42,0.06); }
  .pay-method input { accent-color: var(--gold); margin-top: 3px; }
  .pay-method b { display: block; font-size: 0.9rem; } .pay-method span { font-size: 0.76rem; color: var(--ash); }
  .btn-buy { width: 100%; background: var(--gold); color: #FFFBF3; border: 0; border-radius: 12px; padding: 14px; font-family: var(--sans); font-weight: 700; font-size: 1rem; cursor: pointer; }
  .btn-buy:hover { filter: brightness(1.06); } .btn-buy[disabled] { opacity: 0.55; cursor: not-allowed; }
  .btn-sec { display: inline-block; background: transparent; color: var(--text); border: 1px solid var(--line); border-radius: 10px; padding: 10px 16px; text-decoration: none; font-size: 0.88rem; font-weight: 600; }
  .buy-total { display: flex; justify-content: space-between; align-items: baseline; margin: 6px 0 14px; padding-top: 12px; border-top: 1px dashed var(--line); }
  .buy-total .t-usd { font-family: var(--serif); font-weight: 600; font-size: 1.4rem; } .buy-total .t-bs { font-size: 0.82rem; color: var(--ash); }
  .page-narrow { max-width: 640px; margin: 0 auto; padding: 28px 24px 10px; }
  .panel { background: var(--surface); border: 1px solid var(--line); border-radius: 16px; padding: 24px; margin-bottom: 18px; }
  .panel h1 { font-family: var(--serif); font-weight: 600; font-size: 1.5rem; margin: 0 0 6px; }
  .panel .lead { color: var(--text-dim); margin: 0 0 16px; }
  .pay-data { background: var(--surface-tint); border-radius: 12px; padding: 14px 16px; white-space: pre-line; font-size: 0.95rem; line-height: 1.6; margin: 10px 0; }
  .pay-amount { display: flex; justify-content: space-between; align-items: baseline; background: #fff; border: 2px solid var(--gold); border-radius: 12px; padding: 12px 16px; margin: 12px 0; }
  .pay-amount b { font-family: var(--serif); font-size: 1.5rem; }
  .pay-amount span { font-size: 0.78rem; color: var(--ash); text-align: right; }
  .alert { border-radius: 10px; padding: 11px 14px; font-size: 0.88rem; margin: 12px 0; background: var(--surface-tint); }
  .alert.err { background: #fdeaea; color: #8a1f1f; } .alert.ok { background: #e6f4ef; color: #1c5b52; } .alert.warn { background: #fdf3dc; color: #7a5b12; }
  .steps-mini { display: flex; gap: 8px; margin: 0 0 18px; font-size: 0.74rem; color: var(--ash); }
  .steps-mini span { flex: 1; text-align: center; padding: 8px 4px; border-radius: 8px; background: var(--surface-tint); }
  .steps-mini span.on { background: var(--gold); color: #FFFBF3; font-weight: 700; }
  .stars-in { display: flex; gap: 6px; flex-direction: row-reverse; justify-content: flex-end; }
  .stars-in input { display: none; } .stars-in label { font-size: 2rem; color: #d9d3c0; cursor: pointer; margin: 0; }
  .stars-in input:checked ~ label, .stars-in label:hover, .stars-in label:hover ~ label { color: var(--gold); }
  .rating-line { margin: 0 0 10px; font-size: 0.88rem; color: var(--gold); } .rating-line span { color: var(--ash); }
  .pr-review { border-bottom: 1px solid var(--line); padding: 14px 0; } .pr-review:last-child { border-bottom: 0; }
  .pr-review .top { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; font-size: 0.85rem; }
  .pr-review .top b { color: var(--text); } .pr-review .stars-s { color: var(--gold); letter-spacing: 1px; }
  .pr-review p { margin: 6px 0 0; font-size: 0.9rem; color: var(--text-dim); }
  @media (max-width: 560px) { .pay-methods { grid-template-columns: 1fr; } }
</style>
