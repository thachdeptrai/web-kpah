'use strict';
// Missing game PNGs are expected until the server's map/icon folder is copied.
document.addEventListener('error', function (event) {
  var target = event.target;
  if (target && target.tagName === 'IMG' && target.closest('.ci-icon')) {
    target.hidden = true;
  }
}, true);
