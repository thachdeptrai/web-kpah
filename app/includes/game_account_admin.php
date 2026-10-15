<?php
/**
 * KPAH game account / character moderation.
 *
 * IMPORTANT: independent fields in the supplied game schema:
 *   team_user.id          = web/game account primary key
 *   team_user.ban         = account ban: 0 normal, 1 banned, 3 special game state
 *   tob_char.userId       = team_user.id (NOT team_user.userid)
 *   tob_char.ban          = character ban: 0 normal, 1 banned
 *   tob_char.del          = character deletion state; must NEVER be changed here
 *
 * This code never touches passwords, wallets, balances, inventory or login data.
 */
if (!defined('KP_ROOT')) { http_response_code(403); exit; }

/** A simple immutable-ish intent/action log; create only when a moderation write is made. */
function kp_game_moderation_ensure_log(PDO $db): void
{
    $db->exec("CREATE TABLE IF NOT EXISTS kp_game_moderation_log (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
      admin_id BIGINT UNSIGNED NOT NULL,
      admin_name VARCHAR(64) NOT NULL,
      target_type VARCHAR(16) NOT NULL,
      target_id INT NOT NULL,
      target_name VARCHAR(64) NOT NULL,
      owner_id INT NOT NULL,
      old_ban INT NOT NULL,
      new_ban INT NOT NULL,
      reason VARCHAR(250) NOT NULL,
      result VARCHAR(16) NOT NULL DEFAULT 'pending',
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      completed_at DATETIME NULL,
      KEY idx_game_mod_target (target_type,target_id,id),
      KEY idx_game_mod_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function kp_game_moderation_log_available(PDO $db): bool
{
    try {
        return (bool)$db->query("SHOW TABLES LIKE 'kp_game_moderation_log'")->fetchColumn();
    } catch (Throwable $e) {
        kp_log('moderation log availability check failed', $e);
        return false;
    }
}

function kp_game_moderation_history(PDO $db, int $accountId, int $limit = 60): array
{
    if (!kp_game_moderation_log_available($db)) return [];
    $limit = max(1, min(100, $limit));
    $q = $db->prepare("SELECT id,admin_name,target_type,target_id,target_name,old_ban,new_ban,reason,result,created_at
                      FROM kp_game_moderation_log WHERE owner_id=? ORDER BY id DESC LIMIT $limit");
    $q->execute([$accountId]);
    return $q->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Perform a single compare-and-set mutation; do not silently alter any other status.
 * A pending audit row is inserted first, since game tables are MyISAM and cannot
 * participate in an atomic transaction with InnoDB logging.
 */
function kp_game_moderation_change(PDO $db, array $admin, string $type, int $id, string $direction, string $reason, string $confirmation): string
{
    if (!in_array($type, ['account','character'], true) || $id < 1) {
        throw new RuntimeException('Loại tài khoản hoặc ID nhân vật không hợp lệ.');
    }
    if (!in_array($direction, ['ban','unban'], true)) {
        throw new RuntimeException('Thao tác khóa/mở khóa không hợp lệ.');
    }
    $reason = trim($reason);
    if (strlen($reason) < 8 || strlen($reason) > 250 || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $reason)) {
        throw new RuntimeException('Lý do phải có từ 8 đến 250 ký tự và không chứa ký tự điều khiển.');
    }
    $nextBan = $direction === 'ban' ? 1 : 0;
    $expectedBan = 1 - $nextBan;
    // Isolate concurrent moderator requests on the same entity.
    $lockName = 'kpah_mod_'.$type.'_'.$id;
    $stmt = $db->prepare('SELECT GET_LOCK(?,3)');
    $stmt->execute([$lockName]);
    if ((int)$stmt->fetchColumn() !== 1) {
        throw new RuntimeException('Nhân vật/tài khoản đang được xử lý bởi một admin khác.');
    }
    try {
        if ($type === 'account') {
            $stmt = $db->prepare('SELECT id,username,ban FROM team_user WHERE id=? LIMIT 1');
            $stmt->execute([$id]);
            $record = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$record) throw new RuntimeException('Không tìm thấy tài khoản game.');
            $ownerId = (int)$record['id'];
            $name = (string)$record['username'];
            $currentBan = (int)$record['ban'];
            // Never lock an account that contains a privileged in-game character.
            $stmt = $db->prepare('SELECT COUNT(*) FROM tob_char WHERE userId=? AND isAdmin<>0');
            $stmt->execute([$ownerId]);
            if ((int)$stmt->fetchColumn() > 0) {
                throw new RuntimeException('Tài khoản có nhân vật quản trị game: thao tác bị từ chối.');
            }
        } else {
            $stmt = $db->prepare('SELECT c.id,c.charname,c.userId,c.ban,c.isAdmin,u.id AS owner_exists
                                  FROM tob_char AS c
                                  LEFT JOIN team_user AS u ON u.id=c.userId
                                  WHERE c.id=? LIMIT 1');
            $stmt->execute([$id]);
            $record = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$record) throw new RuntimeException('Không tìm thấy nhân vật.');
            if ($record['owner_exists'] === null) {
                throw new RuntimeException('Nhân vật chưa liên kết tài khoản hợp lệ.');
            }
            $ownerId = (int)$record['userId'];
            $name = (string)$record['charname'];
            $currentBan = (int)$record['ban'];
            if ((int)$record['isAdmin'] !== 0) {
                throw new RuntimeException('Không thể khóa/mở khóa nhân vật quản trị game tại đây.');
            }
        }
        if (!hash_equals($name, trim($confirmation))) {
            throw new RuntimeException('Tên xác nhận không trùng với tài khoản/nhân vật được chọn.');
        }
        // Both flags are 0/1 for moderation, but preserve reserved game statuses.
        if (!in_array($currentBan, [0,1], true)) {
            throw new RuntimeException('Trạng thái ban của game khác 0/1; không tự ghi đè giá trị đặc biệt.');
        }
        if ($currentBan !== $expectedBan) {
            throw new RuntimeException($currentBan === $nextBan
                ? 'Trạng thái đã đúng như yêu cầu. Không cần thay đổi.'
                : 'Trạng thái vừa thay đổi, hãy tải lại trang trước khi thực hiện.');
        }

        // Audit storage must be writable before changing a game table (fail-closed).
        kp_game_moderation_ensure_log($db);
        $log = $db->prepare('INSERT INTO kp_game_moderation_log
               (admin_id,admin_name,target_type,target_id,target_name,owner_id,old_ban,new_ban,reason)
               VALUES (?,?,?,?,?,?,?,?,?)');
        $log->execute([
            (int)$admin['id'], (string)$admin['username'], $type, $id, $name,
            $ownerId, $currentBan, $nextBan, $reason
        ]);
        $auditId = (int)$db->lastInsertId();
        try {
            if ($type === 'account') {
                $stmt = $db->prepare('UPDATE team_user SET ban=? WHERE id=? AND ban=?');
            } else {
                $stmt = $db->prepare('UPDATE tob_char SET ban=? WHERE id=? AND ban=? AND isAdmin=0');
            }
            $stmt->execute([$nextBan,$id,$expectedBan]);
            $changed = $stmt->rowCount() === 1;
            $log = $db->prepare('UPDATE kp_game_moderation_log
                                 SET result=?,completed_at=NOW() WHERE id=? AND result=?');
            $log->execute([$changed?'applied':'conflict',$auditId,'pending']);
            if (!$changed) {
                throw new RuntimeException('Không cập nhật được: trạng thái vừa thay đổi hoặc bản ghi không còn tồn tại.');
            }
        } catch (Throwable $e) {
            // On a DB outage, pending records remain available for manual reconciliation.
            throw $e;
        }
        kp_log('game moderation applied kind='.$type.' target_id='.$id.' owner_id='.$ownerId
            .' admin_id='.(int)$admin['id'].' old_ban='.$currentBan.' new_ban='.$nextBan);
        return ($type === 'account' ? 'Tài khoản ' : 'Nhân vật ')
            .$name.($nextBan === 1 ? ' đã bị ban.' : ' đã được mở ban.');
    } finally {
        $release = $db->prepare('SELECT RELEASE_LOCK(?)');
        $release->execute([$lockName]);
    }
}


/**
 * Return only targets whose normal ban flag is exactly 1. A character with
 * isAdmin != 0, or an account owning any GM character, is excluded. Orphan
 * characters are excluded to match single-entity moderation behavior.
 */
function kp_game_moderation_bulk_targets(PDO $db, string $type): array
{
    if ($type === 'account') {
        $sql = 'SELECT u.id,u.username AS target_name
                FROM team_user u WHERE u.ban=1
                AND NOT EXISTS (SELECT 1 FROM tob_char c WHERE c.userId=u.id AND c.isAdmin<>0)
                ORDER BY u.id ASC';
    } elseif ($type === 'character') {
        $sql = 'SELECT c.id,c.charname AS target_name,u.username AS owner_name
                FROM tob_char c
                INNER JOIN team_user u ON u.id=c.userId
                WHERE c.ban=1 AND c.isAdmin=0
                ORDER BY c.id ASC';
    } else {
        throw new RuntimeException('Loại mở ban hàng loạt không hợp lệ.');
    }
    return $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
}

function kp_game_moderation_bulk_fingerprint(array $targets): string
{
    // Fingerprint freezes the exact set displayed in the confirmation UI.
    $ids = [];
    foreach ($targets as $row) $ids[] = (int)$row['id'];
    return hash('sha256', implode(',', $ids));
}

/**
 * Explicitly admin-confirmed bulk UNBAN, using the SAME audited per-target
 * compare-and-set path as the single-target controls. Never unban accounts
 * and characters in the same action. No changes to special ban states.
 *
 * The game tables are MyISAM: if a target fails halfway, earlier successful
 * operations cannot be rolled back. Report partial results accurately.
 */
function kp_game_moderation_bulk_unban(PDO $db, array $admin, string $type, string $reason, string $confirmation, string $fingerprint, array $selectedIds): array
{
    $phrases = [
        'account' => 'MO BAN TAT CA TAI KHOAN',
        'character' => 'MO BAN TAT CA NHAN VAT',
    ];
    if (!isset($phrases[$type])) {
        throw new RuntimeException('Chỉ hỗ trợ mở ban tài khoản hoặc nhân vật.');
    }
    if (!hash_equals($phrases[$type], trim($confirmation))) {
        throw new RuntimeException('Câu xác nhận hàng loạt không chính xác.');
    }
    $reason=trim($reason);
    if (strlen($reason)<8 || strlen($reason)>250 || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/',$reason)) {
        throw new RuntimeException('Lý do phải dài 8–250 ký tự và không chứa ký tự điều khiển.');
    }
    if (!preg_match('/^[a-f0-9]{64}$/D',$fingerprint)) {
        throw new RuntimeException('Danh sách xác nhận không hợp lệ. Tải lại trang.');
    }
    $lockName='kpah_mod_bulk_'.$type;
    $stmt=$db->prepare('SELECT GET_LOCK(?,3)');
    $stmt->execute([$lockName]);
    if ((int)$stmt->fetchColumn()!==1) {
        throw new RuntimeException('Đang có đợt mở ban hàng loạt khác. Thử lại sau.');
    }
    try {
        $targets=kp_game_moderation_bulk_targets($db,$type);
        if (!hash_equals(kp_game_moderation_bulk_fingerprint($targets),$fingerprint)) {
            throw new RuntimeException('Danh sách bị ban đã thay đổi. Tải lại trang rồi xác nhận lại.');
        }
        if (!$targets) {
            throw new RuntimeException('Không còn đối tượng bị ban hợp lệ để mở.');
        }

        // Fail closed: only the explicitly selected subset is actionable.
        // Checkbox values are untrusted; never use their order or names to write SQL.
        if (!$selectedIds || count($selectedIds) > count($targets)) {
            throw new RuntimeException('Chưa chọn tài khoản/nhân vật hợp lệ để mở ban.');
        }
        $allowedIds = [];
        foreach ($targets as $target) $allowedIds[(int)$target['id']] = $target;
        $chosen = [];
        foreach ($selectedIds as $rawId) {
            if ((!is_string($rawId) && !is_int($rawId))
                || !preg_match('/^[1-9][0-9]{0,9}$/D',(string)$rawId)) {
                throw new RuntimeException('Danh sách chọn có ID không hợp lệ.');
            }
            $id=(int)$rawId;
            if (!isset($allowedIds[$id]) || isset($chosen[$id])) {
                throw new RuntimeException('Có đối tượng không đủ điều kiện hoặc bị chọn trùng. Tải lại trang.');
            }
            $chosen[$id] = $allowedIds[$id];
        }
        // Fail closed if the audit log cannot be created before mutating MyISAM.
        kp_game_moderation_ensure_log($db);
        $ok=0;$failed=0;
        foreach ($chosen as $target) {
            try {
                kp_game_moderation_change(
                    $db,$admin,$type,(int)$target['id'],'unban',$reason,(string)$target['target_name']
                );
                $ok++;
            } catch (Throwable $e) {
                $failed++;
                // No credentials, email, phone or request payload in logs.
                kp_log('bulk game unban failed kind='.$type.' id='.(int)$target['id'],$e);
            }
        }
        kp_log('bulk game unban kind='.$type.' admin_id='.(int)$admin['id']
            .' selected='.count($chosen).' skipped='.(count($targets)-count($chosen))
            .' changed='.$ok.' failed='.$failed);
        return ['type'=>$type,'total'=>count($chosen),'eligible'=>count($targets),
            'excluded'=>count($targets)-count($chosen),'changed'=>$ok,'failed'=>$failed];
    } finally {
        $stmt=$db->prepare('SELECT RELEASE_LOCK(?)');
        $stmt->execute([$lockName]);
    }
}
