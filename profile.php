<?php
// Compatibility entrypoint. UI source lives in app/Pages.
require_once __DIR__.'/includes/legacy_entry.php';
kp_legacy_redirect('/profile.php', '/tai-khoan/ho-so');
require __DIR__.'/app/Pages/Profile.php';
