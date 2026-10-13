<?php
// Compatibility entrypoint. UI source lives in app/Pages.
require_once __DIR__.'/includes/legacy_entry.php';
kp_legacy_redirect('/forgot-password.php', '/quen-mat-khau');
require __DIR__.'/app/Pages/ForgotPassword.php';
