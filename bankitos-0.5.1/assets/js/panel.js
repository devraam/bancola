(function(){
  const doc = document;
  const selectors = {
    section: '[data-bankitos-invite]',
    open: '[data-bankitos-invite-open]',
    panel: '[data-bankitos-invite-panel]',
    close: '[data-bankitos-invite-close]',
    form: '[data-bankitos-invite-form]',
    rows: '[data-bankitos-invite-rows]',
    add: '[data-bankitos-invite-add]',
    remove: '[data-bankitos-invite-remove]',
    error: '[data-bankitos-invite-error]',
    editToggle: '[data-bankitos-invite-edit-toggle]',
    editForm: '[data-bankitos-invite-edit-form]',
    editCancel: '[data-bankitos-invite-edit-cancel]'
  };

  const messages = window.bankitosPanelInvites || {};

  function createRow(){
    const wrapper = doc.createElement('div');
    wrapper.className = 'bankitos-invite-row';
    wrapper.setAttribute('data-bankitos-invite-row','');
    wrapper.innerHTML = '' +
      '<div class="bankitos-field">' +
        '<label>' + (messages.nameLabel || 'Nombre') + '</label>' +
        '<input type="text" name="invite_name[]" required>' +
      '</div>' +
      '<div class="bankitos-field">' +
        '<label>' + (messages.emailLabel || 'Correo electrónico') + '</label>' +
        '<input type="email" name="invite_email[]" required>' +
      '</div>' +
      '<button type="button" class="bankitos-invite-row__remove" data-bankitos-invite-remove aria-label="' + (messages.removeLabel || 'Eliminar fila') + '">×</button>';
    return wrapper;
  }

  function togglePanel(panel, show){
    if (!panel) return;
    if (show){
      panel.removeAttribute('hidden');
      panel.classList.add('is-visible');
      const firstInput = panel.querySelector('input');
      if (firstInput){
        firstInput.focus();
      }
    } else {
      panel.setAttribute('hidden','');
      panel.classList.remove('is-visible');
    }
  }

  function showError(container, message){
    if (!container) return;
    container.textContent = message || '';
    if (message){
      container.classList.add('is-visible');
    } else {
      container.classList.remove('is-visible');
    }
  }

  function validateForm(form){
    const minRequired = parseInt(form.getAttribute('data-min-required') || '1', 10);
    const rows = Array.from(form.querySelectorAll('[data-bankitos-invite-row]'));
    let completed = 0;
    let firstInvalid = null;
    const emailRegex = /[^@\s]+@[^@\s]+\.[^@\s]+/;

    rows.forEach(row => {
      const inputs = row.querySelectorAll('input');
      const nameInput = inputs[0];
      const emailInput = inputs[1];
      const nameVal = nameInput ? nameInput.value.trim() : '';
      const emailVal = emailInput ? emailInput.value.trim() : '';
      const hasName = !!nameVal;
      const hasEmail = !!emailVal;

      if ((hasName && !hasEmail) || (!hasName && hasEmail)) {
        if (!firstInvalid) firstInvalid = hasName ? emailInput : nameInput;
      }
      if (hasName && hasEmail) {
        if (!emailRegex.test(emailVal)) {
          if (!firstInvalid) firstInvalid = emailInput;
        } else {
          completed++;
        }
      }
    });

    if (completed < minRequired) {
      return { valid: false, message: messages.minRequiredError || 'Debes completar el número mínimo de invitaciones.' };
    }
    if (firstInvalid) {
      firstInvalid.focus();
      return { valid: false, message: messages.missingFieldsError || 'Completa nombre y correo en cada fila.' };
    }
    if (completed) {
      for (const row of rows) {
        const emailInput = row.querySelector('input[type="email"]');
        if (emailInput && emailInput.value.trim() && !emailRegex.test(emailInput.value.trim())) {
          emailInput.focus();
          return { valid: false, message: messages.invalidEmailError || 'Ingresa correos electrónicos válidos.' };
        }
      }
    }
    return { valid: true };
  }

  function initInvitePanels(){
    const sections = doc.querySelectorAll(selectors.section);
    if (!sections.length) return;

    sections.forEach(section => {
      const panel = section.querySelector(selectors.panel);
      if (!panel) return;

      const toggleBtn = section.querySelector(selectors.open);
      const form = panel.querySelector(selectors.form);
      const rowsContainer = panel.querySelector(selectors.rows);
      const addBtn = panel.querySelector(selectors.add);
      const errorBox = panel.querySelector(selectors.error);
      const closeElements = panel.querySelectorAll(selectors.close);

      if (toggleBtn){
        toggleBtn.addEventListener('click', () => {
          const willShow = panel.hasAttribute('hidden');
          togglePanel(panel, willShow);
          toggleBtn.setAttribute('aria-expanded', willShow ? 'true' : 'false');
        });
      }
      closeElements.forEach(el => {
          el.addEventListener('click', () => {
            togglePanel(panel, false);
            if (toggleBtn){
              toggleBtn.setAttribute('aria-expanded', 'false');
              toggleBtn.focus();
            }
          });
        });
    if (addBtn && rowsContainer){
        addBtn.addEventListener('click', (event) => {
          event.preventDefault();
          rowsContainer.appendChild(createRow());
        });
      }

      if (rowsContainer){
        rowsContainer.addEventListener('click', (event) => {
          const target = event.target;
          if (target && target.matches(selectors.remove)) {
            event.preventDefault();
            const row = target.closest('[data-bankitos-invite-row]');
            if (row && rowsContainer.children.length > 1) {
              row.remove();
            }
          }
        });
      }

    if (form){
        form.addEventListener('submit', (event) => {
          const result = validateForm(form);
          if (!result.valid) {
            event.preventDefault();
            showError(errorBox, result.message);
          } else {
            showError(errorBox, '');
          }
        });
      }
    });
  }

  function initEditForms(){
    const toggles = doc.querySelectorAll(selectors.editToggle);
    if (!toggles.length) return;

    toggles.forEach(toggle => {
      const targetId = toggle.getAttribute('aria-controls');
      const form = targetId ? doc.getElementById(targetId) : null;
      if (!form) return;

      toggle.addEventListener('click', () => {
        const willShow = form.hasAttribute('hidden');
        if (willShow) {
          form.removeAttribute('hidden');
          const firstInput = form.querySelector('input[type="text"], input[type="email"], input');
          if (firstInput) {
            firstInput.focus();
          }
        } else {
          form.setAttribute('hidden', '');
        }
        toggle.setAttribute('aria-expanded', willShow ? 'true' : 'false');
      });

      form.addEventListener('click', event => {
        const target = event.target;
        if (target && target.matches(selectors.editCancel)) {
          event.preventDefault();
          form.setAttribute('hidden', '');
          toggle.setAttribute('aria-expanded', 'false');
          toggle.focus();
        }
      });
    });
  }

  function init(){
    initInvitePanels();
    initEditForms();
  }

  if (document.readyState === 'loading'){
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();