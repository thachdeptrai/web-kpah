<?php
require __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/admin_security.php';
require_once __DIR__ . '/../includes/admin_nav.php';
require_once __DIR__ . '/../includes/giftcodes.php';
require_once __DIR__ . '/../includes/giftcode_bulk.php';
require_once __DIR__ . '/../includes/giftcode_history_view.php';
kp_send_security_headers(true);
header('Content-Type: text/html; charset=utf-8');
$admin = kp_admin_require('/quan-tri/dang-nhap', ['admin']);
$notice = '';
$error = '';
$bulkResult = ['codes'=>[], 'locked'=>[], 'error'=>''];
$bulkNonce = (string)($_SESSION['kp_giftcode_bulk_nonce'] ?? '');
if (!preg_match('/\A[a-f0-9]{32}\z/D', $bulkNonce)) {
    $bulkNonce = bin2hex(random_bytes(16));
    $_SESSION['kp_giftcode_bulk_nonce'] = $bulkNonce;
}
$pdo = kp_db();
$rewardCatalog = ['items'=>[], 'shop_items'=>[], 'shop_map'=>[], 'options'=>[], 'item_map'=>[], 'option_map'=>[]];
$rewardCatalogError = '';
$dboEnabled = kp_giftcode_dbo_enabled();
$itemColumnCapacity = 255;
try {
    $rewardCatalog = kp_giftcode_reward_catalog($pdo);
    $itemColumnCapacity = kp_giftcode_item_column_capacity($pdo);
} catch (Throwable $e) {
    kp_log('giftcode reward catalog unavailable', $e);
    $rewardCatalogError = 'Không tải được data_item/data_shop/data_attribute hoặc schema Giftcode.';
}
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!kp_admin_csrf_valid($_POST['csrf'] ?? '')) {
        $error = 'Phiên không hợp lệ. Tải lại trang và thử lại.';
    } else {
        try {
            $action = (string)($_POST['action'] ?? '');
            $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($action === 'create' || $action === 'update' || $action === 'bulk_create') {
                if ($rewardCatalogError !== '') throw new RuntimeException($rewardCatalogError.' Không thể lưu phần thưởng an toàn.');
                if ($action === 'bulk_create') {
                    if (!hash_equals($bulkNonce, (string)($_POST['bulk_nonce'] ?? ''))) {
                        throw new RuntimeException('Yêu cầu tạo hàng loạt đã hết hạn hoặc đã xử lý. Hãy tải lại trang.');
                    }
                    [$allowed] = kp_admin_limit('giftcode-bulk', (string)$admin['id'], 5, 3600);
                } else {
                    [$allowed] = kp_admin_limit('giftcode-write', (string)$admin['id'], 45, 3600);
                }
                if (!$allowed) throw new RuntimeException('Thao tác quá nhiều. Thử lại sau.');
                $code = trim((string)($_POST['code'] ?? ''));
                if ($action !== 'bulk_create' && !kp_giftcode_valid_code($code)) {
                    throw new RuntimeException('Code phải dài 4–50 ký tự, chỉ gồm chữ không dấu, số, _ hoặc -.');
                }
                $values = [];
                foreach (['xu','luong','luongLock','limit_use'] as $field) {
                    $n = filter_var($_POST[$field] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>0,'max_range'=>2147483647]]);
                    if ($n === false) throw new RuntimeException('Giá trị ' . $field . ' không hợp lệ.');
                    $values[$field] = (int)$n;
                }
                $rewards = kp_giftcode_validate_reward_payload(
                    (string)($_POST['reward_items'] ?? '[]'),
                    $rewardCatalog['item_map'],
                    $rewardCatalog['option_map'],
                    $dboEnabled,
                    $rewardCatalog['shop_map']
                );
                $item = kp_giftcode_serialize_rewards($rewards);
                $type = !empty($_POST['disabled']) ? 1 : 0;
                $meta = [
                    'public' => !empty($_POST['public']),
                    'title' => trim((string)($_POST['title'] ?? '')),
                    'description' => trim((string)($_POST['description'] ?? '')),
                ];
                if (strlen($meta['title']) > 150 || strlen($meta['description']) > 600) throw new RuntimeException('Tiêu đề hoặc mô tả quá dài.');
                if (!kp_giftcode_valid_item($item)) throw new RuntimeException('Chuỗi phần thưởng sinh ra không hợp lệ.');
                if (strlen($item) > $itemColumnCapacity) {
                    throw new RuntimeException('Phần thưởng dài '.strlen($item).' byte, vượt cột item hiện tại ('.$itemColumnCapacity.'). Hãy chạy migration giftcode_item_options.sql.');
                }
                // Game Java reads this table directly. A disabled code must remain type != 0.
                if ($action === 'bulk_create') {
                    $count = filter_var($_POST['bulk_count'] ?? null, FILTER_VALIDATE_INT,
                        ['options'=>['min_range'=>1,'max_range'=>100]]);
                    if ($count === false || $count === null) {
                        throw new RuntimeException('Chỉ được tạo 1–100 Giftcode mỗi lần.');
                    }
                    $prefix = kp_giftcode_bulk_prefix($_POST['bulk_prefix'] ?? '');
                    // Consume this one-time nonce before any MyISAM inserts. Reposting
                    // the same request must not produce an accidental second batch.
                    $bulkNonce = bin2hex(random_bytes(16));
                    $_SESSION['kp_giftcode_bulk_nonce'] = $bulkNonce;
                    $bulkResult = kp_giftcode_bulk_create(
                        $pdo, $prefix, (int)$count, $values, $item, $meta, $type !== 0
                    );
                    $done = count($bulkResult['codes']);
                    if ($bulkResult['error'] !== '') {
                        $error = $bulkResult['error'] . ' Đã hoàn tất ' . $done . '/' . $count . ' mã.';
                    } else {
                        $notice = 'Đã tạo ' . $done . ' Giftcode riêng tư. '
                            . 'Mỗi mã có ' . number_format($values['limit_use']) . ' lượt sử dụng.';
                    }
                } elseif ($action === 'create') {
                    $dup = $pdo->prepare('SELECT id FROM giftcode WHERE giftcode = ? LIMIT 1');
                    $dup->execute([$code]);
                    if ($dup->fetchColumn()) throw new RuntimeException('Giftcode đã tồn tại.');
                    $st = $pdo->prepare('INSERT INTO giftcode (giftcode, xu, luong, luongLock, item, expire, limit_use, type) VALUES (?, ?, ?, ?, ?, 0, ?, ?)');
                    $st->execute([$code,$values['xu'],$values['luong'],$values['luongLock'],$item === '' ? null : $item,$values['limit_use'],$type]);
                    $id = (int)$pdo->lastInsertId();
                    try { kp_giftcode_catalog_set($id, $meta); }
                    catch (Throwable $e) {
                        // Fail closed: an unconfigured code must not remain redeemable.
                        $pdo->prepare('UPDATE giftcode SET type = 1 WHERE id = ?')->execute([$id]);
                        throw $e;
                    }
                    $notice = 'Đã tạo giftcode.';
                } else {
                    if (!$id) throw new RuntimeException('ID không hợp lệ.');
                    $original = $pdo->prepare('SELECT giftcode, item FROM giftcode WHERE id = ?');
                    $original->execute([$id]);
                    $old = $original->fetch(PDO::FETCH_ASSOC);
                    if (!$old) throw new RuntimeException('Không tìm thấy giftcode.');
                    if ((string)$old['giftcode'] !== $code) throw new RuntimeException('Không được đổi chuỗi code vì cần giữ lịch sử người chơi đã nhập.');
                    // Special legacy tokens (PT/GEM/PET/...) are not editable here, but must survive updates.
                    $legacy = kp_giftcode_parse_rewards((string)$old['item'], $rewardCatalog['item_map'], $rewardCatalog['shop_map'])['legacy'];
                    if ($legacy) $item = implode(',', array_filter([$item, implode(',', $legacy)], 'strlen'));
                    if (!kp_giftcode_valid_item($item)) throw new RuntimeException('Chuỗi phần thưởng sau khi ghép token cũ không hợp lệ.');
                    if (strlen($item) > $itemColumnCapacity) {
                        throw new RuntimeException('Phần thưởng dài '.strlen($item).' byte, vượt cột item hiện tại ('.$itemColumnCapacity.'). Hãy chạy migration giftcode_item_options.sql.');
                    }
                    $st = $pdo->prepare('UPDATE giftcode SET xu = ?, luong = ?, luongLock = ?, item = ?, limit_use = ?, type = ? WHERE id = ?');
                    $st->execute([$values['xu'],$values['luong'],$values['luongLock'],$item === '' ? null : $item,$values['limit_use'],$type,$id]);
                    kp_giftcode_catalog_set($id, $meta);
                    $notice = 'Đã cập nhật giftcode.';
                }
            } elseif ($action === 'delete') {
                if (!$id) throw new RuntimeException('ID không hợp lệ.');
                $st = $pdo->prepare('SELECT giftcode FROM giftcode WHERE id = ? LIMIT 1');
                $st->execute([$id]);
                $code = $st->fetchColumn();
                if ($code === false) throw new RuntimeException('Giftcode không tồn tại.');
                $count = $pdo->prepare('SELECT COUNT(*) FROM giftcode_log WHERE giftcode = ?');
                $count->execute([$code]);
                if ((int)$count->fetchColumn() > 0) {
                    // Keep reward history intact; archive used codes instead of deleting.
                    $pdo->prepare('UPDATE giftcode SET type = 1 WHERE id = ?')->execute([$id]);
                    kp_giftcode_catalog_set($id, null);
                    $notice = 'Code đã có lịch sử sử dụng nên đã khóa và ẩn thay vì xóa.';
                } else {
                    // Hide/disable before deletion to avoid exposing a code on concurrent requests.
                    $pdo->prepare('UPDATE giftcode SET type = 1 WHERE id = ?')->execute([$id]);
                    kp_giftcode_catalog_set($id, null);
                    $pdo->prepare('DELETE FROM giftcode WHERE id = ?')->execute([$id]);
                    $notice = 'Đã xóa code chưa có lượt nhận.';
                }
            } else throw new RuntimeException('Hành động không hợp lệ.');
        } catch (RuntimeException $e) {
            $error = $e->getMessage();
        } catch (PDOException $e) {
            kp_log('giftcode admin DB operation failed', $e);
            $error = 'Lỗi dữ liệu hoặc code bị trùng. Kiểm tra kết nối và thử lại.';
        } catch (Throwable $e) {
            kp_log('giftcode admin operation failed', $e);
            $error = 'Không lưu được thay đổi. Kiểm tra quyền ghi storage/giftcode.';
        }
    }
}
$codes = [];
$logs = [];
$catalog = [];
$totalLogs = 0;
$filter = trim((string)($_GET['code'] ?? ''));
$playerFilter = trim((string)($_GET['player'] ?? ''));
$page = max(1, min(10000, (int)($_GET['page'] ?? 1)));
$limit = 30;
try {
    $codes = $pdo->query('SELECT id, giftcode, xu, luong, luongLock, item, expire, limit_use, type FROM giftcode ORDER BY id DESC LIMIT 300')->fetchAll(PDO::FETCH_ASSOC);
    $catalog = kp_giftcode_catalog_read();
    $where = [];
    $params = [];
    if ($filter !== '') {
        $where[] = 'giftcode = ?';
        $params[] = substr($filter, 0, 255);
    }
    if ($playerFilter !== '') {
        $where[] = 'player LIKE ?';
        $params[] = '%' . str_replace(['%', '_', '\\'], ['\\%', '\\_', '\\\\'], substr($playerFilter,0,80)) . '%';
    }
    $cond = $where ? ' WHERE ' . implode(' AND ', $where) : '';
    $count = $pdo->prepare('SELECT COUNT(*) FROM giftcode_log' . $cond);
    $count->execute($params);
    $totalLogs = (int)$count->fetchColumn();
    $offset = ($page - 1) * $limit;
    $st = $pdo->prepare('SELECT id, giftcode, player, item, xu, luong, luongK FROM giftcode_log' . $cond . ' ORDER BY id DESC LIMIT ' . $limit . ' OFFSET ' . $offset);
    $st->execute($params);
    $logs = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    kp_log('giftcode admin list failed', $e);
    $error = $error ?: 'Không tải được dữ liệu giftcode.';
}

