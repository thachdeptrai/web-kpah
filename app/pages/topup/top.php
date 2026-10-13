<?php
// Compatibility URL for the former Top nạp page.
// The shared leaderboard now offers Top nạp together with eight other rankings.
require dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/player_portal.php';
kp_player_account();
header('Location: ../rankings.php?type=nap',true,302);
exit;
