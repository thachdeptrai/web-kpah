<?php
// Compatibility URL for the former Top nạp page.
// The shared leaderboard now offers Top nạp together with eight other rankings.
require __DIR__.'/../includes/bootstrap.php';
require_once __DIR__.'/../includes/player_portal.php';
kp_player_account();
require_once __DIR__.'/../includes/rankings.php';
$hasNap=isset(kp_rankings_available()['nap']);
header('Location: '.($hasNap?'/bang-xep-hang?type=nap':'/bang-xep-hang'),true,302);
exit;