function kp_admin_giftcode_reward_builder($builderId, array $parsed, $catalogReady, $dboEnabled, $capacity)
{
    $initial = json_encode($parsed['rewards'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP);
    if (!is_string($initial)) $initial = '[]';
    ?>
    <section class="gc-reward-builder gc-full" data-gc-builder="<?= kp_e($builderId) ?>" data-capacity="<?= (int)$capacity ?>">
        <div class="gc-reward-head">
            <div><span>VẬT PHẨM TRAO TRONG GAME</span><h3>Chọn item & cấu hình chỉ số</h3><p>Dữ liệu từ <code>data_item</code>, <code>data_shop</code> và <code>data_attribute</code>. ShopTemplate chỉ được phát khi server Java hỗ trợ token SHOP.</p></div>
            <button type="button" class="gc-add-item"<?= $catalogReady ? '' : ' disabled' ?>>＋ Chọn vật phẩm</button>
        </div>
        <?php if (!$dboEnabled): ?>
            <div class="gc-server-lock"><strong>Option tùy chỉnh đang khóa an toàn.</strong><span>Item mặc định vẫn lưu bằng token DB. Áp patch Java, migration rồi đặt <code>KPAH_GIFTCODE_DBO_ENABLED=1</code> để bật DBO.</span></div>
        <?php endif; ?>
        <?php if ($parsed['legacy']): ?>
            <div class="gc-legacy"><strong>Token cũ được bảo toàn tự động</strong><code><?= kp_e(implode(',', $parsed['legacy'])) ?></code><small>Các loại PT/GEM/PET hoặc item không còn trong data_item không bị form ghi đè.</small></div>
        <?php endif; ?>
        <div class="gc-selected-items" aria-live="polite"></div>
        <div class="gc-reward-empty"><b>Chưa chọn vật phẩm</b><span>Nhấn “Chọn vật phẩm” để thêm phần thưởng. Không cần nhập chuỗi SQL bằng tay.</span></div>
        <div class="gc-reward-summary"><span><b data-gc-item-count>0</b> cấu hình item</span><span><b data-gc-option-count>0</b> option tùy chỉnh</span><span><b data-gc-byte-count>0</b>/<?= number_format((int)$capacity) ?> byte SQL</span></div>
        <textarea name="reward_items" class="gc-reward-json" hidden><?= kp_e($initial) ?></textarea>
        <script type="application/json" class="gc-reward-initial"><?= $initial ?></script>
    </section>
    <?php
}
?><!doctype html>
<html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow"><title>Quản lý Giftcode · NGHIỆN KPAH</title>
<link rel="stylesheet" href="/assets/css/giftcodes.css?v=<?= (int)@filemtime(KP_ROOT.'/assets/css/giftcodes.css') ?>">
<link rel="stylesheet" href="/assets/css/admin-nav.css?v=<?= (int)@filemtime(KP_ROOT.'/assets/css/admin-nav.css') ?>">
<link rel="stylesheet" href="/assets/css/site-responsive.css?v=<?= (int)@filemtime(KP_ROOT.'/assets/css/site-responsive.css') ?>">
<link rel="stylesheet" href="/assets/css/admin-theme.css?v=<?= (int)@filemtime(KP_ROOT.'/assets/css/admin-theme.css') ?>">
<script src="/assets/js/admin-giftcode-builder.js?v=<?= (int)@filemtime(KP_ROOT.'/assets/js/admin-giftcode-builder.js') ?>" defer></script>
</head><body class="gc-admin">
<header class="gc-top"><a href="/quan-tri">← Quản trị</a><strong>NGHIỆN KPAH · GIFTCODE</strong><span><?= kp_e($admin['username']) ?></span></header>
<?php kp_admin_nav('giftcodes','admin'); ?>
<main class="gc-shell">
<div class="gc-heading"><div><span>QUẢN LÝ QUÀ TẶNG</span><h1>Giftcode & lịch sử sử dụng</h1><p>Code phát cho người chơi ở trang chủ, người chơi nhập trực tiếp trong game.</p></div><a href="/#giftcode">Xem trang chủ ↗</a></div>
<?php if ($notice): ?><div class="gc-alert gc-ok"><?= kp_e($notice) ?></div><?php endif; ?>
<?php if ($error): ?><div class="gc-alert gc-error"><?= kp_e($error) ?></div><?php endif; ?>
<?php if ($rewardCatalogError): ?><div class="gc-alert gc-error"><strong>Catalog vật phẩm chưa sẵn sàng.</strong> <?= kp_e($rewardCatalogError) ?></div><?php endif; ?>
<?php if ($itemColumnCapacity <= 255): ?><div class="gc-alert gc-warning"><strong>Chưa chạy migration item TEXT.</strong> Chuỗi thưởng đang bị giới hạn <?= (int)$itemColumnCapacity ?> byte; form sẽ chặn trước khi SQL cắt dữ liệu.</div><?php endif; ?>
<section class="gc-panel"><h2>Tạo giftcode mới</h2><p>Code mặc định công khai chỉ khi bạn tích “Hiển thị trang chủ”. Với mã cũ, mục hiển thị được lưu riêng, không cần ALTER TABLE.</p>
<form method="post" class="gc-form">
<input type="hidden" name="csrf" value="<?= kp_e(kp_csrf_token()) ?>"><input type="hidden" name="action" value="create">
<label>Mã giftcode<input name="code" maxlength="50" pattern="[A-Za-z0-9_-]{4,50}" required placeholder="KPAH2026"></label>
<label>Xu<input name="xu" type="number" min="0" max="2147483647" value="0" required></label>
<label>Lượng<input name="luong" type="number" min="0" max="2147483647" value="0" required></label>
<label>Lượng khóa<input name="luongLock" type="number" min="0" max="2147483647" value="0" required></label>
<label>Lượt còn lại<input name="limit_use" type="number" min="0" max="2147483647" value="100" required></label>
<label>Tiêu đề trên trang chủ<input name="title" maxlength="150" placeholder="Quà chào mừng"></label>
<label class="gc-full">Mô tả công khai<textarea name="description" maxlength="600" rows="2" placeholder="Hướng dẫn và sự kiện..."></textarea></label>
<?php kp_admin_giftcode_reward_builder('create', ['rewards'=>[], 'legacy'=>[]], $rewardCatalogError === '', $dboEnabled, $itemColumnCapacity); ?>
<label class="gc-check"><input type="checkbox" name="public" value="1"> Hiển thị trên trang chủ</label>
<label class="gc-check"><input type="checkbox" name="disabled" value="1"> Tạm khóa code (type = 1)</label>
<div class="gc-full"><button type="submit">Tạo giftcode ↗</button></div>
</form></section>
<section class="gc-panel" id="gc-bulk-create">
<h2>Tạo hàng loạt Giftcode <small>(1–100 mã/lần)</small></h2>
<p>Tạo nhiều <strong>Giftcode riêng biệt</strong> trong một lần, mỗi mã có cùng Xu/Lượng/vật phẩm và số lượt sử dụng. Mã được sinh ngẫu nhiên, không trùng và <strong>luôn ẩn khỏi trang chủ</strong> để phân phát riêng.</p>
<form method="post" class="gc-form" id="gc-bulk-form">
<input type="hidden" name="csrf" value="<?= kp_e(kp_csrf_token()) ?>">
<input type="hidden" name="bulk_nonce" value="<?= kp_e($bulkNonce) ?>">
<input type="hidden" name="action" value="bulk_create">
<label>Tiền tố mã<input name="bulk_prefix" maxlength="30" pattern="[A-Za-z0-9_-]{3,30}" required value="KPAH" placeholder="KPAH"></label>
<label>Số mã cần tạo<input name="bulk_count" type="number" min="1" max="100" value="100" required></label>
<label>Lượt sử dụng / mã<input name="limit_use" type="number" min="0" max="2147483647" value="1" required></label>
<label>Xu / mã<input name="xu" type="number" min="0" max="2147483647" value="0" required></label>
<label>Lượng / mã<input name="luong" type="number" min="0" max="2147483647" value="0" required></label>
<label>Lượng khóa / mã<input name="luongLock" type="number" min="0" max="2147483647" value="0" required></label>
<label>Tiêu đề nội bộ<input name="title" maxlength="150" placeholder="Giftcode sự kiện"></label>
<label class="gc-full">Ghi chú / mô tả nội bộ<textarea name="description" maxlength="600" rows="2" placeholder="Đợt phát giftcode..."></textarea></label>
<?php kp_admin_giftcode_reward_builder('bulk', ['rewards'=>[], 'legacy'=>[]], $rewardCatalogError === '', $dboEnabled, $itemColumnCapacity); ?>
<label class="gc-check"><input type="checkbox" name="disabled" value="1"> Tạo các mã ở trạng thái khóa (chưa cho nhập)</label>
<p class="gc-full gc-bulk-warning">Giới hạn 5 lần tạo hàng loạt mỗi giờ. Mỗi mã mới được lưu ở trạng thái khóa trước khi xác nhận metadata, rồi mới kích hoạt. Không nhấn gửi lại nếu có lỗi giữa chừng.</p>
<div class="gc-full"><button type="submit">Tạo Giftcode hàng loạt</button></div>
</form>
<?php if ($bulkResult['codes']): ?>
<div class="gc-bulk-result" role="status">
<h3>Đã tạo <?= count($bulkResult['codes']) ?> mã</h3>
<p>Sao chép và lưu danh sách này để phát cho người chơi; chỉ hiển thị trong phản hồi tạo mã.</p>
<textarea id="gc-bulk-generated-codes" rows="8" readonly aria-label="Danh sách Giftcode vừa tạo"><?= kp_e(implode("\n", $bulkResult['codes'])) ?></textarea>
<button type="button" data-gc-copy-batch>Sao chép tất cả</button>
</div>
<?php endif; ?>
<?php if ($bulkResult['locked']): ?>
<div class="gc-alert gc-warning">Một số mã đã được tạo nhưng chưa kích hoạt; kiểm tra trước khi thử lại:
<code><?= kp_e(implode(', ', $bulkResult['locked'])) ?></code></div>
<?php endif; ?>
</section>
<section class="gc-panel"><h2>Danh sách giftcode <small>(300 mã mới nhất)</small></h2>
<p>Để sửa, thay số liệu rồi lưu. <strong>Đổi mã code đã tạo không được hỗ trợ</strong> nhằm giữ lịch sử nhập; xóa mã đã dùng sẽ tự động chuyển thành khóa.</p>
<div class="gc-code-list">
<?php foreach ($codes as $code): $id=(int)$code['id']; $meta=$catalog[(string)$id] ?? []; $parsedRewards=kp_giftcode_parse_rewards((string)$code['item'], $rewardCatalog['item_map'], $rewardCatalog['shop_map']); ?>
<details class="gc-code"><summary><strong><?= kp_e($code['giftcode']) ?></strong><span><?= (int)$code['type']===0 ? 'Đang bật' : 'Đã khóa' ?> · <?= number_format((int)$code['limit_use']) ?> lượt · <?= !empty($meta['public']) ? 'Công khai' : 'Ẩn' ?></span><b>Chỉnh sửa ↓</b></summary>
<form method="post" class="gc-form">
<input type="hidden" name="csrf" value="<?= kp_e(kp_csrf_token()) ?>"><input type="hidden" name="action" value="update"><input type="hidden" name="id" value="<?= $id ?>">
<label>Mã (không thể đổi)<input readonly name="code" value="<?= kp_e($code['giftcode']) ?>"></label>
<?php foreach (['xu'=>'Xu','luong'=>'Lượng','luongLock'=>'Lượng khóa','limit_use'=>'Lượt còn lại'] as $field=>$name): ?>
<label><?= kp_e($name) ?><input type="number" name="<?= kp_e($field) ?>" min="0" max="2147483647" value="<?= (int)$code[$field] ?>" required></label>
<?php endforeach; ?>
<label>Tiêu đề<input name="title" maxlength="150" value="<?= kp_e((string)($meta['title'] ?? '')) ?>"></label>
<label class="gc-full">Mô tả<textarea name="description" maxlength="600" rows="2"><?= kp_e((string)($meta['description'] ?? '')) ?></textarea></label>
<?php kp_admin_giftcode_reward_builder('edit-'.$id, $parsedRewards, $rewardCatalogError === '', $dboEnabled, $itemColumnCapacity); ?>
<label class="gc-check"><input type="checkbox" name="public" value="1"<?= !empty($meta['public']) ? ' checked' : '' ?>> Công khai</label>
<label class="gc-check"><input type="checkbox" name="disabled" value="1"<?= (int)$code['type']!==0 ? ' checked' : '' ?>> Tạm khóa</label>
<div class="gc-full gc-buttons"><button type="submit">Lưu thay đổi</button><button type="submit" name="action" value="delete" class="gc-danger" formnovalidate onclick="return confirm('Bạn chắc chắn muốn xóa hoặc lưu trữ giftcode này?')">Xóa / Lưu trữ</button><a href="/quan-tri/giftcode?code=<?= rawurlencode($code['giftcode']) ?>#gc-history">Xem người đã nhập ↗</a></div>
</form></details>
<?php endforeach; ?>
<?php if (!$codes): ?><p>Chưa có giftcode trong database.</p><?php endif; ?>
</div></section>
<section class="gc-panel" id="gc-history"><h2>Lịch sử người nhập giftcode</h2>
<p>Lịch sử từ bảng <code>giftcode_log</code>. Schema hiện tại không có thời gian nhận quà nên hiển thị theo ID mới nhất.</p>
<form method="get" class="gc-filters"><label>Mã code<input name="code" value="<?= kp_e($filter) ?>" maxlength="255" placeholder="Lọc code"></label><label>Tên nhân vật<input name="player" value="<?= kp_e($playerFilter) ?>" maxlength="80" placeholder="Tìm nhân vật"></label><button type="submit">Tìm kiếm</button><a href="/quan-tri/giftcode#gc-history">Xóa lọc</a></form>
<p>Tìm được <?= number_format($totalLogs) ?> lượt nhận.</p>
<div class="gc-table-wrap"><table><thead><tr><th>ID</th><th>Giftcode</th><th>Nhân vật</th><th>Xu</th><th>Lượng</th><th>Lượng khóa</th><th>Vật phẩm</th></tr></thead><tbody>
<?php foreach ($logs as $log): ?><tr><td><?= (int)$log['id'] ?></td><td><?= kp_e((string)$log['giftcode']) ?></td><td><?= kp_e((string)$log['player']) ?></td><td><?= number_format((int)$log['xu']) ?></td><td><?= number_format((int)$log['luong']) ?></td><td><?= number_format((int)$log['luongK']) ?></td><td class="gc-history-items-cell"><?= kp_giftcode_history_items_html((string)$log['item'], $rewardCatalog['item_map'], $rewardCatalog['option_map']) ?></td></tr><?php endforeach; ?>
<?php if (!$logs): ?><tr><td colspan="7">Không có dữ liệu phù hợp.</td></tr><?php endif; ?>
</tbody></table></div>
<?php $totalPages=(int)ceil($totalLogs/$limit); if ($totalPages>1): ?><nav class="gc-pagination" aria-label="Phân trang lịch sử"><?php if ($page>1): ?><a href="?<?= kp_e(http_build_query(['code'=>$filter,'player'=>$playerFilter,'page'=>$page-1])) ?>#gc-history">← Trang trước</a><?php endif; ?><span>Trang <?= $page ?>/<?= $totalPages ?></span><?php if ($page<$totalPages): ?><a href="?<?= kp_e(http_build_query(['code'=>$filter,'player'=>$playerFilter,'page'=>$page+1])) ?>#gc-history">Trang tiếp →</a><?php endif; ?></nav><?php endif; ?>
</section>
</main>
<?php
$catalogJson = json_encode(
    ['items'=>$rewardCatalog['items'], 'options'=>$rewardCatalog['options'], 'shop_items'=>$rewardCatalog['shop_items'], 'shop_enabled'=>kp_giftcode_shop_enabled(), 'dbo_enabled'=>$dboEnabled],
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP
);
if (!is_string($catalogJson)) $catalogJson = '{"items":[],"options":[],"dbo_enabled":false}';
?>
<script type="application/json" id="gc-reward-catalog"><?= $catalogJson ?></script>
<div class="gc-picker" id="gc-item-picker" role="dialog" aria-modal="true" aria-labelledby="gc-item-picker-title" hidden>
    <div class="gc-picker-backdrop" data-gc-close></div>
    <section class="gc-picker-panel">
        <header><div><span>DATA_ITEM + DATA_SHOP · NGUỒN GAME</span><h2 id="gc-item-picker-title">Chọn vật phẩm</h2><p>Tìm theo tên hoặc ID, lọc theo loại rồi thêm vào Giftcode.</p></div><button type="button" class="gc-picker-close" data-gc-close aria-label="Đóng">×</button></header>
        <div class="gc-source-tabs" role="group" aria-label="Nguồn vật phẩm"><button type="button" class="is-active" data-gc-source="data_item" aria-pressed="true">Trang bị · data_item</button><button type="button" data-gc-source="data_shop" aria-pressed="false">Vật phẩm Shop · data_shop (<?=count($rewardCatalog['shop_items'])?>)</button></div>
        <p class="gc-shop-help" id="gc-shop-help" hidden>ShopTemplate được đọc từ data_shop, khác ID data_item. Cần patch game server để dùng Giftcode SHOP; nếu chưa bật chỉ được xem.</p>
        <div class="gc-picker-tools"><label><span>Tìm nhanh</span><input type="search" id="gc-item-search" placeholder="Ví dụ: áo, kiếm, #120"></label><label><span>Loại vật phẩm</span><select id="gc-item-type"><option value="">Tất cả loại</option></select></label></div>
        <div class="gc-item-grid" id="gc-item-grid"></div>
        <div class="gc-picker-footer"><p class="gc-picker-result" id="gc-item-result"></p><button type="button" class="gc-item-more" id="gc-item-more" hidden>Hiển thị thêm vật phẩm</button></div>
    </section>
</div>
<div class="gc-picker" id="gc-option-picker" role="dialog" aria-modal="true" aria-labelledby="gc-option-picker-title" hidden>
    <div class="gc-picker-backdrop" data-gc-close></div>
    <section class="gc-picker-panel gc-option-panel">
        <header><div><span>DATA_ATTRIBUTE · <?= number_format(count($rewardCatalog['options'])) ?> OPTION</span><h2 id="gc-option-picker-title">Thêm chỉ số</h2><p>Giới hạn param thay đổi theo kiểu mảng Java.</p></div><button type="button" class="gc-picker-close" data-gc-close aria-label="Đóng">×</button></header>
        <div class="gc-picker-tools"><label><span>Tìm option</span><input type="search" id="gc-option-search" placeholder="Tên chỉ số hoặc #ID"></label><label><span>Nhóm lưu</span><select id="gc-option-group"><option value="">Tất cả nhóm</option><option value="atb">atb</option><option value="newAtb">newAtb</option><option value="addMoreLevelSkill">Kỹ năng</option><option value="lockAtb">Khóa</option><option value="otherAtt">Mở rộng</option></select></label></div>
        <div class="gc-option-list" id="gc-option-list"></div>
        <p class="gc-picker-result" id="gc-option-result"></p>
    </section>
</div>
</body></html>
