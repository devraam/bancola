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

  // Al deshabilitar un botón de submit, el navegador NO incluye su par
  // name/value en el envío del formulario (los controles deshabilitados se
  // omiten). Algunos formularios (p. ej. la firma de créditos) transportan un
  // dato crítico —como la decisión "approved"/"rejected"— en el value del
  // botón. Para que ese valor sobreviva a la deshabilitación, lo replicamos en
  // un input hidden justo antes de poner el botón en estado de carga.
  function preserveSubmitterValue(form, submitter) {
    if (!form || !submitter || !submitter.name) {
      return;
    }
    var previous = form.querySelector('input[data-bankitos-submitter="1"]');
    if (previous && previous.parentNode) {
      previous.parentNode.removeChild(previous);
    }
    var hidden = document.createElement('input');
    hidden.type = 'hidden';
    hidden.name = submitter.name;
    hidden.value = submitter.value;
    hidden.setAttribute('data-bankitos-submitter', '1');
    form.appendChild(hidden);
  }

  // Fallback para navegadores/webviews antiguos sin event.submitter:
  // recordamos el último botón de submit activado por el usuario.
  function isSubmitControl(el) {
    if (!el || !el.matches) {
      return false;
    }
    return el.matches('button[type="submit"], input[type="submit"], button:not([type])');
  }

  function rememberSubmitter(form, event) {
    var node = event.target;
    while (node && node !== form) {
      if (isSubmitControl(node)) {
        form.__bankitosSubmitter = node;
        return;
      }
      node = node.parentNode;
    }
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

    form.addEventListener('click', function (event) {
      rememberSubmitter(form, event);
    }, true);

    form.addEventListener('submit', function (event) {
      if (form.dataset.bankitosSubmitting === '1') {
        event.preventDefault();
        event.stopImmediatePropagation();
        return;
      }

      if (event.defaultPrevented && !shouldKeepLoading(form)) {
        return;
      }

      // Conservar el value del botón pulsado ANTES de deshabilitarlo.
      preserveSubmitterValue(form, event.submitter || form.__bankitosSubmitter);
      form.__bankitosSubmitter = null;

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