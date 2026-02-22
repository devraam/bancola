(function (window, document) {
  'use strict';

  var settings = window.bankitosRecaptcha || {};
  var siteKey = settings.siteKey || '';
  if (!siteKey) {
    return;
  }

  function whenReady(callback, attempts, onFail) {
    if (window.grecaptcha && typeof window.grecaptcha.ready === 'function') {
      window.grecaptcha.ready(callback);
      return;
    }
    if (typeof attempts === 'number' && attempts <= 0) {
      if (typeof onFail === 'function') {
        onFail();
      }
      return;
    }
    window.setTimeout(function () {
      whenReady(callback, typeof attempts === 'number' ? attempts - 1 : 20, onFail);
    }, 150);
  }

  function attach(form) {
    if (!form || form.__bankitosRecaptchaBound) {
      return;
    }
    form.__bankitosRecaptchaBound = true;

    form.addEventListener('submit', function (event) {
      if (event.defaultPrevented) {
        return;
      }
      if (form.dataset.bankitosRecaptchaComplete === '1') {
        return;
      }
      event.preventDefault();
      if (form.dataset.bankitosRecaptchaRunning === '1') {
        return;
      }
      form.dataset.bankitosRecaptchaRunning = '1';
      var action = form.getAttribute('data-bankitos-recaptcha') || 'submit';
      var tokenField = form.querySelector('[data-bankitos-recaptcha-token]');

      whenReady(function () {
        window.grecaptcha.execute(siteKey, { action: action }).then(function (token) {
          if (tokenField) {
            tokenField.value = token || '';
          }
          form.dataset.bankitosRecaptchaRunning = '0';
          form.dataset.bankitosRecaptchaComplete = '1';
          form.submit();
        }).catch(function () {
          form.dataset.bankitosRecaptchaRunning = '0';
        });
      }, 20, function () {
        form.dataset.bankitosRecaptchaRunning = '0';
      });
    });
  }

  function init() {
    var forms = document.querySelectorAll('form[data-bankitos-recaptcha]');
    for (var i = 0; i < forms.length; i++) {
      attach(forms[i]);
    }
  }

  if (document.readyState === 'complete' || document.readyState === 'interactive') {
    window.setTimeout(init, 0);
  } else {
    document.addEventListener('DOMContentLoaded', init);
  }
})(window, document);