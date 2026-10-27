<?php
/** Global availability of NEW Point reward exchanges. Earning and admin order
 * processing remain available regardless of this flag.
 */
if (!defined('KP_ROOT')) { http_response_code(403); exit; }

function kp_points_redemption_control_ready(PDO $db): bool {
    $st=$db->query("SHOW TABLES LIKE 'kp_points_redemption_control'");
    return $st!==false && $st->fetchColumn()!==false;
}

/** Default-enabled on installations not yet migrated (backwards compatible).
 * Lock the configuration row inside the redemption transaction to serialize
 * the final enable/disable decision against incoming redemption requests.
 */
function kp_points_redemption_enabled(PDO $db, bool $lock=false): bool {
    if (!kp_points_redemption_control_ready($db)) return true;
    $sql='SELECT is_enabled FROM kp_points_redemption_control WHERE id=1'.($lock?' FOR UPDATE':'');
    $st=$db->query($sql);
    $status=$st->fetchColumn();
    // If migration table exists but control row is missing, fail closed.
    return $status!==false && (int)$status===1;
}

/** Must be invoked by a verified Admin POST with CSRF. */
function kp_points_redemption_set_enabled(PDO $db, bool $enabled, int $adminId): bool {
    if ($adminId<1)throw new InvalidArgumentException('Quản trị viên không hợp lệ.');
    if (!kp_points_redemption_control_ready($db))
        throw new RuntimeException('Chưa chạy database/points_stage7_redemption_control.sql.');
    $st=$db->prepare('UPDATE kp_points_redemption_control SET is_enabled=?,changed_by=? WHERE id=1 AND is_enabled<>?');
    $value=$enabled?1:0;
    $st->execute([$value,$adminId,$value]);
    if($st->rowCount()===1)return true;
    $verify=$db->query('SELECT is_enabled FROM kp_points_redemption_control WHERE id=1')->fetchColumn();
    if($verify===false)throw new RuntimeException('Chưa khởi tạo trạng thái đổi Point trong SQL.');
    return false; // Already in the requested state: safe to retry.
}
