(function (window, document) {
  'use strict';

  var settings = window.bankitosFormSubmit || {};
  var loadingLabel = settings.loadingLabel || 'Enviando...';

  function getSubmitElements(form) {
    return form.querySelectorAll('button[type="submit"], input[type="submit"]');
  }

  function setButtonLoading(button, isLoading) {
    if (!button) {
      return;
    }
    if (isLoading) {
      if (!button.dataset.bankitosOriginalLabel) {
        if (button.tagName === 'INPUT') {
          button.dataset.bankitosOriginalLabel = button.value || '';
        } else {
          button.dataset.bankitosOriginalLabel = button.textContent || '';
        }
      }
      if (button.tagName === 'INPUT') {
        button.value = loadingLabel;
      } else {
        button.textContent = loadingLabel;
      }
      button.classList.add('is-loading');
      button.setAttribute('aria-busy', 'true');
      button.disabled = true;
    } else {
      var original = button.dataset.bankitosOriginalLabel;
      if (original !== undefined) {
        if (button.tagName === 'INPUT') {
          button.value = original;
        } else {
          button.textContent = original;
        }
      }
      button.classList.remove('is-loading');
      button.removeAttribute('aria-busy');
      button.disabled = false;
    }
  }

  function setFormLoading(form, isLoading) {
    var buttons = getSubmitElements(form);
    buttons.forEach(function (button) {
      setButtonLoading(button, isLoading);
    });
  }

  function shouldKeepLoading(form) {
    return form.dataset.bankitosRecaptchaRunning === '1';
  }

  function clearSubmittingState(form) {
    delete form.dataset.bankitosSubmitting;
    form.removeAttribute('aria-busy');
    setFormLoading(form, false);
  }

  function attach(form) {
    if (!form || form.__bankitosSubmitBound) {
      return;
    }
    form.__bankitosSubmitBound = true;

    form.addEventListener('submit', function (event) {
      if (form.dataset.bankitosSubmitting === '1') {
        event.preventDefault();
        event.stopImmediatePropagation();
        return;
      }

      if (event.defaultPrevented && !shouldKeepLoading(form)) {
        return;
      }

      form.dataset.bankitosSubmitting = '1';
      form.setAttribute('aria-busy', 'true');
      setFormLoading(form, true);

      window.setTimeout(function () {
        if (event.defaultPrevented && !shouldKeepLoading(form)) {
          clearSubmittingState(form);
        }
      }, 0);
    });
  }

  function init() {
    var forms = document.querySelectorAll('form');
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