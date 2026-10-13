<?php
/**
 * Tạo tài khoản trong bảng `team_user` đúng định dạng game đang dùng.
 *
 * Quan sát từ kpah.sql:
 *  - Bảng `team_user` (MyISAM, utf8). Khoá chính `id` AUTO_INCREMENT.
 *  - `userid` (INT UNSIGNED, NOT NULL, UNIQUE `Index_2`, KHÔNG tự tăng, không có default)
 *    => phải tự cấp: MAX(userid)+1 trong vùng khoá GET_LOCK, có thử lại nếu trùng.
 *  - `username` UNIQUE `uk_team_user_username` (varchar(20)).
 *  - `password` dạng MySQL PASSWORD(): '*' + HEX_HOA(SHA1(SHA1(mật khẩu))), 41 ký tự
 *    (đã đối chiếu: '123456' => *6BB4837EB74329105EE4568DDA7DC67ED2CA2AD9 trùng dữ liệu thật).
 *  - `phone` NOT NULL (mặc định ''), dữ liệu thật dùng dạng +84xxxxxxxxx.
 *  - `newagent`/`newprovider` mặc định '-1' (để database tự áp default).
 */
if (!defined('KP_ROOT')) {
    http_response_code(403);
    exit;
}

/**
 * Băm theo định dạng legacy mà game server hiện dùng.
 * Không đổi sang bcrypt khi tạo tài khoản mới nếu game server vẫn cần hash này.
 */
function kp_game_password_hash($password)
{
    return '*' . strtoupper(sha1(sha1((string) $password, true)));
}

/**
 * Xác minh các định dạng đang có trong bảng team_user:
 * - bcrypt ($2y$ / $2a$ / $2b$): password_verify()
 * - hash legacy của game: so sánh hằng thời gian với kp_game_password_hash().
 * Không chấp nhận mật khẩu lưu dạng plaintext.
 */
function kp_verify_account_password($password, $storedHash)
{
    $password = (string) $password;
    $storedHash = (string) $storedHash;

    if (preg_match('/^\$2[aby]\$\d{2}\$/', $storedHash) === 1) {
        return password_verify($password, $storedHash);
    }

    if (preg_match('/^\*[A-F0-9]{40}$/', $storedHash) === 1) {
        return hash_equals($storedHash, kp_game_password_hash($password));
    }

    // Hỗ trợ thêm hash PHP hiện đại nếu có tài khoản nào đã dùng password_hash() với Argon2.
    if (strpos($storedHash, '$argon2') === 0) {
        return password_verify($password, $storedHash);
    }

    return false;
}

/**
 * @return string 'ok' | 'exists' | 'error'
 * @throws PDOException khi kết nối/truy vấn lỗi (nơi gọi phải bắt và ghi log)
 */
function kp_create_account($username, $password, $phone, $email)
{
    $pdo  = kp_db();
    $hash = kp_game_password_hash($password);
    $lock = 'kpah_reg_userid';

    $st = $pdo->prepare('SELECT GET_LOCK(?, 5)');
    $st->execute([$lock]);
    $gotLock = ((int) $st->fetchColumn() === 1);
    if (!$gotLock) {
        return 'error';
    }

    try {
        $chk = $pdo->prepare('SELECT 1 FROM `team_user` WHERE `username` = ? LIMIT 1');
        $chk->execute([$username]);
        if ($chk->fetchColumn()) {
            return 'exists';
        }

        // Chặn email đã dùng; đăng ký web được tuần tự hóa bằng cùng named lock.
        $emailCheck = $pdo->prepare('SELECT 1 FROM `team_user` WHERE LOWER(TRIM(`email`)) = LOWER(TRIM(?)) LIMIT 1');
        $emailCheck->execute([$email]);
        if ($emailCheck->fetchColumn()) {
            return 'email_exists';
        }

        $ins = $pdo->prepare(
            'INSERT INTO `team_user` (`userid`, `provider`, `agent`, `username`, `password`, `phone`, `regdate`, `ban`, `fromgame`, `email`)
             VALUES (?, 0, -1, ?, ?, ?, ?, 0, 0, ?)'
        );

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $userid = (int) $pdo->query('SELECT COALESCE(MAX(`userid`), 0) + 1 FROM `team_user`')->fetchColumn();
            try {
                $ins->execute([$userid, $username, $hash, (string) $phone, date('Y-m-d H:i:s'), (string) $email]);
                return 'ok';
            } catch (PDOException $e) {
                if (!kp_is_duplicate_key($e)) {
                    throw $e;
                }
                if (kp_duplicate_key_name($e) === 'uk_team_user_username') {
                    return 'exists';
                }
                // trùng userid (game server vừa cấp cùng lúc) -> lấy lại số mới
            }
        }
        return 'error';
    } finally {
        try {
            $rel = $pdo->prepare('SELECT RELEASE_LOCK(?)');
            $rel->execute([$lock]);
        } catch (\Throwable $e) {
            // bỏ qua: khoá sẽ tự nhả khi kết thúc request
        }
    }
}

function kp_is_duplicate_key(PDOException $e)
{
    return isset($e->errorInfo[1]) && (int) $e->errorInfo[1] === 1062;
}

/** Lấy tên chỉ mục bị trùng từ thông điệp lỗi MySQL/MariaDB. */
function kp_duplicate_key_name(PDOException $e)
{
    if (preg_match("/for key '(?:[^']*\\.)?([^']+)'/", $e->getMessage(), $m)) {
        return $m[1];
    }
    // định dạng thông điệp khác: nhận biết qua tên cột
    if (stripos($e->getMessage(), 'username') !== false) {
        return 'uk_team_user_username';
    }
    return '';
}
