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

// MER-29: Filter the fund summaries on the Funds page by name as the user types.
document.querySelectorAll('[data-fund-search]').forEach((input) => {
  const rows = document.querySelectorAll('.fund-row');
  const empty = document.querySelector('[data-fund-empty]');

  input.addEventListener('input', () => {
    const query = input.value.trim().toLowerCase();
    let shown = 0;
    rows.forEach((row) => {
      const match = row.dataset.fundName.includes(query);
      row.classList.toggle('d-none', !match);
      if (match) shown += 1;
    });
    empty.classList.toggle('d-none', shown > 0);
  });
});

// MER-26: Upload the profile photo as soon as one is chosen.
document.querySelectorAll('[data-photo-input]').forEach((input) => {
  input.addEventListener('change', () => {
    if (input.files.length) {
      input.closest('form').submit();
    }
  });
});

// MER-26: 6-digit verification code boxes — move to the next box as digits are typed,
// go back on Backspace, and spread a pasted code across all boxes.
document.querySelectorAll('[data-code-inputs]').forEach((group) => {
  const boxes = [...group.querySelectorAll('input')];

  const fill = (start, digits) => {
    digits.split('').forEach((digit, offset) => {
      if (boxes[start + offset]) boxes[start + offset].value = digit;
    });
    boxes[Math.min(start + digits.length, boxes.length - 1)].focus();
  };

  boxes.forEach((box, i) => {
    box.addEventListener('input', (event) => {
      // A single typed character replaces the box's digit; anything longer (autofill,
      // a pasted or typed-at-once code) is spread across the boxes from here.
      const typed = event.inputType === 'insertText' && event.data && event.data.length === 1;
      const digits = (typed ? event.data : box.value).replace(/\D/g, '');
      if (!digits) {
        // Ignore non-digits but keep the digit that was already there.
        box.value = box.value.replace(/\D/g, '').slice(0, 1);
        return;
      }
      box.value = '';
      fill(i, digits);
    });
    box.addEventListener('keydown', (event) => {
      if (event.key === 'Backspace' && !box.value && i > 0) {
        boxes[i - 1].value = '';
        boxes[i - 1].focus();
        event.preventDefault();
      }
    });
    box.addEventListener('paste', (event) => {
      const digits = (event.clipboardData.getData('text') || '').replace(/\D/g, '').slice(0, boxes.length);
      if (digits) {
        event.preventDefault();
        fill(0, digits);
      }
    });
  });
});
