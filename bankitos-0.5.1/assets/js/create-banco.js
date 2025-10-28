(function (window, document) {
  'use strict';

  var data = window.bankitosCreateBanco;
  if (!data || !data.config) {
    return;
  }

  var cfg = data.config;
  var messages = data.messages || {};
  var form = document.querySelector(cfg.form);
  if (!form) {
    return;
  }

  var submitBtn = document.querySelector(cfg.submit);
  var fields = {};
  var wrappers = {};
  var errors = {};

  function resolveMap(source, target) {
    Object.keys(source || {}).forEach(function (key) {
      var selector = source[key];
      if (typeof selector !== 'string') {
        return;
      }
      var element = document.querySelector(selector);
      if (element) {
        target[key] = element;
      }
    });
  }

  resolveMap(cfg.fields, fields);
  resolveMap(cfg.wrappers, wrappers);
  resolveMap(cfg.errors, errors);

  function setError(key, message) {
    var wrap = wrappers[key];
    var errorEl = errors[key];
    if (!wrap) {
      return;
    }
    if (message) {
      wrap.classList.add('has-error');
      if (errorEl) {
        errorEl.textContent = message;
        errorEl.style.display = 'block';
      }
    } else {
      wrap.classList.remove('has-error');
      if (errorEl) {
        errorEl.textContent = '';
        errorEl.style.display = 'none';
      }
    }
  }

  function getValue(key) {
    var field = fields[key];
    return field ? String(field.value || '').trim() : '';
  }

  function msg(key, fallback) {
    return messages[key] || fallback;
  }

  function validateNombre() {
    return getValue('nombre') ? '' : msg('required', '');
  }

  function validateObjetivo() {
    return getValue('objetivo') ? '' : msg('required', '');
  }

  function validateCuota() {
    var value = getValue('cuota');
    if (!value) {
      return msg('required', '');
    }
    var number = parseFloat(value.replace(',', '.'));
    if (!isFinite(number)) {
      return msg('number', '');
    }
    if (cfg.limits && typeof cfg.limits.cuotaMin === 'number' && number < cfg.limits.cuotaMin) {
      return msg('cuotaMin', '');
    }
    return '';
  }

  function validatePer() {
    return getValue('per') ? '' : msg('periodRequired', msg('required', ''));
  }

  function validateTasa() {
    var value = getValue('tasa');
    if (!value) {
      return msg('required', '');
    }
    var number = parseFloat(value.replace(',', '.'));
    if (!isFinite(number)) {
      return msg('number', '');
    }
    if (cfg.limits) {
      if (typeof cfg.limits.tasaMin === 'number' && number < cfg.limits.tasaMin) {
        return msg('tasaRange', '');
      }
      if (typeof cfg.limits.tasaMax === 'number' && number > cfg.limits.tasaMax) {
        return msg('tasaRange', '');
      }
      if (typeof cfg.limits.tasaStep === 'number') {
        var rounded = Math.round(number / cfg.limits.tasaStep) * cfg.limits.tasaStep;
        if (Math.abs(number - rounded) > 1e-9) {
          return msg('tasaStep', '');
        }
      }
    }
    return '';
  }

  function validateDur() {
    return getValue('dur') ? '' : msg('required', '');
  }

  var validators = {
    nombre: validateNombre,
    objetivo: validateObjetivo,
    cuota: validateCuota,
    per: validatePer,
    tasa: validateTasa,
    dur: validateDur
  };

  function runValidation() {
    var valid = true;
    Object.keys(validators).forEach(function (key) {
      var error = validators[key]();
      setError(key, error);
      if (error) {
        valid = false;
      }
    });

    if (submitBtn) {
      if (valid) {
        submitBtn.removeAttribute('disabled');
        submitBtn.classList.remove('is-disabled');
      } else {
        submitBtn.setAttribute('disabled', 'disabled');
        submitBtn.classList.add('is-disabled');
      }
    }
    return valid;
  }

  ['input', 'change', 'blur'].forEach(function (eventName) {
    Object.keys(fields).forEach(function (key) {
      fields[key].addEventListener(eventName, runValidation);
    });
  });

  form.addEventListener('submit', function (event) {
    if (!runValidation()) {
      event.preventDefault();
      var firstError = form.querySelector('.has-error input, .has-error select, .has-error textarea');
      if (firstError && typeof firstError.focus === 'function') {
        firstError.focus({ preventScroll: false });
        if (typeof firstError.scrollIntoView === 'function') {
          firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
      }
      form.setAttribute('data-bankitos-invalid', '1');
      form.setAttribute('data-bankitos-message', msg('focusMessage', ''));
    }
  });

  runValidation();
})(window, document);