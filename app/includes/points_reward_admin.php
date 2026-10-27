<?php
/**
 * Administrative removal of unused Point catalog entries.
 *
 * A redeemed gift must stay in the catalog (and can be hidden instead) so that
 * historical redemption references, stock restoration, and audits are intact.
 * This function MUST be invoked only after an authenticated admin + CSRF check.
 */
if (!defined('KP_ROOT')) { http_response_code(403); exit; }
require_once __DIR__.'/points_reward_display.php';
require_once __DIR__.'/points_tiers.php';

function kp_points_admin_delete_reward(PDO $db, int $rewardId): string {
    if ($rewardId < 1) throw new InvalidArgumentException('Mã quà không hợp lệ.');
    if ($db->inTransaction()) throw new RuntimeException('Không thể xóa quà khi giao dịch khác chưa kết thúc.');
    $db->beginTransaction();
    try {
        // Lock the same reward row as kp_points_redeem() to serialize redeem/delete.
        $st=$db->prepare('SELECT title FROM kp_points_rewards WHERE id=? FOR UPDATE');
        $st->execute([$rewardId]);
        $title=$st->fetchColumn();
        if ($title===false) throw new RuntimeException('Không tìm thấy quà hoặc quà đã được xóa.');

        // No physical deletion once any redemption exists, including rejected
        // and fulfilled ones. Their references are required for audit and replay.
        $st=$db->prepare('SELECT id FROM kp_points_redemptions WHERE reward_id=? LIMIT 1');
        $st->execute([$rewardId]);
        if ($st->fetchColumn()!==false) {
            throw new RuntimeException('Quà đã có lịch sử đổi. Không thể xóa vĩnh viễn; hãy dùng “Ẩn quà” để bảo toàn đơn và Point.');
        }

        if (kp_point_reward_display_available($db)) {
            $st=$db->prepare('DELETE FROM kp_points_reward_display WHERE reward_id=?');
            $st->execute([$rewardId]);
        }
        if (kp_points_tier_enabled($db)) {
            $st=$db->prepare('DELETE FROM kp_points_reward_tiers WHERE reward_id=?');
            $st->execute([$rewardId]);
        }
        $st=$db->prepare('DELETE FROM kp_points_rewards WHERE id=?');
        $st->execute([$rewardId]);
        if ($st->rowCount()!==1) throw new RuntimeException('Không xóa được quà. Dữ liệu đã thay đổi.');
        $db->commit();
        return (string)$title;
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        throw $e;
    }
}
