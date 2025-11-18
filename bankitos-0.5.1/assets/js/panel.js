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
    editCancel: '[data-bankitos-invite-edit-cancel]',
    defaultActions: '[data-bankitos-invite-default-actions]',
    actionsCell: '[data-invite-actions-cell]',
    editField: '[data-bankitos-invite-edit-field]',
    editDisplay: '[data-bankitos-invite-display]'
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

  function togglePanel(panel, show, toggleBtn){
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
    if (toggleBtn){
      toggleBtn.setAttribute('aria-expanded', show ? 'true' : 'false');
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

      function ensureRows(){
        if (!rowsContainer) return;
        if (!rowsContainer.querySelector('[data-bankitos-invite-row]')){
          rowsContainer.appendChild(createRow());
        }
      }

      ensureRows();

      if (toggleBtn){
        toggleBtn.addEventListener('click', (event) => {
          event.preventDefault();
          const willShow = panel.hasAttribute('hidden');
          if (willShow){
            ensureRows();
          }
          togglePanel(panel, willShow, toggleBtn);
        });
      }
      closeElements.forEach(el => {
          el.addEventListener('click', (event) => {
          event.preventDefault();
          togglePanel(panel, false, toggleBtn);
          if (toggleBtn){
            toggleBtn.focus();
          }
        });
     });

      if (section.hasAttribute('data-bankitos-invite-initial-open')) {
        ensureRows();
        togglePanel(panel, true, toggleBtn);
      }

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

      // Encontrar los elementos relativos
      const actionsWrapper = toggle.closest(selectors.actionsCell); // Contenedor <td>
      if (!actionsWrapper) return;
      
      const defaultActions = actionsWrapper.querySelector(selectors.defaultActions);
      const row = toggle.closest('tr');
      const editFields = row ? row.querySelectorAll(selectors.editField) : [];
      const displayFields = row ? row.querySelectorAll(selectors.editDisplay) : [];
      const inlineInputs = row ? row.querySelectorAll(`[data-bankitos-invite-edit-input="${targetId}"]`) : [];

      function toggleInlineFields(show){
        editFields.forEach(field => {
          if (show) {
            field.removeAttribute('hidden');
          } else {
            field.setAttribute('hidden', '');
          }
        });
        displayFields.forEach(field => {
          if (show) {
            field.setAttribute('hidden', '');
          } else {
            field.removeAttribute('hidden');
          }
        });
      }

      toggle.addEventListener('click', () => {
        const willShow = form.hasAttribute('hidden');
        if (willShow) {
          form.removeAttribute('hidden');
          if (defaultActions) defaultActions.setAttribute('hidden', ''); // Ocultar acciones
          toggle.setAttribute('hidden', ''); // Ocultar el botón "Editar"
          toggleInlineFields(true);

          const firstInput = inlineInputs.length
            ? inlineInputs[0]
            : form.querySelector('input[type="text"], input[type="email"], input');
          if (firstInput) {
            firstInput.focus();
          }
        } else {
          form.setAttribute('hidden', '');
          if (defaultActions) defaultActions.removeAttribute('hidden'); // Mostrar acciones
          toggle.removeAttribute('hidden'); // Mostrar el botón "Editar"
          toggleInlineFields(false);
        }
        toggle.setAttribute('aria-expanded', willShow ? 'true' : 'false');
      });

      form.addEventListener('click', event => {
        const target = event.target;
        if (target && target.matches(selectors.editCancel)) {
          event.preventDefault();
          form.setAttribute('hidden', '');
          if (defaultActions) defaultActions.removeAttribute('hidden'); // Mostrar acciones
          toggle.removeAttribute('hidden'); // Mostrar el botón "Editar"
          toggleInlineFields(false);
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

  // Corrección: Forzar la espera a DOMContentLoaded SIEMPRE.
  // Esto garantiza que el HTML de los shortcodes exista
  // antes de que el script intente buscar los botones.
  document.addEventListener('DOMContentLoaded', init);
})();