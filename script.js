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

  var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* ---------- Illustrationen: zeichnen, sobald sie gut sichtbar sind ---------- */
  // Jeder Strich bekommt eine Verzögerung (--dl), damit sich die Szene nacheinander aufbaut.
  // Verlässt die Illustration den Bildschirm, wird sie zurückgesetzt und beim nächsten Mal neu gezeichnet.
  var illus = document.querySelectorAll('.illu-anim, .hero-illu, .page-hero-illu');
  illus.forEach(function (el) {
    el.classList.add('illu-anim');
    var parts = el.querySelectorAll('.illu .s, .illu [class*="f"]');
    parts.forEach(function (part, i) {
      part.style.setProperty('--dl', (i / parts.length * 2).toFixed(2) + 's');
    });
  });
  if (illus.length) {
    if (reduceMotion || !('IntersectionObserver' in window)) {
      illus.forEach(function (el) { el.classList.add('in'); });
    } else {
      var drawObs = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
          if (entry.intersectionRatio >= 0.45) { entry.target.classList.add('in'); }
          else if (entry.intersectionRatio === 0) { entry.target.classList.remove('in'); }
        });
      }, { threshold: [0, 0.45] });
      illus.forEach(function (el) { drawObs.observe(el); });
    }
  }

  /* ---------- Zähler (Hero-Kennzahl) ---------- */
  document.querySelectorAll('[data-count]').forEach(function (el) {
    var target = parseInt(el.getAttribute('data-count'), 10);
    if (reduceMotion || !target) { return; }
    var start = null;
    var step = function (t) {
      if (!start) { start = t; }
      var p = Math.min((t - start) / 1800, 1);
      el.textContent = Math.round(target * (1 - Math.pow(1 - p, 3)));
      if (p < 1) { requestAnimationFrame(step); }
    };
    el.textContent = '0';
    if ('IntersectionObserver' in window) {
      var countObs = new IntersectionObserver(function (entries) {
        if (entries[0].isIntersecting) { countObs.disconnect(); requestAnimationFrame(step); }
      }, { threshold: 1 });
      countObs.observe(el);
    } else { requestAnimationFrame(step); }
  });

  /* ---------- Parallax & Zeitstrahl-Fortschritt ---------- */
  var parallax = document.querySelectorAll('[data-parallax]');
  var timeline = document.querySelector('.timeline');
  if (!reduceMotion && (parallax.length || timeline)) {
    var ticking = false;
    var update = function () {
      var y = window.scrollY;
      parallax.forEach(function (el) {
        el.style.transform = 'translateY(' + (y * parseFloat(el.getAttribute('data-parallax'))) + 'px)';
      });
      if (timeline) {
        var r = timeline.getBoundingClientRect();
        var p = (window.innerHeight * 0.6 - r.top) / r.height;
        timeline.style.setProperty('--progress', Math.max(0, Math.min(1, p)).toFixed(3));
      }
      ticking = false;
    };
    window.addEventListener('scroll', function () {
      if (!ticking) { requestAnimationFrame(update); ticking = true; }
    }, { passive: true });
    update();
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
      }, { threshold: 0.2, rootMargin: '0px 0px -12% 0px' });
      items.forEach(function (el, i) {
        el.style.setProperty('--d', ((i % 4) * 0.12) + 's');
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

  /* ---------- Adresse kopieren (Kontaktseite) ---------- */
  document.querySelectorAll('[data-copy]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var text = btn.getAttribute('data-copy');
      var status = btn.parentNode.querySelector('.copy-status');
      var done = function (ok) { if (status) { status.textContent = ok ? 'Adresse kopiert.' : text; } };
      if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(text).then(function () { done(true); }, function () { done(false); });
      } else { done(false); }
    });
  });

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
