/** Native Joomla form validation and tab activation for the tag editor. */
const initialiseEditor = () => {
  const form = document.querySelector('.smarttag-editor form');
  if (!form) return;

  const reveal = (field) => {
    if (!field || !form.contains(field)) return;
    const panels = [];
    let panel = field.closest('joomla-tab-element');
    while (panel) {
      panels.unshift(panel);
      panel = panel.parentElement?.closest('joomla-tab-element');
    }
    panels.forEach((element) => {
      const tabs = element.parentElement.closest('joomla-tab');
      if (tabs && !element.hasAttribute('active')) tabs.activateTab(element, false);
    });
    requestAnimationFrame(() => {
      const control = field.matches('input, select, textarea, button')
        ? field : field.querySelector('input:not([type="hidden"]):not([disabled]), select:not([disabled]), textarea:not([disabled]), button:not([disabled])');
      control?.focus();
    });
  };

  form.addEventListener('submit', (event) => {
    const task = event.submitter?.value || form.querySelector('input[name="task"]')?.value;
    if (task === 'tag.cancel') return;
    if (!document.formvalidator.isValid(form)) {
      event.preventDefault();
      reveal(form.querySelector('[aria-invalid="true"], input.invalid, select.invalid, textarea.invalid, fieldset.invalid'));
    }
  });

  const invalidField = form.dataset.invalidField;
  if (invalidField) reveal(document.getElementById(invalidField));
};

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initialiseEditor, { once: true });
} else {
  initialiseEditor();
}
