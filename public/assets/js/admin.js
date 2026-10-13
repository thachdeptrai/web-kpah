(function () {
  'use strict';
  var input = document.getElementById('code-search');
  if (!input) return;
  var section = input.closest('section');
  if (!section) return;
  input.addEventListener('input', function () {
    var term = input.value.trim().toLowerCase();
    section.querySelectorAll('tbody tr').forEach(function (row) {
      row.hidden = !row.textContent.toLowerCase().includes(term);
    });
  });
})();
