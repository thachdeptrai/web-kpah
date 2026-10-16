<?php
/**
 * Read-only character inventory for logged-in account, derived from the Java
 * Item.getInfoSave format: class,templateId,...|attributes; items separated by >.
 * data_item.xstart is the server's ItemTemplates.idIcon.
 */
if (!defined('KP_ROOT')) { http_response_code(403); exit; }

function kp_inventory_parse($raw, $limit = 130)
{
    if (!is_string($raw) || $raw === '' || strlen($raw) > 500000) return [];
    $result = [];
    foreach (explode('>', ltrim($raw, '>')) as $serialized) {
        if (count($result) >= $limit) break;
        if ($serialized === '' || strlen($serialized) > 8192) continue;
        $parts = explode('|', $serialized, 2);
        $fields = explode(',', $parts[0]);
        if (count($fields) < 7) continue;
        $clazz = trim($fields[0]); $template = trim($fields[1]);
        if (!preg_match('/^\d{1,2}$/D', $clazz) || (int)$clazz > 5 ||
            !preg_match('/^\d{1,8}$/D', $template)) continue;
        $attributes = [];
        if (isset($parts[1])) {
            foreach (array_slice(explode(',', $parts[1]), 0, 33) as $i => $value) {
                $value = trim($value);
                if (preg_match('/^-?\d{1,10}$/D', $value) && (int)$value !== 0) {
                    $attributes[$i] = (int)$value;
                }
            }
        }
        $result[] = [
            'class' => (int)$clazz,
            'template_id' => (int)$template,
            'plus' => isset($fields[5]) && preg_match('/^-?\d{1,6}$/D', trim($fields[5])) ? (int)$fields[5] : 0,
            'level' => isset($fields[6]) && preg_match('/^\d{1,6}$/D', trim($fields[6])) ? (int)$fields[6] : 0,
            'durability' => isset($fields[3]) && preg_match('/^\d{1,10}$/D', trim($fields[3])) ? (int)$fields[3] : null,
            'max_durability' => isset($fields[4]) && preg_match('/^\d{1,10}$/D', trim($fields[4])) ? (int)$fields[4] : null,
            'attributes' => $attributes,
        ];
    }
    return $result;
}

function kp_inventory_character($pdo, $accountId, $name)
{
    if (!is_string($name) || $name === '' || strlen($name) > 80) return null;
    // Explicit ownership: tob_web_info_char.userid == team_user.id.
    // Never select inventories solely by submitted charname.
    $stmt = $pdo->prepare('SELECT c.charname, c.lastLv, c.xp, c.gold, c.luong, c.luonglock,
              c.hp, c.mp, c.strength, c.agitity, c.spirit, c.dexterity, c.luck,
              c.equip, c.inven, c.bag, c.tuido, c.lastLog
          FROM tob_web_info_char AS w
          INNER JOIN tob_char AS c ON c.charname = w.charname AND c.userId = w.userid
          WHERE w.userid = ? AND w.charname = ? LIMIT 1');
    $stmt->execute([(int)$accountId, $name]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function kp_inventory_templates($pdo, $groups)
{
    $ids = [];
    foreach ($groups as $group) foreach ($group as $item) $ids[$item['template_id']] = true;
    $ids = array_slice(array_keys($ids), 0, 600);
    if (!$ids) return [];
    $stmt = $pdo->prepare('SELECT id, name, type, level, xstart AS icon_id, colortype
                           FROM data_item WHERE id IN ('.implode(',', array_fill(0, count($ids), '?')).')');
    $stmt->execute($ids);
    $result = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) $result[(int)$row['id']] = $row;
    return $result;
}

function kp_inventory_icon_url($template)
{
    if (!is_array($template)) return '';
    $id = filter_var($template['icon_id'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>0,'max_range'=>9999999]]);
    if ($id === false) return '';
    // A missing copy renders a CSS placeholder; the endpoint is allowlisted by Apache.
    return '/res/icon/'.$id.'.png';
}
