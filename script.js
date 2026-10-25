/* HIS Objektmanagement – gemeinsames Script (kein Tracking, keine Drittanbieter) */
(function () {
  'use strict';

  /* ---------- Burger-Menü ---------- */
  var toggle = document.querySelector('.nav-toggle');
  var nav = document.getElementById('site-nav');
  if (toggle && nav) {
    var setOpen = function (open) {
      toggle.setAttribute('aria-expanded', String(open));
      toggle.setAttribute('aria-label', open ? 'Menü schließen' : 'Menü öffnen');
      nav.classList.toggle('open', open);
      document.body.classList.toggle('nav-open', open);
    };
    toggle.addEventListener('click', function () {
      setOpen(toggle.getAttribute('aria-expanded') !== 'true');
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') { setOpen(false); }
    });
    nav.addEventListener('click', function (e) {
      if (e.target.tagName === 'A') { setOpen(false); }
    });
  }

  /* ---------- Header-Zustand beim Scrollen ---------- */
  var header = document.querySelector('.site-header');
  if (header) {
    var onScroll = function () { header.classList.toggle('scrolled', window.scrollY > 8); };
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
  }

  /* ---------- Inhaltsverzeichnis: aktiven Abschnitt markieren ---------- */
  var tocLinks = document.querySelectorAll('.toc a[href^="#"]');
  if (tocLinks.length && 'IntersectionObserver' in window) {
    var byId = {};
    tocLinks.forEach(function (a) { byId[a.getAttribute('href').slice(1)] = a; });
    var spy = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (!entry.isIntersecting) { return; }
        tocLinks.forEach(function (a) { a.removeAttribute('aria-current'); });
        var link = byId[entry.target.id];
        if (link) { link.setAttribute('aria-current', 'true'); }
      });
    }, { rootMargin: '-35% 0px -60% 0px' });
    Object.keys(byId).forEach(function (id) {
      var sec = document.getElementById(id);
      if (sec) { spy.observe(sec); }
    });
  }

  /* ---------- Scroll-Reveal ---------- */
  var items = document.querySelectorAll('.reveal');
  var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  if (items.length) {
    if (!('IntersectionObserver' in window) || reduce) {
      items.forEach(function (el) { el.classList.add('in'); });
    } else {
      var io = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
          if (entry.isIntersecting) {
            entry.target.classList.add('in');
            io.unobserve(entry.target);
          }
        });
      }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });
      items.forEach(function (el, i) {
        el.style.setProperty('--d', ((i % 4) * 0.08) + 's');
        io.observe(el);
      });
    }
  }

  /* ---------- Leistung per URL-Parameter vorauswählen ---------- */
  var params = new URLSearchParams(window.location.search);
  var preset = (params.get('leistung') || '').split(',').map(function (s) { return s.trim(); });
  if (preset.length) {
    document.querySelectorAll('input[name="leistung"]').forEach(function (box) {
      if (preset.indexOf(box.value) !== -1) { box.checked = true; }
    });
  }

  /* ---------- Formulare: Validierung, Honeypot, Versand ---------- */
  var PLACEHOLDER = /^\[PLATZHALTER/;
  var MAIL = 'kontakt@hisobjekt.de';

  function setError(field, invalid) {
    if (!field) { return; }
    field.classList.toggle('invalid', invalid);
    var ctl = field.querySelector('input, textarea, fieldset');
    if (ctl) { ctl.setAttribute('aria-invalid', invalid ? 'true' : 'false'); }
  }

  function validate(form) {
    var ok = true, first = null;
    form.querySelectorAll('.field[data-required]').forEach(function (field) {
      var type = field.getAttribute('data-required');
      var bad = false;
      if (type === 'group') {
        bad = !field.querySelector('input:checked');
      } else {
        var el = field.querySelector('input, textarea');
        var v = el.value.trim();
        if (type === 'check') { bad = !el.checked; }
        else if (type === 'email') { bad = !/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(v); }
        else { bad = v === ''; }
      }
      setError(field, bad);
      if (bad && !first) { first = field.querySelector('input, textarea'); }
      if (bad) { ok = false; }
    });
    if (first) { first.focus(); }
    return ok;
  }

  function mailtoBody(form) {
    var d = new FormData(form);
    var lines = [
      'Name: ' + (d.get('name') || ''),
      'Telefon: ' + (d.get('telefon') || ''),
      'E-Mail: ' + (d.get('email') || ''),
      'Objektort / PLZ: ' + (d.get('ort') || '')
    ];
    var l = d.getAll('leistung');
    if (l.length) {
      var labels = l.map(function (v) {
        var box = form.querySelector('input[name="leistung"][value="' + v + '"]');
        return box ? box.getAttribute('data-label') : v;
      });
      lines.push('Leistung(en): ' + labels.join(', '));
    }
    if (d.get('zeitraum')) { lines.push('Gewünschter Zeitraum: ' + d.get('zeitraum')); }
    lines.push('', 'Nachricht:', d.get('nachricht') || '');
    return lines.join('\n');
  }

  document.querySelectorAll('form[data-form]').forEach(function (form) {
    var status = form.querySelector('.form-status');
    var say = function (msg) { if (status) { status.textContent = msg; status.hidden = false; } };

    form.addEventListener('input', function (e) {
      var field = e.target.closest('.field');
      if (field && field.classList.contains('invalid')) { setError(field, false); }
    });

    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var hp = form.querySelector('.hp input');
      if (hp && hp.value) { say('Vielen Dank für Ihre Anfrage.'); form.reset(); return; } // Spam-Bot
      if (!validate(form)) { say('Bitte prüfen Sie die markierten Felder.'); return; }

      var action = form.getAttribute('action') || '';
      if (PLACEHOLDER.test(action)) {
        // Fallback, solange kein Formular-Endpunkt hinterlegt ist: E-Mail-Programm öffnen
        var subject = form.getAttribute('data-subject') || 'Anfrage über hisobjekt.de';
        window.location.href = 'mailto:' + MAIL + '?subject=' + encodeURIComponent(subject) +
          '&body=' + encodeURIComponent(mailtoBody(form));
        say('Ihr E-Mail-Programm wird geöffnet. Bitte senden Sie die vorbereitete Nachricht dort ab. Alternativ erreichen Sie uns unter 0176 72198299.');
      } else {
        form.submit();
      }
    });
  });
})();
