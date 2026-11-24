(() => {
  'use strict';
  const dialog = document.getElementById('action-confirmation');
  if (!dialog) return;
  const title = document.getElementById('confirmation-title');
  const description = document.getElementById('confirmation-description');
  const note = document.getElementById('confirmation-note');
  const accept = document.getElementById('confirmation-accept');
  const cancelButtons = [...dialog.querySelectorAll('[data-confirm-cancel]')];
  let pending = null;
  let closing = false;
  let closeTimer;
  let submittedForms = new WeakSet();
  const confirmedForms = new WeakSet();
  const waitingForms = new WeakSet();

  function dismiss(approved) {
    if (!pending || closing) return;
    closing = true;
    dialog.dataset.closing = 'true';
    const finish = () => {
      clearTimeout(closeTimer);
      dialog.removeEventListener('animationend', onAnimationEnd);
      if (dialog.open) dialog.close(approved ? 'confirm' : 'cancel');
    };
    const onAnimationEnd = event => { if (event.target === dialog) finish(); };
    if (window.matchMedia?.('(prefers-reduced-motion: reduce)').matches) finish();
    else {
      dialog.addEventListener('animationend', onAnimationEnd);
      closeTimer = setTimeout(finish, 180);
    }
  }

  dialog.addEventListener('close', () => {
    clearTimeout(closeTimer);
    const current = pending;
    pending = null;
    closing = false;
    delete dialog.dataset.closing;
    document.documentElement.classList.remove('has-confirm-modal');
    if (current?.previousFocus?.isConnected) current.previousFocus.focus({preventScroll: true});
    current?.resolve(dialog.returnValue === 'confirm');
  });
  dialog.addEventListener('cancel', event => { event.preventDefault(); dismiss(false); });
  dialog.addEventListener('click', event => {
    if (event.target !== dialog) return;
    const bounds = dialog.getBoundingClientRect();
    if (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom) dismiss(false);
  });
  cancelButtons.forEach(button => button.addEventListener('click', () => dismiss(false)));
  accept.addEventListener('click', () => dismiss(true));

  window.SsrConfirm = {
    ask(options) {
      if (pending || dialog.open) return Promise.resolve(false);
      const kind = ['payment', 'success', 'deleted', 'error'].includes(options.kind) ? options.kind : 'delete';
      const result = Boolean(options.result);
      dialog.dataset.kind = kind;
      dialog.dataset.result = String(result);
      dialog.setAttribute('role', result ? 'dialog' : 'alertdialog');
      title.textContent = options.title;
      description.textContent = options.description;
      note.textContent = options.note;
      note.hidden = !options.note;
      accept.textContent = options.acceptLabel;
      accept.className = `button ${kind === 'payment' || kind === 'success' ? 'primary' : 'danger'}`;
      cancelButtons.at(-1).hidden = result;
      dialog.querySelector('.confirm-close').setAttribute('aria-label', result ? 'Tutup notifikasi' : 'Tutup konfirmasi');
      const iconName = kind === 'deleted' ? 'error' : kind;
      dialog.querySelectorAll('[data-confirm-icon]').forEach(icon => { icon.toggleAttribute('hidden', icon.dataset.confirmIcon !== iconName); });
      return new Promise(resolve => {
        pending = {resolve, previousFocus: document.activeElement};
        dialog.returnValue = '';
        try {
          dialog.showModal();
          document.documentElement.classList.add('has-confirm-modal');
          (result ? accept : cancelButtons.at(-1)).focus({preventScroll: true});
        } catch (_) {
          pending = null;
          resolve(false);
        }
      });
    },
  };

  document.addEventListener('submit', async event => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || !form.matches('[data-confirm]')) return;
    if (submittedForms.has(form)) { event.preventDefault(); return; }
    if (confirmedForms.has(form)) {
      confirmedForms.delete(form);
      submittedForms.add(form);
      form.querySelectorAll('button[type="submit"]').forEach(button => {
        button.dataset.confirmOriginalText = button.textContent;
        button.disabled = true;
        button.textContent = 'Menghapus...';
      });
      return;
    }
    event.preventDefault();
    if (waitingForms.has(form)) return;
    waitingForms.add(form);
    try {
      const approved = await window.SsrConfirm.ask({
        kind: 'delete',
        title: 'Hapus reservasi ini?',
        description: form.dataset.confirm,
        note: 'Pastikan reservasi yang dipilih sudah benar sebelum melanjutkan.',
        acceptLabel: 'Hapus Reservasi',
      });
      if (!approved || !form.isConnected) return;
      confirmedForms.add(form);
      if (event.submitter?.isConnected) form.requestSubmit(event.submitter);
      else form.requestSubmit();
    } finally {
      waitingForms.delete(form);
    }
  });

  window.addEventListener('pageshow', () => {
    submittedForms = new WeakSet();
    document.querySelectorAll('[data-confirm-original-text]').forEach(button => {
      button.disabled = false;
      button.textContent = button.dataset.confirmOriginalText;
      delete button.dataset.confirmOriginalText;
    });
  });

  const feedback = document.getElementById('operation-feedback');
  if (feedback) {
    window.SsrConfirm.ask({
      kind: feedback.dataset.kind,
      result: true,
      title: feedback.dataset.title,
      description: feedback.dataset.message,
      note: feedback.dataset.note,
      acceptLabel: 'Oke, Mengerti',
    });
  }
})();
