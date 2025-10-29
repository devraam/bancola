(function(){
  const doc = document;
  const selectors = {
    open: '[data-bankitos-invite-open]',
    modal: '[data-bankitos-modal]',
    close: '[data-bankitos-invite-close]',
    form: '[data-bankitos-invite-form]',
    rows: '[data-bankitos-invite-rows]',
    add: '[data-bankitos-invite-add]',
    remove: '[data-bankitos-invite-remove]',
    error: '[data-bankitos-invite-error]'
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

  function toggleModal(modal, show){
    if (!modal) return;
    if (show){
      modal.removeAttribute('hidden');
      doc.body.style.overflow = 'hidden';
    } else {
      modal.setAttribute('hidden','');
      doc.body.style.overflow = '';
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

  function init(){
    const modal = doc.querySelector(selectors.modal);
    if (!modal) return;
    const openBtn = doc.querySelector(selectors.open);
    const closeElements = modal.querySelectorAll(selectors.close);
    const form = modal.querySelector(selectors.form);
    const rowsContainer = modal.querySelector(selectors.rows);
    const addBtn = modal.querySelector(selectors.add);
    const errorBox = modal.querySelector(selectors.error);

    if (openBtn){
      openBtn.addEventListener('click', () => toggleModal(modal, true));
    }
    closeElements.forEach(el => el.addEventListener('click', () => toggleModal(modal, false)));
    modal.addEventListener('click', (event) => {
      if (event.target === modal) {
        toggleModal(modal, false);
      }
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
  }

  if (document.readyState === 'loading'){
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();