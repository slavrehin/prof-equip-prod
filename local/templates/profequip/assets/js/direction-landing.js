/* Лендинги направлений: интерактивность страницы. Чистый vanilla JS, без зависимостей.
   Отправку форм (капча, fetch, обработка ответа) выполняет общий /local/templates/profequip/js/dev.js
   по классу .fb-form — здесь только валидация до отправки, маска телефона, вложения, источник лида. */
(function () {
  'use strict';

  var root = document.querySelector('.dl');
  if (!root) return;

  var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var mq = window.matchMedia('(max-width: 860px)');
  var $ = function (sel, ctx) { return (ctx || root).querySelector(sel); };
  var $$ = function (sel, ctx) { return Array.prototype.slice.call((ctx || root).querySelectorAll(sel)); };
  var metrikaId = parseInt(root.getAttribute('data-metrika'), 10) || 0;

  /* ---------- Высота фиксированной шапки → --dl-hdr (отступ контента и липкое меню) ---------- */
  (function () {
    var hdr = document.querySelector('.header');
    if (!hdr) return;
    function sync() {
      root.style.setProperty('--dl-hdr', hdr.offsetHeight + 'px');
      // ширина полосы прокрутки (0 при оверлейных полосах) — для блока «Запчасти» на всю ширину экрана
      root.style.setProperty('--dl-sbw', (window.innerWidth - document.documentElement.clientWidth) + 'px');
    }
    sync();
    if ('ResizeObserver' in window) new ResizeObserver(sync).observe(hdr); else window.addEventListener('resize', sync);
  })();

  /* ---------- Hero-слайдер ---------- */
  (function () {
    var hero = $('.dl-hero');
    if (!hero) return;
    var slides = $$('.dl-slide', hero), tabs = $$('.dl-htab', hero), cur = 0, timer;
    if (slides.length < 2 || tabs.length !== slides.length) return;

    function go(i) {
      slides[cur].classList.remove('is-on');
      tabs[cur].classList.remove('is-on');
      tabs[cur].setAttribute('aria-selected', 'false');
      cur = (i + slides.length) % slides.length;
      slides[cur].classList.add('is-on');
      void tabs[cur].offsetWidth; // перезапуск CSS-прогресса вкладки
      tabs[cur].classList.add('is-on');
      tabs[cur].setAttribute('aria-selected', 'true');
    }
    function play() {
      if (reduce) return;
      clearInterval(timer);
      timer = setInterval(function () { go(cur + 1); }, 7000);
    }
    tabs.forEach(function (t, i) { t.addEventListener('click', function () { go(i); play(); }); });
    hero.addEventListener('mouseenter', function () { clearInterval(timer); hero.classList.add('is-paused'); });
    hero.addEventListener('mouseleave', function () { hero.classList.remove('is-paused'); play(); });
    hero.addEventListener('focusin', function () { clearInterval(timer); hero.classList.add('is-paused'); });
    play();
  })();

  /* ---------- Стрелки карусели проектов ---------- */
  $$('[data-scroll]').forEach(function (b) {
    b.addEventListener('click', function () {
      var t = $(b.getAttribute('data-scroll')), card = t && t.firstElementChild;
      if (!card) return;
      t.scrollBy({ left: (card.offsetWidth + 32) * parseInt(b.getAttribute('data-dir'), 10), behavior: reduce ? 'auto' : 'smooth' });
    });
  });

  /* ---------- Запчасти: форма на месте фото ---------- */
  (function () {
    var parts = $('.dl-parts'), open = $('#dl-partsOpen'), close = $('#dl-partsClose');
    if (!parts || !open || !close) return;
    open.addEventListener('click', function () {
      parts.classList.add('is-form');
      open.setAttribute('aria-expanded', 'true');
      if (window.innerWidth <= 860) $('.dl-parts__media').scrollIntoView({ behavior: reduce ? 'auto' : 'smooth', block: 'start' });
      setTimeout(function () { var f = $('#dl-pf1'); if (f) f.focus({ preventScroll: true }); }, 350);
    });
    close.addEventListener('click', function () {
      parts.classList.remove('is-form');
      open.setAttribute('aria-expanded', 'false');
      open.focus();
    });
  })();

  /* ---------- Решения: вкладки (десктоп) / аккордеон (мобильный) ---------- */
  (function () {
    var btns = $$('.dl-sol__btn'), panels = $$('.dl-sol__panel'), wrap = $('.dl-sol__panels');
    if (!btns.length || btns.length !== panels.length) return;
    function place() {
      panels.forEach(function (p, i) {
        if (mq.matches) btns[i].nextElementSibling.appendChild(p); else wrap.appendChild(p);
      });
    }
    place();
    mq.addEventListener('change', place);
    btns.forEach(function (b, i) {
      b.addEventListener('click', function () {
        var open = b.getAttribute('aria-selected') === 'true';
        if (mq.matches && open) { b.setAttribute('aria-selected', 'false'); panels[i].hidden = true; return; }
        btns.forEach(function (x, j) { x.setAttribute('aria-selected', j === i ? 'true' : 'false'); panels[j].hidden = j !== i; });
      });
      // стрелки вверх/вниз — как у вкладок
      b.addEventListener('keydown', function (e) {
        if (e.key !== 'ArrowDown' && e.key !== 'ArrowUp') return;
        var n = btns[(i + (e.key === 'ArrowDown' ? 1 : btns.length - 1)) % btns.length];
        n.focus(); e.preventDefault();
      });
    });
  })();

  /* ---------- Переключатель (Новинки/В наличии): кнопки [data-switch-btn] переключают панели [data-switch-panel] внутри той же секции ---------- */
  $$('.dl-toggle:not(.dl-toggle--obj)').forEach(function (bar) {
    var section = bar.closest('section'), btns = $$('[data-switch-btn]', bar);
    if (!section || !btns.length) return;
    btns.forEach(function (b) {
      b.addEventListener('click', function () {
        var key = b.getAttribute('data-switch-btn');
        btns.forEach(function (x) { var on = x === b; x.classList.toggle('is-on', on); x.setAttribute('aria-selected', on ? 'true' : 'false'); });
        $$('[data-switch-panel]', section).forEach(function (p) { p.hidden = p.getAttribute('data-switch-panel') !== key; });
      });
    });
  });

  /* ---------- Зоны: переключатель типа объекта фильтрует вкладки .dl-sol__list по data-objtypes ---------- */
  (function () {
    var bar = $('#dl-objtype');
    if (!bar) return;
    var scope = $(bar.getAttribute('data-switch-scope') || '.dl-sol'), btns = $$('[data-objtype]', bar);
    var items = scope ? $$('.dl-sol__list > li', scope) : [];
    if (!scope || !items.length) return;
    function apply(type) {
      var any = false;
      items.forEach(function (li) {
        var types = (li.getAttribute('data-objtypes') || '').split(',').filter(Boolean);
        var show = !types.length || types.indexOf(type) !== -1;
        li.hidden = !show;
        if (show) any = true;
      });
      if (!any) items.forEach(function (li) { li.hidden = false; }); // без совпадений — показываем все, а не пустой список
      var first = items.filter(function (li) { return !li.hidden; })[0];
      var btn = first && $('.dl-sol__btn', first);
      if (btn && btn.getAttribute('aria-selected') !== 'true') btn.click();
    }
    btns.forEach(function (b) {
      b.addEventListener('click', function () {
        btns.forEach(function (x) { var on = x === b; x.classList.toggle('is-on', on); x.setAttribute('aria-selected', on ? 'true' : 'false'); });
        apply(b.getAttribute('data-objtype'));
      });
    });
    if (btns[0]) apply(btns[0].getAttribute('data-objtype'));
  })();

  /* ---------- Технологии: карточка → модальное окно (нативный <dialog> — фокус-ловушка и Esc уже встроены) ---------- */
  (function () {
    var dialog = $('#dl-techDialog'), cards = $$('[data-tech-open]');
    if (!dialog || !cards.length || typeof dialog.showModal !== 'function') return;
    var title = $('#dl-techTitle', dialog), body = $('[data-tech-body]', dialog), closeBtn = $('[data-tech-close]', dialog);
    cards.forEach(function (card) {
      card.addEventListener('click', function () {
        var tpl = $('[data-tech-detail]', card);
        title.textContent = tpl ? tpl.getAttribute('data-tech-title') : '';
        body.textContent = tpl ? tpl.content.textContent.trim() : '';
        dialog.showModal();
      });
    });
    closeBtn.addEventListener('click', function () { dialog.close(); });
    dialog.addEventListener('click', function (e) { if (e.target === dialog) dialog.close(); }); // клик вне окна (по backdrop)
  })();

  /* ---------- Этапы ---------- */
  (function () {
    var steps = $$('.dl-step'), detail = $('.dl-step__detail');
    if (!steps.length || !detail) return;
    steps.forEach(function (s) {
      s.addEventListener('click', function () {
        steps.forEach(function (x) { x.setAttribute('aria-expanded', 'false'); });
        s.setAttribute('aria-expanded', 'true');
        $('strong', detail).textContent = s.getAttribute('data-title');
        $('[data-step-text]', detail).textContent = s.getAttribute('data-text');
        $('[data-step-meta]', detail).textContent = s.getAttribute('data-meta');
        var pic = s.getAttribute('data-pic') || '', img = $('[data-step-pic]', detail);
        if (img) {
          img.hidden = !pic;
          if (pic) { img.src = pic; img.alt = s.getAttribute('data-title'); }
          detail.classList.toggle('has-pic', !!pic);
        }
        if (mq.matches) s.after(detail);
      });
    });
    function placeDetail() {
      var a = $('.dl-step[aria-expanded="true"]');
      if (mq.matches && a) a.after(detail); else $('.dl-steps').after(detail);
    }
    placeDetail();
    mq.addEventListener('change', placeDetail);
  })();

  /* ---------- Круговая гарантия ---------- */
  (function () {
    var nodes = $$('.dl-node'), segs = $$('.dl-ring__seg'), rc = $('#dl-ringCenter');
    if (!nodes.length || !rc) return;
    function pick(i) {
      nodes.forEach(function (n, k) { n.setAttribute('aria-selected', k === i ? 'true' : 'false'); });
      segs.forEach(function (g, k) { g.classList.toggle('is-on', k === i); });
      var d = nodes[i];
      $('b', rc).textContent = d.getAttribute('data-title');
      $('span', rc).textContent = d.getAttribute('data-text');
      var a = $('a', rc), href = d.getAttribute('data-link');
      if (a) { a.textContent = d.getAttribute('data-label'); a.href = href || '#'; a.hidden = !href; }
    }
    nodes.forEach(function (n, i) {
      n.addEventListener('mouseenter', function () { pick(i); });
      n.addEventListener('focus', function () { pick(i); });
      n.addEventListener('click', function () {
        pick(i);
        var link = n.getAttribute('data-link');
        if (link) location.href = link;
      });
    });
  })();

  /* ---------- Кнопки, ведущие к ближайшей форме (источник лида, «Получить КП») ---------- */
  $$('[data-goto]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var target = $(btn.getAttribute('data-goto'));
      if (!target) return;
      var form = target.matches('form') ? target : $('form', target);
      target.scrollIntoView({ behavior: reduce ? 'auto' : 'smooth', block: 'center' });
      if (!form) return;
      var src = btn.getAttribute('data-src');
      if (src) {
        form.setAttribute('data-source', src); // источник лида уходит в CRM
        var hid = $('.dl-src', form);
        if (hid) hid.value = src;
      }
      if (/^КП · /.test(src || '')) {
        var kp = $('[data-topic="kp"]', form);
        if (kp) kp.checked = true;
        var note = $('textarea', form);
        if (note && !note.value) note.value = src.replace('КП · ', 'Запрос КП: ');
      }
      setTimeout(function () {
        var tel = $('input[data-kind="phone"]', form);
        if (tel) tel.focus({ preventScroll: true });
      }, reduce ? 0 : 600);
    });
  });

  /* ---------- Якорное меню: подсветка текущей секции ---------- */
  (function () {
    var bar = $('#dl-anchors');
    if (!bar || !('IntersectionObserver' in window)) return;
    var links = $$('.dl-anchors__scroll a', bar), scroller = $('.dl-anchors__scroll', bar);
    var secs = links.map(function (a) { return document.querySelector(a.getAttribute('href')); }).filter(Boolean);
    var seen = {};
    var hdr = (document.querySelector('.header') || {}).offsetHeight || 80;
    var offset = hdr + bar.offsetHeight + 20;
    var obs = new IntersectionObserver(function (entries) {
      entries.forEach(function (e) { seen[e.target.id] = e.isIntersecting ? e.intersectionRatio : 0; });
      var best = null, bv = 0;
      Object.keys(seen).forEach(function (id) { if (seen[id] > bv) { bv = seen[id]; best = id; } });
      links.forEach(function (a) {
        var on = !!best && a.getAttribute('href') === '#' + best;
        a.classList.toggle('is-on', on);
        if (on && scroller.scrollWidth > scroller.clientWidth) { // прокручиваем только саму ленту, не страницу
          scroller.scrollTo({ left: a.offsetLeft - scroller.clientWidth / 2 + a.offsetWidth / 2, behavior: reduce ? 'auto' : 'smooth' });
        }
      });
    }, { rootMargin: '-' + offset + 'px 0px -55% 0px', threshold: [0, .15, .4, .75] });
    secs.forEach(function (s) { obs.observe(s); });
  })();

  /* ---------- Фото, которое не загрузилось: остаётся контурный рисунок ---------- */
  $$('.dl-ph img').forEach(function (im) {
    function hide() { im.style.display = 'none'; }
    if (im.complete && im.naturalWidth === 0) hide(); else im.addEventListener('error', hide);
  });

  /* =========================================================
     Формы
     ========================================================= */

  /* Маска телефона +7 (000) 000-00-00 — тот же формат, что у форм сайта */
  function digits10(v) {
    var d = v.replace(/\D/g, '');
    if (d.charAt(0) === '8' || d.charAt(0) === '7') d = d.slice(1);
    return d.slice(0, 10);
  }
  function maskPhone(v) {
    var d = digits10(v);
    if (!d) return '';
    var out = '+7 (' + d.slice(0, 3);
    if (d.length > 3) out += ') ' + d.slice(3, 6);
    if (d.length > 6) out += '-' + d.slice(6, 8);
    if (d.length > 8) out += '-' + d.slice(8, 10);
    return out;
  }
  $$('input[data-kind="phone"]').forEach(function (inp) {
    inp.addEventListener('input', function () { inp.value = maskPhone(inp.value); });
  });

  /* Валидация — те же правила, что у остальных форм сайта */
  var rules = {
    phone: function (v) { return digits10(v).length === 10; },
    contact: function (v) { return /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/.test(v) || (v.replace(/\D/g, '').length >= 10 && /^[\d+()\-\s]+$/.test(v)); },
    email: function (v) { return /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/.test(v); },
    name: function (v) { return /^[А-Яа-яA-Za-zёЁ \-]+$/.test(v); }
  };
  var messages = { phone: 'Введите номер телефона', contact: 'Введите телефон или e-mail', email: 'Введите почту', name: 'Введите имя', required: 'Заполните поле' };

  function setError(input, msg) {
    var box = input.closest('.dl-field') || input.closest('.dl-file');
    if (!box) return;
    var old = box.querySelector('.dl-field__err');
    if (old) old.remove();
    box.classList.toggle('is-error', !!msg);
    if (msg) {
      var p = document.createElement('span');
      p.className = 'dl-field__err';
      p.textContent = msg;
      box.appendChild(p);
    }
  }
  function validateInput(input) {
    var v = input.value.trim(), kind = input.getAttribute('data-kind') || (input.type === 'email' ? 'email' : '');
    var msg = '';
    if (!v) {
      if (input.required) msg = messages[kind] || messages.required;
    } else if (kind && rules[kind] && !rules[kind](v)) {
      msg = messages[kind];
    } else if (input.getAttribute('autocomplete') === 'name' && !rules.name(v)) {
      msg = messages.name;
    }
    setError(input, msg);
    return !msg;
  }
  $$('.dl-form input:not([type=hidden]):not([type=file]):not([type=radio]), .dl-form textarea').forEach(function (inp) {
    inp.addEventListener('blur', function () { validateInput(inp); });
    inp.addEventListener('input', function () { if (inp.closest('.is-error')) validateInput(inp); });
  });

  // Capture на document: срабатывает раньше обработчика dev.js на самой форме и может его остановить
  document.addEventListener('submit', function (e) {
    var form = e.target;
    if (!form.classList || !form.classList.contains('dl-form')) return;
    var ok = true, first = null;
    $$('input:not([type=hidden]):not([type=file]):not([type=radio]), textarea', form).forEach(function (inp) {
      if (!validateInput(inp)) { ok = false; first = first || inp; }
    });
    if (!ok) {
      e.preventDefault();
      e.stopPropagation();
      if (first) first.focus();
      return;
    }
    var err = $('.dl-form-error', form);
    if (err) err.remove();
  }, true);

  /* Вложения: до 3 файлов раскладываем по скрытым input с именами вопросов веб-формы */
  $$('input[type=file][data-files-to]').forEach(function (inp) {
    var label = inp.closest('.dl-file'), text = $('[data-fname]', label), def = text ? text.textContent : '';
    inp.addEventListener('change', function () {
      var form = inp.closest('form'), slots = $$('input[type=file]', $(inp.getAttribute('data-files-to'), form));
      var files = Array.prototype.slice.call(inp.files), max = (parseFloat(inp.getAttribute('data-max')) || 25) * 1024 * 1024;
      label.classList.remove('is-error');
      var oldErr = $('.dl-field__err', label);
      if (oldErr) oldErr.remove();
      slots.forEach(function (s) { s.value = ''; });
      if (!files.length) { if (text) text.textContent = def; return; }
      var tooBig = files.filter(function (f) { return f.size > max; });
      if (tooBig.length) {
        setError(inp, 'Файл больше ' + (max / 1048576) + ' МБ: ' + tooBig[0].name);
        inp.value = '';
        if (text) text.textContent = def;
        return;
      }
      var used = files.slice(0, slots.length);
      used.forEach(function (f, i) {
        try { var dt = new DataTransfer(); dt.items.add(f); slots[i].files = dt.files; } catch (x) { /* старый браузер — файл не прикрепится */ }
      });
      if (text) text.textContent = used.map(function (f) { return f.name; }).join(', ') + (files.length > used.length ? ' (принято первых ' + used.length + ')' : '');
    });
    inp.closest('form').addEventListener('form:success', function () { if (text) text.textContent = def; });
  });

  /* Результат отправки: dev.js шлёт form:success / form:error на саму форму */
  $$('.dl-form').forEach(function (form) {
    form.addEventListener('form:success', function () {
      form.classList.add('is-sent');
      var src = form.getAttribute('data-goal') || form.getAttribute('data-source');
      if (typeof window.ym === 'function' && metrikaId && src) {
        try { window.ym(metrikaId, 'reachGoal', src); } catch (x) { /* счётчик недоступен */ }
      }
      form.setAttribute('data-source', src || '');
    });
    // topic сбрасывается form.reset() на «первый по умолчанию» — отдельных действий не требуется
  });

  /* UTM-метки и Client ID Метрики — в скрытые поля (уходят в CRM вместе с заявкой) */
  (function () {
    var utm = {}, keys = ['source', 'medium', 'campaign', 'content', 'term'];
    try { utm = JSON.parse(sessionStorage.getItem('dl_utm') || '{}'); } catch (x) { utm = {}; }
    var q = new URLSearchParams(location.search), changed = false;
    keys.forEach(function (k) { if (q.get('utm_' + k)) { utm[k] = q.get('utm_' + k); changed = true; } });
    if (changed) { try { sessionStorage.setItem('dl_utm', JSON.stringify(utm)); } catch (x) { /* режим без хранилища */ } }
    keys.forEach(function (k) {
      if (!utm[k]) return;
      $$('input[data-track="utm_' + k + '"]').forEach(function (i) { i.value = utm[k]; });
    });
    if (typeof window.ym === 'function' && metrikaId) {
      try {
        window.ym(metrikaId, 'getClientID', function (id) {
          $$('input[data-track="client_id"]').forEach(function (i) { i.value = id; });
        });
      } catch (x) { /* счётчик недоступен */ }
    }
  })();
})();
