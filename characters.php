<?php
// Compatibility entrypoint. UI source lives in app/Pages.
require_once __DIR__.'/includes/legacy_entry.php';
kp_legacy_redirect('/characters.php', '/tai-khoan/nhan-vat');
require __DIR__.'/app/Pages/Characters.php';
