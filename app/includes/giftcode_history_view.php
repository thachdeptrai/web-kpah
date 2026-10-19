<?php
/**
 * Read-only Giftcode history presentation.
 * Keep every game DB/DBO/legacy token unchanged in SQL; render only escaped
 * catalog labels and safe item icons for authorized admins.
 */
function kp_giftcode_history_option_value($param, array $meta)
{
    $multiplier = (int)($meta['display_multiplier'] ?? 1);
    if ($multiplier < 1) $multiplier = 1;
    return number_format((int)$param * $multiplier, 0, ',', '.')
        . (!empty($meta['percent']) ? '%' : '');
}

function kp_giftcode_history_option_html($optionId, $param, array $optionMap)
{
    $meta = $optionMap[(int)$optionId] ?? [];
    $label = trim((string)($meta['name'] ?? ''));
    if ($label === '') $label = 'Option #' . (int)$optionId;
    $description = trim((string)($meta['description'] ?? ''));
    $value = kp_giftcode_history_option_value($param, $meta);
    $html = '<li class="gc-history-option">';
    $html .= '<span class="gc-history-option-main"><strong>' . kp_e($label)
        . '</strong><b>' . kp_e($value) . '</b></span>';
    if ($description !== '') {
        $html .= '<small>' . kp_e($description) . '</small>';
    }
    return $html . '</li>';
}

/**
 * HTML for the "Vật phẩm" column of giftcode_log, using the existing parser.
 * A missing/unknown item remains visibly marked as an old token, never guessed.
 */
function kp_giftcode_history_items_html($raw, array $itemMap, array $optionMap)
{
    $parsed = kp_giftcode_parse_rewards((string)$raw, $itemMap);
    if (!$parsed['rewards'] && !$parsed['legacy']) {
        return '<span class="gc-history-no-item">Không có dữ liệu vật phẩm</span>';
    }

    $html = '<div class="gc-history-items">';
    foreach ($parsed['rewards'] as $reward) {
        $id = (int)$reward['item_id'];
        $item = $itemMap[$id] ?? [];
        $name = trim((string)($item['name'] ?? ''));
        if ($name === '') $name = 'Vật phẩm #' . $id;
        $image = (string)($item['image'] ?? '');
        $defaults = isset($item['default_options']) && is_array($item['default_options'])
            ? $item['default_options'] : [];
        $custom = isset($reward['options']) && is_array($reward['options'])
            ? $reward['options'] : [];
        $optionCount = count($defaults) + count($custom);

        // Summary stays one short row; detailed metadata/options expand on demand.
        $html .= '<details class="gc-history-item"><summary class="gc-history-item-summary">';
        $html .= '<span class="gc-history-icon">';
        if (preg_match('~\A/res/icon/[0-9]+\.png\z~D', $image)) {
            $html .= '<img src="' . kp_e($image)
                . '" alt="" loading="lazy" decoding="async" width="32" height="32">';
        } else {
            $html .= '<span class="gc-history-icon-missing" aria-hidden="true">✦</span>';
        }
        $html .= '</span><span class="gc-history-item-head"><strong>' . kp_e($name) . '</strong>';
        $html .= '<small>#' . $id;
        if ($optionCount > 0) $html .= ' · ' . $optionCount . ' chỉ số';
        $html .= '</small></span>';
        $html .= '<span class="gc-history-quantity">×'
            . number_format((int)$reward['quantity'], 0, ',', '.') . '</span>';
        $html .= '<span class="gc-history-chevron" aria-hidden="true">⌄</span></summary>';

        $type = trim((string)($item['type_name'] ?? ''));
        $html .= '<div class="gc-history-item-body"><small class="gc-history-item-meta">#' . $id;
        if ($type !== '') $html .= ' · ' . kp_e($type);
        if ((int)$reward['minutes'] > 0) {
            $html .= ' · ' . number_format((int)$reward['minutes'], 0, ',', '.') . ' phút';
        }
        if ((int)$reward['lock'] === 1) $html .= ' · Đã khóa';
        if ((int)$reward['magic'] === 1) $html .= ' · Hệ vật lý';
        $html .= '</small>';

        if ($defaults) {
            $html .= '<div class="gc-history-option-group"><span>Chỉ số gốc từ data_item</span><ul>';
            foreach ($defaults as $opt) {
                $html .= kp_giftcode_history_option_html(
                    (int)($opt['id'] ?? -1), (int)($opt['param'] ?? 0), $optionMap
                );
            }
            $html .= '</ul></div>';
        }
        if ($custom) {
            $html .= '<div class="gc-history-option-group gc-history-option-custom"><span>Option trong token DBO</span><ul>';
            foreach ($custom as $opt) {
                $html .= kp_giftcode_history_option_html(
                    (int)$opt['id'], (int)$opt['param'], $optionMap
                );
            }
            $html .= '</ul></div>';
        }
        if ($optionCount === 0) {
            $html .= '<small class="gc-history-no-item">Không có option riêng trong bản ghi.</small>';
        }
        $html .= '</div></details>';
    }
    foreach ($parsed['legacy'] as $token) {
        $html .= '<details class="gc-history-legacy"><summary>Token cũ / chưa nhận diện</summary><code>'
            . kp_e($token) . '</code></details>';
    }
    return $html . '</div>';
}
