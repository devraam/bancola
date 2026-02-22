(function (window, document) {
  'use strict';

  var settings = window.bankitosMinAmountValidation || {};
  var messageTemplate = settings.message || 'Monto mínimo permitido: {min}';

  function formatMessage(min) {
    return messageTemplate.replace('{min}', min);
  }

  function parseValue(value) {
    if (typeof value === 'number') {
      return value;
    }
    if (typeof value !== 'string') {
      return NaN;
    }
    return parseFloat(value.replace(',', '.'));
  }

  function getErrorElement(field, form) {
    var wrapper = field.closest('.bankitos-field');
    if (wrapper) {
      return wrapper.querySelector('[data-bankitos-min-error]') || wrapper.querySelector('.bankitos-field-error');
    }
    return form.querySelector('[data-bankitos-min-error]');
  }

  function setFieldError(field, form, hasError, message) {
    var wrapper = field.closest('.bankitos-field');
    var errorElement = getErrorElement(field, form);
    if (errorElement && message) {
      errorElement.textContent = message;
    }
    if (wrapper) {
      wrapper.classList.toggle('has-error', hasError);
    }
    if (errorElement) {
      errorElement.hidden = !hasError;
    }
  }

  function validateField(field, form) {
    var minAttr = field.getAttribute('data-bankitos-min-amount');
    var minValue = minAttr ? parseFloat(minAttr) : 0;
    if (!minValue) {
      setFieldError(field, form, false, '');
      return false;
    }
    if (field.type !== 'hidden' && field.value === '') {
      setFieldError(field, form, false, '');
      return false;
    }
    var value = parseValue(field.value);
    var hasError = isNaN(value) || value < minValue;
    if (hasError) {
      setFieldError(field, form, true, formatMessage(minValue));
    } else {
      setFieldError(field, form, false, '');
    }
    return hasError;
  }

  function setSubmitDisabled(form, disabled) {
    var buttons = form.querySelectorAll('button[type="submit"], input[type="submit"]');
    buttons.forEach(function (button) {
      if (disabled) {
        button.dataset.bankitosMinDisabled = '1';
        button.disabled = true;
        button.setAttribute('aria-disabled', 'true');
      } else if (button.dataset.bankitosMinDisabled === '1') {
        button.disabled = false;
        button.removeAttribute('aria-disabled');
        delete button.dataset.bankitosMinDisabled;
      }
    });
  }

  function validateForm(form) {
    var fields = form.querySelectorAll('[data-bankitos-min-amount]');
    var hasError = false;
    fields.forEach(function (field) {
      hasError = validateField(field, form) || hasError;
    });
    setSubmitDisabled(form, hasError);
    return !hasError;
  }

  function attach(form) {
    if (!form || form.__bankitosMinBound) {
      return;
    }
    form.__bankitosMinBound = true;

    var fields = form.querySelectorAll('[data-bankitos-min-amount]');
    fields.forEach(function (field) {
      field.addEventListener('input', function () {
        validateForm(form);
      });
      field.addEventListener('change', function () {
        validateForm(form);
      });
    });

    form.addEventListener('submit', function (event) {
      if (!validateForm(form)) {
        event.preventDefault();
        event.stopImmediatePropagation();
      }
    });

    validateForm(form);
  }

  function init() {
    var forms = document.querySelectorAll('form[data-bankitos-min-form]');
    forms.forEach(function (form) {
      attach(form);
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})(window, document);