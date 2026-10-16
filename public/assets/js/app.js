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

// Cash summary donuts: show the hovered slice's share in the center of the chart.
// The center keeps showing the last slice hovered until another one is hovered.
document.querySelectorAll('.cash-donut').forEach((donut) => {
  const percent = donut.querySelector('.cash-donut-percent');
  const name = donut.querySelector('.cash-donut-name');

  donut.querySelectorAll('.cash-donut-segment').forEach((segment) => {
    segment.addEventListener('mouseenter', () => {
      percent.textContent = segment.dataset.percent;
      name.textContent = segment.dataset.name;
    });
  });
});
