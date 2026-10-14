<?php
// Sao chép thành config/topup.local.php trên VPS. KHÔNG commit file thật.
return [
    'partner_id' => 'DAN_PARTNER_ID_VAO_DAY',
    'partner_key' => 'DAN_PARTNER_KEY_VAO_DAY',
    // Chỉ bật sau khi xác nhận game server nhận và tiêu thụ board_naptien.
    'game_queue_verified' => false,
    'server_id' => -1,
];
