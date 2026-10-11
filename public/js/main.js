/* DevGigs - small, event-driven script. No scroll listeners, no animation loops. */
(function () {
  'use strict';
  var root = document.documentElement;
  var EMAIL_RE = /^[^\s@]+@[^\s@]+\.[A-Za-z]{2,}$/;
  var PHONE_RE = /^\+?[0-9\s\-]{7,20}$/;

  /* Same rules as includes/validation.php */
  var PASSWORD_RULES = {
    len:   function (v) { return v.length >= 8 && v.length <= 72; },
    upper: function (v) { return /[A-Z]/.test(v); },
    lower: function (v) { return /[a-z]/.test(v); },
    num:   function (v) { return /[0-9]/.test(v); }
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

  document.addEventListener('DOMContentLoaded', function () {
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
