<?php
require __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/admin_security.php';
require_once __DIR__ . '/../includes/admin_nav.php';
require_once __DIR__ . '/../includes/game_downloads.php';
kp_send_security_headers(true);
header('Content-Type: text/html; charset=utf-8');
$admin = kp_admin_require('/quan-tri/dang-nhap', ['admin']);
$error = '';
$errorStatus = 422;
$uploadMaxBytes = kp_game_download_ini_bytes(ini_get('upload_max_filesize'));
$postMaxBytes = kp_game_download_ini_bytes(ini_get('post_max_size'));
$appMaxBytes = 512 * 1024 * 1024;
$isAjax = strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest'
    || stripos((string)($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json') !== false;
$notice = (string)($_SESSION['kp_download_notice'] ?? '');
unset($_SESSION['kp_download_notice']);
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    try {
        if (empty($_POST) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
            $receivedBytes = (int)$_SERVER['CONTENT_LENGTH'];
            if ($postMaxBytes > 0 && $receivedBytes > $postMaxBytes) {
                $errorStatus = 413;
                throw new RuntimeException('Yêu cầu ' . number_format($receivedBytes / 1048576, 1, ',', '.') . ' MB vượt post_max_size của PHP (' . ini_get('post_max_size') . '). Tăng giới hạn trong php.ini của Apache và khởi động lại Apache, hoặc dùng link HTTPS bên ngoài.');
            }
            $errorStatus = 400;
            throw new RuntimeException('PHP không đọc được form upload. Kiểm tra Content-Type multipart/form-data, cấu hình Apache/PHP và nhật ký lỗi máy chủ.');
        }
        if (!kp_admin_csrf_valid($_POST['csrf'] ?? '')) {
            $errorStatus = 403;
            throw new RuntimeException('Phiên quản trị hoặc mã xác thực CSRF không hợp lệ. Tải lại trang quản trị rồi thử lại.');
        }
        list($allowed) = kp_admin_limit('game-download-update', (string)$admin['id'], 30, 3600);
        if (!$allowed) {
            $errorStatus = 429;
            throw new RuntimeException('Đã thao tác quá nhiều lần. Thử lại sau.');
        }
        kp_game_download_save($_POST, $_FILES);
        kp_admin_log_event('game_downloads_updated', (int)$admin['id']);
        if ($isAjax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => true, 'message' => 'Đã lưu cấu hình tải game.']);
            exit;
        }
        $_SESSION['kp_download_notice'] = 'Đã lưu cấu hình tải game. Trang chủ cập nhật ngay.';
        header('Location: /quan-tri/tai-game', true, 303);
        exit;
    } catch (RuntimeException $e) {
        $error = $e->getMessage();
    } catch (Throwable $e) {
        $errorStatus = 500;
        kp_log('game downloads admin save failed', $e);
        $error = 'Không lưu được. Kiểm tra quyền ghi storage/game-downloads và giới hạn PHP.';
    }
}
if ($isAjax && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    http_response_code($errorStatus);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'message' => $error ?: 'Không thể lưu cấu hình.'], JSON_UNESCAPED_UNICODE);
    exit;
}
$rows = kp_game_download_catalog();
$csrf = kp_csrf_token();
$platforms = kp_game_platforms();
?><!doctype html>
<html lang="vi"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Quản lý tải game · NGHIỆN KPAH</title>
<link rel="stylesheet" href="/assets/css/admin.css?v=<?= (int)@filemtime(KP_ROOT.'/assets/css/admin.css') ?>">
<link rel="stylesheet" href="/assets/css/admin-nav.css?v=<?= (int)@filemtime(KP_ROOT.'/assets/css/admin-nav.css') ?>">
<link rel="stylesheet" href="/assets/css/site-responsive.css?v=<?= (int)@filemtime(KP_ROOT.'/assets/css/site-responsive.css') ?>">
<link rel="stylesheet" href="/assets/css/admin-downloads.css?v=<?= (int)@filemtime(KP_ROOT.'/assets/css/admin-downloads.css') ?>">
<link rel="stylesheet" href="/assets/css/admin-theme.css?v=<?= (int)@filemtime(KP_ROOT.'/assets/css/admin-theme.css') ?>">
</head><body>
<header><div class="brand"><div class="brand-mark"><img src="/assets/img/emblem.webp" width="50" height="50" alt=""></div><div class="brand-copy"><span class="eyebrow">NGHIỆN KPAH · QUẢN TRỊ</span><h1>Quản lý bản tải game</h1></div></div>
<div class="head-right"><a class="logout" href="/">Xem trang chủ ↗</a><span><?= kp_e($admin['username']) ?></span><a class="logout" href="/quan-tri/dang-xuat">Đăng xuất ↗</a></div></header>
<?php kp_admin_nav('downloads', 'admin'); ?>
<main class="wrap dl-wrap">
<section class="hero dl-hero"><div><div class="eyebrow">GAME DISTRIBUTION / QUẢN TRỊ</div><h2>Phát hành game <em>chủ động.</em></h2><p>Bật hoặc tắt từng bản tải Windows, Java JAR, Android và iOS; dán link HTTPS hoặc upload file lên VPS. Không cần sửa code hay database.</p></div><a class="dl-preview" href="/#tai-game" target="_blank" rel="noopener noreferrer">Xem khu tải game ↗</a></section>
<?php if ($notice): ?><div class="notice" role="status"><?= kp_e($notice) ?></div><?php endif; ?>
<?php if ($error): ?><div class="error" role="alert"><?= kp_e($error) ?></div><?php endif; ?>
<form id="dl-form" method="post" enctype="multipart/form-data" action="/quan-tri/tai-game" class="dl-form" data-upload-max-bytes="<?= (int)$uploadMaxBytes ?>" data-post-max-bytes="<?= (int)$postMaxBytes ?>" data-app-max-bytes="<?= (int)$appMaxBytes ?>">
<input type="hidden" name="csrf" value="<?= kp_e($csrf) ?>">
<input type="hidden" name="MAX_FILE_SIZE" value="536870912">
<div class="dl-grid">
<?php foreach ($platforms as $key => $platform): $row = $rows[$key]; ?>
<section class="card dl-card">
<div class="dl-card-top"><div class="dl-platform"><span class="dl-platform-icon" aria-hidden="true"><?= $key === 'windows' ? '▦' : ($key === 'android' ? '▣' : ($key === 'java' ? '☕' : '◈')) ?></span><div><span class="section-kicker">BẢN PHÁT HÀNH</span><h2><?= kp_e($platform['name']) ?></h2></div></div><span class="pill<?= $row['enabled'] ? '' : ' off' ?>"><?= $row['enabled'] ? 'Đang mở' : 'Đang tắt' ?></span></div>
<label class="dl-toggle"><input type="checkbox" name="enabled[<?= kp_e($key) ?>]" value="1" <?= $row['enabled'] ? 'checked' : '' ?>><span class="dl-switch" aria-hidden="true"></span><span><strong>Cho phép người chơi tải</strong><small>Bỏ tích để ẩn nút tải trên trang chủ.</small></span></label>
<div class="dl-field"><label for="source-<?= kp_e($key) ?>">Nguồn tải</label><select id="source-<?= kp_e($key) ?>" name="source[<?= kp_e($key) ?>]"><option value="url"<?= $row['source'] === 'url' ? ' selected' : '' ?>>Link tải bên ngoài (khuyên dùng)</option><option value="file"<?= $row['source'] === 'file' ? ' selected' : '' ?>>File upload lên VPS</option></select></div>
<div class="dl-field"><label for="url-<?= kp_e($key) ?>">Link tải HTTPS</label><input id="url-<?= kp_e($key) ?>" type="text" inputmode="url" name="url[<?= kp_e($key) ?>]" value="<?= kp_e($row['url']) ?>" maxlength="2048" placeholder="https://..."><small>Dùng khi chọn nguồn link. Hỗ trợ GitHub Releases, CDN hoặc máy chủ tải file.</small></div>
<div class="dl-field"><label for="upload-<?= kp_e($key) ?>">Upload file <?= kp_e($platform['hint']) ?></label><input id="upload-<?= kp_e($key) ?>" type="file" name="upload[<?= kp_e($key) ?>]" accept="<?= $key === 'windows' ? '.zip,.exe,.msi' : ($key === 'android' ? '.apk' : ($key === 'java' ? '.jar' : '.ipa')) ?>"><small>Chọn nguồn "File upload lên VPS" trước khi lưu. Không chọn file mới thì giữ file cũ.</small></div>
<div class="dl-file-status">
<?php if ($row['file'] !== ''): ?>
<span class="dl-file-label">File đang lưu</span>
<strong><?= kp_e($row['filename'] ?: $row['file']) ?></strong>
<span><?= number_format($row['size'] / 1048576, 1, ',', '.') ?> MB · <?= kp_e($row['updated_at'] ?: 'Đã lưu') ?></span>
<label class="dl-remove"><input type="checkbox" name="remove[<?= kp_e($key) ?>]" value="1"> Xóa file cũ khỏi VPS khi lưu</label>
<?php if ($row['source'] === 'file' && $row['enabled']): ?><a href="/tai-game/tep?nen-tang=<?= kp_e($key) ?>" target="_blank" rel="noopener noreferrer">Kiểm tra link tải ↗</a><?php endif; ?>
<?php else: ?><span class="dl-empty">Chưa upload file cho nền tảng này.</span><?php endif; ?>
</div>
</section>
<?php endforeach; ?>
</div>
<div class="dl-progress" id="dl-progress" hidden role="status" aria-live="polite"><div class="dl-progress-row"><strong id="dl-progress-label">Đang tải file lên...</strong><span id="dl-progress-value">0%</span></div><progress id="dl-progress-bar" max="100" value="0"></progress><p id="dl-progress-message"></p></div>
<div class="dl-savebar"><div><strong>Thay đổi sẽ áp dụng ngay sau khi lưu</strong><small>Bỏ tích sẽ ẩn nút tải và chặn URL file nội bộ; link ngoài do nhà cung cấp quản lý.</small></div><button class="dl-save" type="submit">Lưu cấu hình tải game ↗</button></div>
</form>
<section class="card dl-guide"><h2>Lưu ý khi upload trực tiếp lên VPS</h2>
<p>Giới hạn ứng dụng: <strong>512 MB / file</strong>. PHP và Apache có thể đặt giới hạn thấp hơn. Với XAMPP, tăng <code>upload_max_filesize</code>, <code>post_max_size</code>, <code>max_execution_time</code> trong <code>php.ini</code> rồi khởi động lại Apache. Dung lượng thực tế PHP hiện tại: upload <strong><?= kp_e(ini_get('upload_max_filesize')) ?></strong>, POST <strong><?= kp_e(ini_get('post_max_size')) ?></strong>.</p>
<?php if (($uploadMaxBytes > 0 && $uploadMaxBytes < $appMaxBytes) || ($postMaxBytes > 0 && $postMaxBytes < $appMaxBytes)): ?><p class="dl-upload-warning" role="note">Lưu ý: PHP trên máy chủ đang giới hạn dung lượng thấp hơn 512 MB. File lớn hơn giới hạn này sẽ bị từ chối dù website hỗ trợ tối đa 512 MB. Với bản game lớn, ưu tiên GitHub Releases/CDN và chọn nguồn tải <strong>Link tải bên ngoài</strong>.</p><?php endif; ?>
<p>File được lưu trong <code>storage/game-downloads</code> và chỉ phát qua route tải riêng, không thực thi file trên máy chủ. Nếu nhiều người tải hoặc file lớn, dùng GitHub Releases/CDN sẽ tiết kiệm băng thông và tài nguyên VPS hơn. IPA trên iPhone còn cần phương thức cài đặt/ký ứng dụng phù hợp; tải IPA không đồng nghĩa cài trực tiếp được.</p>
</section>
</main><script src="/assets/js/admin-downloads.js?v=<?= (int)@filemtime(KP_ROOT.'/assets/js/admin-downloads.js') ?>" defer></script></body></html>
