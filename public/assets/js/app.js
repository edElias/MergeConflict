// Bootstrap client-side validation for forms marked .needs-validation.
// Server-side checks in PHP remain the source of truth.
document.querySelectorAll('form.needs-validation').forEach((form) => {
  form.addEventListener('submit', (event) => {
    if (!form.checkValidity()) {
      event.preventDefault();
      event.stopPropagation();
    }
    form.classList.add('was-validated');
  });
});
