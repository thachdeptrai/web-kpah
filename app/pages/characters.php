<?php
require __DIR__.'/../includes/bootstrap.php';
require_once __DIR__.'/../includes/player_portal.php';
$user=kp_player_account();
$characters=[];
$charactersUnavailable=false;
try { $characters=kp_player_characters($user['id'],16); }
catch (Throwable $e) { kp_log('player characters unavailable',$e); $charactersUnavailable=true; }
$numberFormat=function($v){return kp_player_format($v);};
kp_player_head('Nhân vật của tôi','characters',$user,true);
?>
<section class="portal-hero portal-hero--compact">
    <div class="portal-hero-copy"><span class="portal-kicker"><i></i> THẾ GIỚI KPAH</span>
    <h1>Nhân vật <em>của tôi.</em></h1><p>Xem cấp độ, kinh nghiệm và tài nguyên của những nhân vật bạn đang chơi.</p></div>
    <a class="portal-hero-action" href="/bang-xep-hang">Xem bảng xếp hạng ↗</a>
</section>
<section class="characters-section" id="characters" aria-labelledby="characters-title">
        <div class="characters-intro">
            <div class="characters-intro-copy">
                <p class="characters-eyebrow"><span class="characters-eyebrow-glyph" aria-hidden="true">✦</span> HỒ SƠ NHÂN VẬT <span class="characters-eyebrow-line" aria-hidden="true"></span> KPAH</p>
                <h2 id="characters-title">Nhân vật <em>của bạn</em></h2>
                <p class="characters-intro-note">Các nhân vật của bạn trong KPAH.</p>
            </div>
            <?php if (!$charactersUnavailable && !empty($characters)): ?>
                <div class="characters-count" aria-label="Đang hiển thị <?= count($characters) ?> nhân vật">
                    <span class="characters-count-number"><?= str_pad((string) count($characters), 2, '0', STR_PAD_LEFT) ?></span>
                    <span class="characters-count-copy">NHÂN VẬT<br>ĐANG HIỂN THỊ</span>
                    <span class="characters-count-ornament" aria-hidden="true">✧</span>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($charactersUnavailable): ?>
            <div class="char-ui-empty" role="status">
                <div class="char-ui-empty-seal" aria-hidden="true">✧</div>
                <div><h3>Chưa tải được nhân vật</h3><p>Thử tải lại trang sau ít phút nhé.</p></div>
            </div>
        <?php elseif (empty($characters)): ?>
            <div class="char-ui-empty">
                <div class="char-ui-empty-seal" aria-hidden="true">✧</div>
                <div><h3>Chưa có nhân vật nào</h3><p>Vào game tạo nhân vật, thông tin sẽ xuất hiện tại đây.</p></div>
            </div>
        <?php else: ?>
            <div class="characters-grid">
                <?php foreach ($characters as $index => $character): ?>
                    <?php
                    $charName = (string) ($character['charname'] ?? '');
                    $clanName = trim((string) ($character['clanName'] ?? ''));
                    $charInitial = function_exists('mb_substr')
                        ? mb_strtoupper(mb_substr($charName, 0, 1, 'UTF-8'), 'UTF-8')
                        : strtoupper(substr($charName, 0, 1));
                    $lastSeen = '';
                    if (!empty($character['lastLog']) && (string) $character['lastLog'] !== '2010-01-01 00:00:00') {
                        $stamp = strtotime((string) $character['lastLog']);
                        if ($stamp !== false) {
                            $lastSeen = date('d/m/Y H:i', $stamp);
                        }
                    }
                    ?>
                    <article class="char-ui-card<?= $index === 0 ? ' char-ui-card--featured' : '' ?>">
                        <div class="char-ui-corner char-ui-corner--tl" aria-hidden="true"></div>
                        <div class="char-ui-corner char-ui-corner--br" aria-hidden="true"></div>
                        <div class="char-ui-card-top">
                            <span class="char-ui-index"><span aria-hidden="true">✦</span> NHÂN VẬT <?= str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) ?></span>
                            <span class="char-ui-top-mark" aria-hidden="true">KPAH <i></i> HỒ SƠ</span>
                        </div>
                        <div class="char-ui-card-layout">
                            <div class="char-ui-portrait" aria-hidden="true">
                                <div class="char-ui-portrait-glow"></div>
                                <svg class="char-ui-crest" viewBox="0 0 200 200" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M100 12 164 46 179 115 143 170 100 191 57 170 21 115 36 46 100 12Z" stroke="currentColor" stroke-width="1.4"/>
                                    <path d="m100 30 48 28 14 57-28 42-34 17-34-17-28-42 14-57 48-28Z" stroke="currentColor" stroke-width=".8"/>
                                    <path d="M100 6v26M100 169v25M5 100h26M169 100h26M32 32l18 18m100 100 18 18M168 32l-18 18M50 150l-18 18" stroke="currentColor" stroke-width="1.4"/>
                                    <path d="m100 50 12 27 29 3-22 20 7 29-26-14-26 14 7-29-22-20 29-3 12-27Z" stroke="currentColor" stroke-width=".75" opacity=".65"/>
                                </svg>
                                <span class="char-ui-avatar"><?= kp_e($charInitial) ?></span>
                                <span class="char-ui-portrait-foot">KPAH · ANH HÙNG</span>
                            </div>
                            <div class="char-ui-card-content">
                                <div class="char-ui-identity">
                                    <div class="char-ui-identity-text">
                                        <span class="char-ui-caption">TÊN NHÂN VẬT</span>
                                        <h3><?= kp_e($charName) ?></h3>
                                        <p class="char-ui-clan">
                                            <svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M10 1.8 16.4 5.2v7.2L10 18l-6.4-5.6V5.2L10 1.8Z M10 6v7m-3.5-3.5h7" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                            <?= $clanName !== '' ? kp_e($clanName) : 'Chưa gia nhập bang hội' ?>
                                        </p>
                                    </div>
                                    <div class="char-ui-level">
                                        <span>CẤP ĐỘ</span>
                                        <strong><?= (int) ($character['lastLv'] ?? 0) ?></strong>
                                    </div>
                                </div>
                                <dl class="char-ui-stats">
                                    <div class="char-ui-stat char-ui-stat--xp">
                                        <dt><span class="char-ui-stat-icon" aria-hidden="true">✧</span> Kinh nghiệm</dt>
                                        <dd><?= $numberFormat($character['xp'] ?? 0) ?></dd>
                                    </div>
                                    <div class="char-ui-stat char-ui-stat--gold">
                                        <dt><span class="char-ui-stat-icon" aria-hidden="true">●</span> Xu</dt>
                                        <dd><?= $numberFormat($character['gold'] ?? 0) ?></dd>
                                    </div>
                                    <div class="char-ui-stat char-ui-stat--silver">
                                        <dt><span class="char-ui-stat-icon" aria-hidden="true">◆</span> Lượng</dt>
                                        <dd><?= $numberFormat($character['luong'] ?? 0) ?></dd>
                                    </div>
                                    <div class="char-ui-stat char-ui-stat--locked">
                                        <dt><span class="char-ui-stat-icon" aria-hidden="true">◇</span> Lượng khóa</dt>
                                        <dd><?= $numberFormat($character['luonglock'] ?? 0) ?></dd>
                                    </div>
                                    <div class="char-ui-stat char-ui-stat--farmer">
                                        <dt><span class="char-ui-stat-icon" aria-hidden="true">✧</span> Điểm nông dân</dt>
                                        <dd><?= ($character['diemNongDan'] ?? null) === null ? '—' : $numberFormat($character['diemNongDan']) ?></dd>
                                    </div>
                                </dl>
                                <div class="char-ui-card-bottom">
                                    <span class="char-ui-last">
                                        <svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><circle cx="10" cy="10" r="7.2" stroke="currentColor" stroke-width="1.5"/><path d="M10 5.8v4.7l3.1 1.7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                                        <?= $lastSeen !== '' ? 'Đăng nhập gần nhất: ' . kp_e($lastSeen) : 'Chưa có lịch sử đăng nhập' ?>
                                    </span>
                                    <span class="char-ui-bottom-mark" aria-hidden="true">✦</span>
                                </div>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    
<div class="portal-return"><a href="/tai-khoan">← Tổng quan</a><a href="/ho-so">Chỉnh hồ sơ ↗</a></div>
<?php kp_player_foot(); ?>
