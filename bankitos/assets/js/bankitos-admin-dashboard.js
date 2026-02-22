(function($){
  $(document).on('click', '.bankitos-delete-form button[type="submit"]', function(ev){
    ev.preventDefault();
    var $form = $(this).closest('form');
    var confirmFirst = $form.data('confirm-first') || '';
    var confirmSecond = $form.data('confirm-second') || '';
    var phraseField = $form.find('input[name="confirm_phrase"]');
    var phrase = (phraseField.val() || '').trim().toUpperCase();

    if (confirmFirst && !window.confirm(confirmFirst)) {
      return;
    }
    if (confirmSecond && !window.confirm(confirmSecond)) {
      return;
    }
    var warn = (typeof bankitosAdminDashboard !== 'undefined' && bankitosAdminDashboard.deleteWarning) ? bankitosAdminDashboard.deleteWarning : 'Escribe ELIMINAR para continuar.';
    if (phrase !== 'ELIMINAR') {
      alert(warn);
      return;
    }

    $form.off('submit');
    $form.trigger('submit');
  });
})(jQuery);