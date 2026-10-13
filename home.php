<?php
// Compatibility entrypoint. UI source lives in app/Pages.
require_once __DIR__.'/includes/legacy_entry.php';
kp_legacy_redirect('/home.php', '/tai-khoan');
require __DIR__.'/app/Pages/Home.php';
