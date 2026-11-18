/*
 * Neocarts for OpenCart: the storefront half, identical for 2.3, 3.0 and 4.x.
 * The page gets window.NeocartsOpencart from the header event (see the adapters' catalog controller):
 *   { route, locale, cardsOn, settings: Settings::storefront(), texts: { sf_* }, urls: { … } }
 * Buttons are placed by CSS selectors from the settings (theme presets: docs/ai/themes.md); every
 * price and title comes from the shop's server, never from the page.
 */
(function () {
  'use strict';

  var cfg = window.NeocartsOpencart;
  if (!cfg || window.__neocartsOpencart) return;
  window.__neocartsOpencart = true;

  var s = cfg.settings;
  var DONE = 'data-neocarts-done';
  var productCache = {};
  var paid = false;

  function nc() {
    return window.NeocartsSubscription;
  }

  function whenReady(fn) {
    if (nc()) fn();
    else document.addEventListener('neocarts:ready', fn, { once: true });
  }

  function onDom(fn) {
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', fn);
    else fn();
  }

  function request(url, body) {
    var options = { credentials: 'same-origin', headers: { Accept: 'application/json' } };
    if (body !== undefined) {
      options.method = 'POST';
      options.headers['Content-Type'] = 'application/json';
      options.body = JSON.stringify(body);
    }
    return fetch(url, options).then(function (res) {
      return res.json().catch(function () { return {}; }).then(function (json) {
        if (!res.ok) {
          var error = new Error(json.message || cfg.texts.sf_error);
          error.status = res.status;
          throw error;
        }
        return json;
      });
    });
  }

  function regex(source) {
    try {
      return new RegExp(source);
    } catch (e) {
      return null;
    }
  }

  // ---- selectors ----------------------------------------------------------------------------

  function alternatives(spec) {
    return String(spec || '').split('||').map(function (p) { return p.trim(); }).filter(Boolean);
  }

  /**
   * `a || b || c`: the first alternative that matches wins (a CSS list `a, b` would return the first
   * match in document order instead). An invalid alternative (e.g. :has() in an old browser) is skipped.
   */
  function first(root, spec) {
    var parts = alternatives(spec);
    for (var i = 0; i < parts.length; i++) {
      try {
        var el = root.querySelector(parts[i]);
        if (el) return el;
      } catch (e) { /* invalid in this browser: try the next one */ }
    }
    return null;
  }

  /** Every element of the first alternative that matches anything. */
  function every(root, spec) {
    var parts = alternatives(spec);
    for (var i = 0; i < parts.length; i++) {
      try {
        var list = root.querySelectorAll(parts[i]);
        if (list.length) return Array.prototype.slice.call(list);
      } catch (e) { /* skip */ }
    }
    return [];
  }

  /** el.closest() over any of the alternatives. */
  function closestOf(el, spec) {
    var parts = alternatives(spec);
    for (var i = 0; i < parts.length; i++) {
      try {
        var found = el.closest(parts[i]);
        if (found) return found;
      } catch (e) { /* skip */ }
    }
    return null;
  }

  /** Carousels clone slides: only the original gets a button. */
  function cloned(el) {
    return !!el.closest('.owl-item.cloned, .slick-cloned, .swiper-slide-duplicate');
  }

  function place(anchor, node, position) {
    var map = { before: 'beforebegin', after: 'afterend', prepend: 'afterbegin', append: 'beforeend' };
    anchor.insertAdjacentElement(map[position] || 'afterend', node);
  }

  // ---- buttons and data ---------------------------------------------------------------------

  /** <neocarts-button> for one product or cart line, as the widget's docs describe it. */
  function button(p) {
    var el = document.createElement('neocarts-button');
    el.setAttribute('product-id', p.external_id);
    el.setAttribute('amount', String(p.price));
    el.setAttribute('title', p.title);
    el.setAttribute('image', p.image_url);
    if (p.variant) el.setAttribute('variant', p.variant);
    if (s.button_checkout_url && cfg.urls.cartPage) el.setAttribute('checkout-url', cfg.urls.cartPage);
    el.className = 'neocarts-oc-button';
    return el;
  }

  /**
   * The button in a box per placement (`product` | `card` | `cart` | `slot`): the box carries the
   * merchant's class and inline style (`<place>_class`, `<place>_style`), so spacing and layout can be
   * fixed per theme without touching the widget.
   */
  function boxed(p, place) {
    var box = document.createElement('div');
    box.className = ('neocarts-oc-slot neocarts-oc-slot--' + place + ' ' + (s[place + '_class'] || '')).trim();
    if (s[place + '_style']) box.setAttribute('style', s[place + '_style']);
    box.appendChild(button(p));
    return box;
  }

  /** No button for a product the merchant switched off, one out of stock, or one whose required options are not chosen yet. */
  function canSubscribe(p) {
    return !!p && !p.has_options && p.subscribable !== false;
  }

  /** Base spacing + the merchant's custom CSS (settings → custom_css), once per page. */
  function styles() {
    var el = document.createElement('style');
    el.id = 'neocarts-oc-styles';
    el.textContent = 'neocarts-button.neocarts-oc-button{display:block}.neocarts-oc-slot{clear:both}' +
      '.neocarts-oc-oneclick-hidden{display:none!important}' + (s.custom_css || '');
    document.head.appendChild(el);
  }

  function products(ids) {
    var missing = ids.filter(function (id) { return !(id in productCache); });
    if (!missing.length) return Promise.resolve(productCache);
    return request(cfg.urls.products + (cfg.urls.products.indexOf('?') < 0 ? '?' : '&') + 'ids=' + missing.join(','))
      .then(function (json) {
        missing.forEach(function (id) { productCache[id] = (json.products || {})[id] || null; });
        return productCache;
      });
  }

  function cartLines() {
    return request(cfg.urls.cart).then(function (json) { return json.lines || []; });
  }

  // ---- product page and quick view ----------------------------------------------------------

  /** The product a button belongs to: its own form / product block / modal, not the first id on the page. */
  function productIdNear(anchor) {
    var scope = anchor.closest('form, #product, #form-product, .modal, #colorbox, [data-neocarts-product]') || document;
    var input = scope.querySelector('input[name="product_id"]') || document.querySelector('input[name="product_id"]');
    return input ? input.value : null;
  }

  function productPage() {
    if (!s.product_status) return;
    var anchors = every(document, s.product_anchor).concat(s.quickview_anchor ? every(document, s.quickview_anchor) : []);
    anchors.forEach(function (anchor) {
      if (anchor.hasAttribute(DONE) || cloned(anchor) || closestOf(anchor, s.card_selector)) return;
      var id = productIdNear(anchor);
      if (!id) return;
      anchor.setAttribute(DONE, '');
      products([id]).then(function (all) {
        var p = all[id];
        if (canSubscribe(p)) place(anchor, boxed(p, 'product'), s.product_position);
      });
    });
  }

  // ---- product cards ------------------------------------------------------------------------

  function cards() {
    if (!cfg.cardsOn) return;
    var re = regex(s.card_id_regex);
    if (!re) return;
    var found = [];
    every(document, s.card_selector).forEach(function (card) {
      if (card.hasAttribute(DONE) || cloned(card)) return;
      card.setAttribute(DONE, '');
      var m = card.outerHTML.match(re);
      var anchor = s.card_anchor ? first(card, s.card_anchor) : null;
      // an anchor the theme does not have still gets a button: at the end of the card
      if (m) found.push({ id: m[1], anchor: anchor || card, position: anchor ? s.card_position : 'append' });
    });
    if (!found.length) return;
    products(found.map(function (f) { return f.id; })).then(function (all) {
      found.forEach(function (f) {
        var p = all[f.id];
        if (canSubscribe(p)) place(f.anchor, boxed(p, 'card'), f.position);
      });
    });
  }

  // ---- manual slots -------------------------------------------------------------------------

  /**
   * <div data-neocarts-slot data-product-id="42"></div> or data-cart-key="7" anywhere in a theme,
   * an HTML module or a page builder: the escape hatch for themes without a preset.
   */
  function slots() {
    var found = document.querySelectorAll('[data-neocarts-slot]:not([' + DONE + '])');
    if (!found.length) return;
    var byProduct = [];
    var byCart = [];
    Array.prototype.forEach.call(found, function (slot) {
      slot.setAttribute(DONE, '');
      if (slot.getAttribute('data-product-id')) byProduct.push(slot);
      else if (slot.getAttribute('data-cart-key')) byCart.push(slot);
    });
    if (byProduct.length) {
      products(byProduct.map(function (el) { return el.getAttribute('data-product-id'); })).then(function (all) {
        byProduct.forEach(function (el) {
          var p = all[el.getAttribute('data-product-id')];
          if (canSubscribe(p)) el.appendChild(boxed(p, 'slot'));
        });
      });
    }
    if (byCart.length) {
      cartLines().then(function (lines) {
        byCart.forEach(function (el) {
          var line = lines.filter(function (l) { return String(l.key) === el.getAttribute('data-cart-key'); })[0];
          if (line && line.subscribable !== false) el.appendChild(boxed(line, 'slot'));
        });
      });
    }
  }

  // ---- cart page ----------------------------------------------------------------------------

  function cartRows() {
    var rows = s.cart_status ? every(document, s.cart_row_selector) : [];
    var re = regex(s.cart_key_regex);
    if (!rows.length || !re) return;
    cartLines().then(function (lines) {
      var byKey = {};
      lines.forEach(function (l) { byKey[String(l.key)] = l; });
      rows.forEach(function (row) {
        if (row.hasAttribute(DONE)) return;
        var m = row.outerHTML.match(re);
        var line = m && byKey[m[1]];
        if (!line || line.subscribable === false) return;
        var anchor = s.cart_anchor ? first(row, s.cart_anchor) : null;
        row.setAttribute(DONE, '');
        place(anchor || row, boxed(line, 'cart'), anchor ? s.cart_position : 'append');
      });
      forgetRemoved(lines);
    });
  }

  /** A product taken out of the cart starts as one-time when it comes back. */
  function forgetRemoved(lines) {
    var inCart = {};
    lines.forEach(function (l) { inCart[l.external_id] = true; });
    var gone = Object.keys(nc().all()).filter(function (id) { return !inCart[id]; });
    if (gone.length) nc().remove(gone);
  }

  // ---- subscribe = add to the cart; one-click order buttons ---------------------------------

  function autoAdd() {
    var before = nc().all();
    nc().subscribe(function (all) {
      oneClick(all);
      var fresh = Object.keys(all).filter(function (id) {
        return all[id].mode === 'subscription' && !(before[id] && before[id].mode === 'subscription');
      });
      before = all;
      if (!s.auto_add || !fresh.length) return;
      cartLines().then(function (lines) {
        var inCart = {};
        lines.forEach(function (l) { inCart[l.external_id] = true; });
        fresh.filter(function (id) { return !inCart[id] && /^\d+$/.test(id); }).forEach(addToCart);
      });
    });
    oneClick(before);
  }

  /** One-click / fast-order buttons bypass the cart: hidden while anything is chosen as a subscription. */
  function oneClick(all) {
    if (!s.oneclick_selector) return;
    var any = Object.keys(all).some(function (id) { return all[id].mode === 'subscription'; });
    every(document, s.oneclick_selector).forEach(function (el) {
      el.classList.toggle('neocarts-oc-oneclick-hidden', any);
    });
  }

  function addToCart(productId) {
    var form = document.querySelector('#form-product, #product');
    var idInput = form && form.querySelector('input[name="product_id"]');
    var body;
    if (idInput && idInput.value === productId) {
      body = new URLSearchParams(new FormData(form.tagName === 'FORM' ? form : wrap(form)));
    } else if (window.cart && typeof window.cart.add === 'function') {
      // 2.3/3.0 themes override cart.add() to update their own counters, popups and drawers
      window.cart.add(productId, 1);
      return;
    } else {
      body = new URLSearchParams({ product_id: productId, quantity: '1' });
    }
    fetch(cfg.urls.cartAdd, {
      method: 'POST', credentials: 'same-origin', body: body,
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
    }).then(refreshMiniCart);
  }

  /** 2.3/3.0 keep the product fields in a <div id="product">, not a form. */
  function wrap(div) {
    var form = document.createElement('form');
    div.querySelectorAll('input, select, textarea').forEach(function (el) {
      if (!el.name || ((el.type === 'radio' || el.type === 'checkbox') && !el.checked)) return;
      var copy = document.createElement('input');
      copy.name = el.name;
      copy.value = el.value;
      form.appendChild(copy);
    });
    return form;
  }

  function refreshMiniCart() {
    var target = first(document, s.minicart_selector);
    var url = s.minicart_url || cfg.urls.miniCart;
    if (!target || !url) return;
    fetch(url, { credentials: 'same-origin' }).then(function (r) { return r.text(); }).then(function (html) {
      var doc = new DOMParser().parseFromString(html, 'text/html');
      var fresh = target.id ? doc.getElementById(target.id) : null;
      if (fresh) target.innerHTML = fresh.innerHTML;
      else if (doc.body) target.innerHTML = doc.body.innerHTML;
    });
  }

  // ---- checkout -----------------------------------------------------------------------------

  var busy = false;

  /** @return Promise<boolean> true when Neocarts checkout opened (or an error was shown) */
  function checkout() {
    if (busy) return Promise.resolve(true);
    return cartLines().then(function (lines) {
      var items = nc().getOrderItems(lines.map(function (l) { return { external_id: l.external_id, quantity: l.quantity }; }));
      var selection = {};
      items.forEach(function (i) {
        if (i.mode === 'subscription') {
          selection[i.external_id] = { mode: i.mode, interval_value: i.interval_value, interval_unit: i.interval_unit };
        }
      });
      if (!Object.keys(selection).length) return false;
      busy = true;
      toast(cfg.texts.sf_busy);
      return request(cfg.urls.order, { selection: selection })
        .then(function (json) { return nc().open({ orderId: json.orderId, locale: cfg.locale }); })
        .then(function () { toast(null); return true; })
        .catch(function (e) {
          toast(e && e.message === 'Shop is not ready' ? cfg.texts.sf_not_ready : (e && e.message) || cfg.texts.sf_error);
          return true;
        })
        .then(function (opened) { busy = false; return opened; });
    });
  }

  /** A checkout link by the selector, or the server's own checkout URL (SEO keyword URLs). */
  function checkoutLink(el) {
    var link = closestOf(el, s.checkout_link_selector);
    if (link) return link;
    var a = el.closest('a[href]');
    return a && cfg.urls.checkout && a.href === cfg.urls.checkout ? a : null;
  }

  function interceptCheckoutLinks() {
    if (!s.checkout_intercept) return;
    document.addEventListener('click', function (event) {
      var link = event.target.closest && checkoutLink(event.target);
      if (!link || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey) return;
      event.preventDefault();
      event.stopPropagation();
      checkout().then(function (opened) {
        if (opened) return;
        if (link.href) window.location.href = link.href;
        else if (cfg.urls.checkout) window.location.href = cfg.urls.checkout;
      });
    }, true);
  }

  function checkoutBanner() {
    var routes = String(s.checkout_routes || '').split(',').map(function (r) { return r.trim(); }).filter(Boolean);
    var route = String(cfg.route || '').replace(/[|.]/g, '/');
    if (!s.checkout_banner_status || !routes.some(function (r) { return route.indexOf(r) === 0; })) return;
    var anchor = first(document, s.checkout_banner_anchor);
    if (!anchor || anchor.hasAttribute(DONE)) return;
    anchor.setAttribute(DONE, '');
    cartLines().then(function (lines) {
      var items = nc().getOrderItems(lines.map(function (l) { return { external_id: l.external_id, quantity: l.quantity }; }));
      if (!items.some(function (i) { return i.mode === 'subscription'; })) return;
      var box = document.createElement('div');
      box.className = 'neocarts-oc-banner alert alert-info';
      box.appendChild(document.createTextNode(cfg.texts.sf_checkout_banner + ' '));
      var go = document.createElement('button');
      go.type = 'button';
      go.className = 'btn btn-primary';
      go.textContent = cfg.texts.sf_checkout_button;
      go.addEventListener('click', checkout);
      box.appendChild(go);
      place(anchor, box, s.checkout_banner_position);
    });
  }

  function widgetEvents() {
    var api = nc();
    api.on(api.events.Paid, function () {
      paid = true;
      request(cfg.urls.clear, {}).then(refreshMiniCart);
    });
    api.on(api.events.Close, function () {
      if (paid && cfg.urls.success) window.location.href = cfg.urls.success;
    });
    api.on(api.events.OrderMissing, function () {
      toast(cfg.texts.sf_order_missing);
    });
  }

  var toastEl;
  function toast(text) {
    if (!toastEl) {
      toastEl = document.createElement('div');
      toastEl.className = 'neocarts-oc-toast';
      toastEl.setAttribute('role', 'alert');
      toastEl.style.cssText = 'position:fixed;left:50%;bottom:24px;transform:translateX(-50%);z-index:2147483000;' +
        'max-width:calc(100% - 32px);padding:12px 16px;border-radius:8px;background:#1f2937;color:#fff;font-size:14px;display:none';
      document.body.appendChild(toastEl);
    }
    toastEl.textContent = text || '';
    toastEl.style.display = text ? 'block' : 'none';
    clearTimeout(toastEl._t);
    if (text && text !== cfg.texts.sf_busy) toastEl._t = setTimeout(function () { toast(null); }, 6000);
  }

  // ---- start --------------------------------------------------------------------------------

  function scan() {
    productPage();
    cards();
    slots();
    cartRows();
    checkoutBanner();
  }

  onDom(function () {
    styles();
    whenReady(function () {
      scan();
      autoAdd();
      interceptCheckoutLinks();
      widgetEvents();
      // ponytail: one observer re-scans on any DOM change (AJAX listings, quick view, 4.x cart reloads); debounced
      var timer;
      new MutationObserver(function () {
        clearTimeout(timer);
        timer = setTimeout(scan, 150);
      }).observe(document.body, { childList: true, subtree: true });
    });
  });

  window.NeocartsOpencart.checkout = checkout;
})();
