<?php
/**
 * Read-only KPAH leaderboards. Each query is static and whitelisted.
 * Never interpolate ?type or user input into SQL identifiers/ORDER BY.
 *
 * Labels are descriptive, not claims about server-side reward rules.
 */
if (!defined('KP_ROOT')) { http_response_code(403); exit; }

function kp_rankings_catalog()
{
    return [
        'level' => [
            'label'=>'Cấp độ','short'=>'Cấp độ','metric'=>'Cấp','secondary'=>'EXP',
            'description'=>'Cấp nhân vật và kinh nghiệm trong bảng nhân vật hiện có.',
            'source'=>'tob_char_luong.lv / exp',
            'sql'=>"SELECT charname AS name,lv AS score,exp AS secondary
                    FROM tob_char_luong WHERE charname <> ''
                    ORDER BY lv DESC,exp DESC,id ASC LIMIT 10"
        ],
        'honor' => [
            'label'=>'Danh vọng','short'=>'Danh vọng','metric'=>'Danh vọng','secondary'=>'Cấp',
            'description'=>'Xếp theo chỉ số honor của nhân vật.',
            'source'=>'tob_char_luong.honor',
            'sql'=>"SELECT charname AS name,honor AS score,lv AS secondary
                    FROM tob_char_luong WHERE charname <> '' AND honor > 0
                    ORDER BY honor DESC,lv DESC,id ASC LIMIT 10"
        ],
        'pk' => [
            'label'=>'Điểm PK','short'=>'PK','metric'=>'Điểm PK','secondary'=>'Danh vọng',
            'description'=>'Xếp theo nPK được lưu trong bảng nhân vật, không quy đổi thành số trận thắng.',
            'source'=>'tob_char_luong.nPK',
            'sql'=>"SELECT charname AS name,nPK AS score,honor AS secondary
                    FROM tob_char_luong WHERE charname <> '' AND nPK > 0
                    ORDER BY nPK DESC,honor DESC,id ASC LIMIT 10"
        ],
        'clan' => [
            'label'=>'Bang hội','short'=>'Bang hội','metric'=>'Cấp bang','secondary'=>'EXP bang',
            'description'=>'Xếp bang hội đang hoạt động theo cấp bang và kinh nghiệm bang.',
            'source'=>'tob_clan.lvClan / xp',
            'sql'=>"SELECT clanName AS name,COALESCE(lvClan,0) AS score,COALESCE(xp,0) AS secondary
                    FROM tob_clan WHERE isdel=0 AND clanName IS NOT NULL AND clanName <> ''
                    ORDER BY COALESCE(lvClan,0) DESC,COALESCE(xp,0) DESC,id_clan ASC LIMIT 10"
        ],
        'nap' => [
            'label'=>'Nạp Lượng','short'=>'Nạp Lượng','metric'=>'Lượng đã nạp','secondary'=>'Điểm Top',
            'description'=>'Thống kê Lượng đã nạp của nhân vật, không bao gồm dữ liệu giao dịch riêng tư.',
            'source'=>'tob_event_top.luongnap',
            'sql'=>"SELECT charname AS name,luongnap AS score,toppoint AS secondary
                    FROM tob_event_top WHERE charname <> '' AND luongnap > 0
                    ORDER BY luongnap DESC,toppoint DESC,id ASC LIMIT 10"
        ],
        'event' => [
            'label'=>'Điểm sự kiện','short'=>'Sự kiện','metric'=>'Điểm sự kiện','secondary'=>'Lượng sự kiện',
            'description'=>'Điểm sự kiện theo cột point1 của bảng tob_top_event.',
            'source'=>'tob_top_event.point1',
            'sql'=>"SELECT charname AS name,point1 AS score,luong AS secondary
                    FROM tob_top_event WHERE charname <> '' AND point1 > 0
                    ORDER BY point1 DESC,luong DESC,id ASC LIMIT 10"
        ],
        'duel' => [
            'label'=>'Thách đấu','short'=>'Thách đấu','metric'=>'Điểm thách đấu','secondary'=>'Cấp',
            'description'=>'Xếp theo point của bảng thách đấu hiện có.',
            'source'=>'tob_char_thach_dau.point',
            'sql'=>"SELECT charname AS name,point AS score,lv AS secondary
                    FROM tob_char_thach_dau WHERE charname <> '' AND point > 0
                    ORDER BY point DESC,lv DESC,id ASC LIMIT 10"
        ],
        'siege' => [
            'label'=>'Chiếm thành','short'=>'Chiếm thành','metric'=>'Lần chiến thắng','secondary'=>null,
            'description'=>'Các bang hội có số lần thắng chiếm thành cao nhất.',
            'source'=>'tob_chiemthanh.win',
            'sql'=>"SELECT clan AS name,win AS score,0 AS secondary
                    FROM tob_chiemthanh WHERE clan <> '' AND win > 0
                    ORDER BY win DESC,clan ASC LIMIT 10"
        ],
        'wc' => [
            'label'=>'Sự kiện WC','short'=>'WC','metric'=>'Điểm sự kiện','secondary'=>'Lượng sự kiện',
            'description'=>'Điểm point1 của bảng sự kiện WC riêng; không gộp với bảng sự kiện thường.',
            'source'=>'tob_top_event_wc.point1',
            'sql'=>"SELECT charname AS name,point1 AS score,luong AS secondary
                    FROM tob_top_event_wc WHERE charname <> '' AND point1 > 0
                    ORDER BY point1 DESC,luong DESC,id ASC LIMIT 10"
        ],
    ];
}

function kp_rankings_fetch($type)
{
    $catalog=kp_rankings_catalog();
    if (!isset($catalog[$type])) throw new InvalidArgumentException('Loại bảng xếp hạng không hợp lệ.');
    $st=kp_db()->query($catalog[$type]['sql']);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}
