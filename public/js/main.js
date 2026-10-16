/* DevGigs - small, event-driven script. Parallax uses CSS scroll-driven animation; the JS fallback below only runs where that is unsupported. */
(function () {
  'use strict';
  var root = document.documentElement;
  var EMAIL_RE = /^[^\s@]+@[^\s@]+\.[A-Za-z]{2,}$/;
  var PHONE_RE = /^\+?[0-9\s\-]{7,20}$/;

  /* Same rules as includes/validation.php */
  var PASSWORD_RULES = {
    len: function (v) { return v.length >= 8 && v.length <= 72; },
    upper: function (v) { return /[A-Z]/.test(v); },
    lower: function (v) { return /[a-z]/.test(v); },
    num: function (v) { return /[0-9]/.test(v); }
  };

  function emailMessage(v) {
    if (!v) return 'Email is required.';
    if (v.indexOf('@') === -1) return 'Email must contain an @ symbol.';
    if (!EMAIL_RE.test(v)) return 'Enter a valid email, like name@example.com.';
    return '';
  }

  function show(input, message) {
    input.setCustomValidity(message);
    var box = input.closest('.field');
    var out = box && box.querySelector('.field-msg');
    if (out) out.textContent = input.dataset.touched ? message : '';
    input.setAttribute('aria-invalid', message && input.dataset.touched ? 'true' : 'false');
  }

  function wire(input, getMessage, extra) {
    function run() { show(input, getMessage(input.value.trim(), input.value)); if (extra) extra(); }
    input.addEventListener('input', run);
    input.addEventListener('blur', function () { input.dataset.touched = '1'; run(); });
    run();
  }

  /* Theme preference is stored in a cookie so PHP can render the right theme on the next page */
  document.addEventListener('click', function (e) {
    var toggle = e.target.closest('[data-theme-toggle]');
    if (toggle) {
      var next = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
      root.setAttribute('data-theme', next);
      document.cookie = 'theme=' + next + '; path=/; max-age=31536000; SameSite=Lax';
      return;
    }
    var eye = e.target.closest('[data-toggle-pw]');
    if (eye) {
      var field = document.getElementById(eye.getAttribute('data-toggle-pw'));
      var hidden = field.type === 'password';
      field.type = hidden ? 'text' : 'password';
      eye.textContent = hidden ? 'Hide' : 'Show';
    }
  });

  document.addEventListener('change', function (e) {
    if (e.target.matches('[data-autosubmit]')) e.target.form.submit();
  });

  document.addEventListener('submit', function (e) {
    var message = e.target.getAttribute('data-confirm');
    if (message && !window.confirm(message)) e.preventDefault();
  });


  /* Parallax fallback for browsers without CSS scroll-driven animations.
     Passive listener + requestAnimationFrame, transform/opacity only, paused while the hero is off screen. */
  function initParallaxFallback() {
    var hero = document.querySelector('[data-parallax]');
    if (!hero) return;
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
    if (window.CSS && CSS.supports && CSS.supports('animation-timeline: scroll()')) return;

    var bg = hero.querySelector('.hero-bg');
    var copy = hero.querySelector('.hero-copy');
    var height = hero.offsetHeight, visible = true, ticking = false;

    function frame() {
      var y = Math.min(window.scrollY, height);
      bg.style.transform = 'translate3d(0,' + (y * 0.13).toFixed(1) + 'px,0)';
      copy.style.transform = 'translate3d(0,' + Math.min(y * 0.12, 48).toFixed(1) + 'px,0)';
      copy.style.opacity = Math.max(1 - y / (height * 0.85), 0.15).toFixed(2);
      ticking = false;
    }
    window.addEventListener('scroll', function () {
      if (visible && !ticking) { ticking = true; requestAnimationFrame(frame); }
    }, { passive: true });
    window.addEventListener('resize', function () { height = hero.offsetHeight; });
    if ('IntersectionObserver' in window) {
      new IntersectionObserver(function (entries) { visible = entries[0].isIntersecting; }).observe(hero);
    }
  }

  document.addEventListener('DOMContentLoaded', function () {
    initParallaxFallback();
    document.querySelectorAll('input[data-validate="email"]').forEach(function (input) {
      wire(input, emailMessage);
    });

    document.querySelectorAll('input[data-validate="phone"]').forEach(function (input) {
      wire(input, function (v) {
        return PHONE_RE.test(v) ? '' : 'Use 7-20 digits. A leading + and spaces or dashes are fine.';
      });
    });

    document.querySelectorAll('input[data-validate="password"]').forEach(function (input) {
      var form = input.form;
      var list = form.querySelector('.rules');
      var meter = form.querySelector('.meter i');
      var confirm = form.querySelector('input[data-validate="confirm"]');

      function confirmCheck() {
        if (confirm) show(confirm, confirm.value === input.value ? '' : 'Passwords do not match.');
      }
      wire(input, function (v, raw) {
        var passed = 0;
        Object.keys(PASSWORD_RULES).forEach(function (key) {
          var ok = PASSWORD_RULES[key](raw);
          if (ok) passed++;
          var item = list && list.querySelector('[data-rule="' + key + '"]');
          if (item) item.classList.toggle('ok', ok);
        });
        if (meter) meter.setAttribute('data-level', passed);
        return passed === 4 ? '' : 'Password needs 8-72 characters, an uppercase letter, a lowercase letter and a number.';
      }, confirmCheck);

      if (confirm) {
        confirm.addEventListener('input', confirmCheck);
        confirm.addEventListener('blur', function () { confirm.dataset.touched = '1'; confirmCheck(); });
      }
    });
  });
})();
