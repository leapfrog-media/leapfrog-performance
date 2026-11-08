(function () {
    'use strict';
    // Buttons that ask first (undo, delete, reset).
    document.querySelectorAll('[data-lfa-confirm]').forEach(button => {
        button.addEventListener('click', event => {
            if (!window.confirm(button.dataset.lfaConfirm)) event.preventDefault();
        });
    });

    // Forms with class lfa-track warn before leaving with unsaved changes.
    // Fields marked data-lfa-ignore (search boxes, filters) don't count.
    let dirty = false;
    let saving = false;
    window.lfaMarkDirty = form => {
        if (saving) return;
        dirty = true;
        const label = form && form.querySelector('.lfa-unsaved');
        if (label) label.hidden = false;
    };
    document.querySelectorAll('form.lfa-track').forEach(form => {
        const changed = event => { if (!event.target.closest('[data-lfa-ignore]')) window.lfaMarkDirty(form); };
        form.addEventListener('change', changed);
        form.addEventListener('input', changed);
        // Editors (such as the help box's) may report a change while the form is saving.
        form.addEventListener('submit', () => { dirty = false; saving = true; });
    });
    window.addEventListener('beforeunload', event => {
        if (dirty) { event.preventDefault(); event.returnValue = ''; }
    });
})();
