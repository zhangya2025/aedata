(function() {
  function getMessages() {
    if (typeof window.aegisFormsPublic === 'object' && window.aegisFormsPublic) {
      return window.aegisFormsPublic;
    }
    return {
      requiredMessage: 'This field is required.',
      invalidEmailMessage: 'Please enter a valid email address.',
      fileButtonText: 'Choose file',
      fileNoText: 'No file chosen',
      submittingText: 'Submitting...'
    };
  }

  function clearErrors(form) {
    form.querySelectorAll('.aegis-forms-error').forEach(function(node) {
      node.remove();
    });
  }

  function addError(field, message) {
    var container = field.closest('p') || field.parentElement;
    if (!container) {
      return;
    }

    if (container.querySelector('.aegis-forms-error')) {
      return;
    }

    var error = document.createElement('div');
    error.className = 'aegis-forms-error';
    error.setAttribute('role', 'alert');
    error.textContent = message;
    container.appendChild(error);
  }

  function isEmptyField(field) {
    if (field.type === 'file') {
      return !field.files || field.files.length === 0;
    }

    return !field.value || field.value.trim() === '';
  }

  function validateForm(form, messages) {
    clearErrors(form);

    var fields = Array.prototype.slice.call(
      form.querySelectorAll('input, textarea, select')
    );
    var firstInvalid = null;

    fields.forEach(function(field) {
      if (field.disabled) {
        return;
      }

      if (field.required && isEmptyField(field)) {
        addError(field, messages.requiredMessage);
        if (!firstInvalid) {
          firstInvalid = field;
        }
        return;
      }

      if (field.type === 'email' && field.value && !field.validity.valid) {
        addError(field, messages.invalidEmailMessage);
        if (!firstInvalid) {
          firstInvalid = field;
        }
      }
    });

    if (firstInvalid) {
      firstInvalid.focus();
      return false;
    }

    return true;
  }

  function setupFileInput(form, messages) {
    var input = form.querySelector('#aegis-forms-attachment');
    if (!input) {
      return;
    }

    var wrapper = input.parentElement;
    if (!wrapper) {
      return;
    }

    var nameLabel = wrapper.querySelector('.aegis-forms-file-name');

    var label = wrapper.querySelector('.aegis-forms-file-btn');
    if (label) {
      label.textContent = messages.fileButtonText;
    }

    if (nameLabel) {
      nameLabel.textContent = messages.fileNoText;
    }

    input.addEventListener('change', function() {
      if (!nameLabel) {
        return;
      }
      if (input.files && input.files.length > 0) {
        nameLabel.textContent = input.files[0].name;
      } else {
        nameLabel.textContent = messages.fileNoText;
      }
    });
  }

  function setupForm(form, messages) {
    setupFileInput(form, messages);

    form.addEventListener('submit', function(event) {
      if (!validateForm(form, messages)) {
        event.preventDefault();
        return;
      }

      if (form.dataset.submitted === 'true') {
        event.preventDefault();
        return;
      }

      form.dataset.submitted = 'true';
      var button = form.querySelector('.aegis-forms-submit');
      if (button) {
        button.disabled = true;
        button.textContent = messages.submittingText;
      }
    });
  }

  document.addEventListener('DOMContentLoaded', function() {
    var messages = getMessages();
    var forms = document.querySelectorAll('form[data-aegis-forms]');
    forms.forEach(function(form) {
      setupForm(form, messages);
    });
  });
})();
