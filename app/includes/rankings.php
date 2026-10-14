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
        'spending' => [
            'label'=>'Tiêu Lượng','short'=>'Tiêu Lượng','metric'=>'Lượng đã tiêu','secondary'=>'Lượng đã nạp',
            'description'=>'Nhân vật sử dụng nhiều Lượng nhất theo tổng hợp nạp và tiêu.',
            'sql'=>"SELECT charname AS name,luongxai AS score,luongnap AS secondary FROM tob_nap_xai_luong WHERE charname <> '' AND luongxai > 0 ORDER BY luongxai DESC,luongnap DESC,charname ASC LIMIT 10"
        ],
        'vip' => [
            'label'=>'Cấp VIP','short'=>'VIP','metric'=>'Cấp VIP','secondary'=>null,
            'description'=>'Những nhân vật đạt bậc VIP cao nhất.',
            'sql'=>"SELECT charname AS name,vip AS score,0 AS secondary FROM tob_char_vip WHERE charname <> '' AND vip > 0 ORDER BY vip DESC,id ASC LIMIT 10"
        ],
        'battle' => [
            'label'=>'Chiến trường','short'=>'Chiến trường','metric'=>'Điểm liên trảm','secondary'=>'Số trận',
            'description'=>'Thành tích liên trảm trên chiến trường.',
            'sql'=>"SELECT charname AS name,lientram AS score,sttru AS secondary FROM tob_chien_truong WHERE charname <> '' AND lientram > 0 ORDER BY lientram DESC,sttru DESC,id ASC LIMIT 10"
        ],
        'farm' => [
            'label'=>'Nông trại','short'=>'Nông trại','metric'=>'Hạng nông trại','secondary'=>'Cấp cây',
            'description'=>'Những nông trại có thứ hạng cao nhất (hạng càng nhỏ càng cao).',
            'sql'=>"SELECT c.charname AS name,f.rank AS score,f.lv AS secondary FROM tob_farm f INNER JOIN tob_char c ON c.id=f.owner WHERE f.rank>0 AND c.del=1 AND c.ban=0 AND c.charname <> '' ORDER BY f.rank ASC,f.lv DESC,f.id ASC LIMIT 10"
        ],
        'clanxp' => [
            'label'=>'EXP bang hội','short'=>'EXP bang','metric'=>'EXP bang','secondary'=>'Cấp bang',
            'description'=>'Các bang hội tích lũy nhiều kinh nghiệm nhất.',
            'sql'=>"SELECT clanName AS name,COALESCE(xp,0) AS score,COALESCE(lvClan,0) AS secondary FROM tob_clan WHERE isdel=0 AND clanName IS NOT NULL AND clanName<>'' AND xp>0 ORDER BY xp DESC,lvClan DESC,id_clan ASC LIMIT 10"
        ],
        'contribution' => [
            'label'=>'Tích lũy','short'=>'Tích lũy','metric'=>'Điểm tích lũy','secondary'=>'Cấp',
            'description'=>'Những nhân vật có điểm tích lũy cao nhất.',
            'sql'=>"SELECT charname AS name,tichluy AS score,lastLv AS secondary FROM tob_char WHERE charname<>'' AND del=1 AND ban=0 AND tichluy>0 ORDER BY tichluy DESC,lastLv DESC,id ASC LIMIT 10"
        ],
        'kills' => [
            'label'=>'Hạ gục','short'=>'Hạ gục','metric'=>'Lượt hạ gục','secondary'=>'Điểm PK',
            'description'=>'Những nhân vật ghi nhận nhiều lượt hạ gục nhất.',
            'sql'=>"SELECT charname AS name,killer AS score,nPKill AS secondary FROM tob_char WHERE charname<>'' AND del=1 AND ban=0 AND killer>0 ORDER BY killer DESC,nPKill DESC,id ASC LIMIT 10"
        ],
        'toppoints' => [
            'label'=>'Điểm Top nạp','short'=>'Điểm nạp','metric'=>'Điểm Top','secondary'=>'Cấp',
            'description'=>'Điểm tích lũy Top nạp được game ghi nhận cho nhân vật.',
            'sql'=>"SELECT charname AS name,topNap AS score,lastLv AS secondary FROM tob_char WHERE charname<>'' AND del=1 AND ban=0 AND topNap>0 ORDER BY topNap DESC,lastLv DESC,id ASC LIMIT 10"
        ],
        'clanwealth' => [
            'label'=>'Quỹ bang hội','short'=>'Quỹ bang','metric'=>'Xu bang','secondary'=>'Cấp bang',
            'description'=>'Những bang hội có quỹ lớn nhất.',
            'sql'=>"SELECT clanName AS name,COALESCE(money,0) AS score,COALESCE(lvClan,0) AS secondary FROM tob_clan WHERE isdel=0 AND clanName IS NOT NULL AND clanName<>'' AND money>0 ORDER BY money DESC,lvClan DESC,id_clan ASC LIMIT 10"
        ],
        'strength' => [
            'label'=>'Sức mạnh','short'=>'Sức mạnh','metric'=>'Sức mạnh','secondary'=>'Cấp',
            'description'=>'Những nhân vật có chỉ số sức mạnh cao nhất.',
            'sql'=>"SELECT charname AS name,strength AS score,lastLv AS secondary FROM tob_char WHERE charname<>'' AND del=1 AND ban=0 AND strength>0 ORDER BY strength DESC,lastLv DESC,id ASC LIMIT 10"
        ],
        'playtime' => [
            'label'=>'Thời gian chơi','short'=>'Giờ chơi','metric'=>'Phút chơi','secondary'=>'Cấp',
            'description'=>'Xếp hạng theo tổng thời gian chơi được game ghi nhận.',
            'sql'=>"SELECT charname AS name,FLOOR(totalTimePlay/60) AS score,lastLv AS secondary FROM tob_char WHERE charname<>'' AND del=1 AND ban=0 AND totalTimePlay>0 ORDER BY totalTimePlay DESC,lastLv DESC,id ASC LIMIT 10"
        ],
        'supply' => [
            'label'=>'Vận tiêu','short'=>'Vận tiêu','metric'=>'Cấp tiêu','secondary'=>null,
            'description'=>'Nhân vật có cấp tiêu đang nhận cao nhất; không phải xếp hạng lượt vận tiêu tích lũy.',
            'sql'=>"SELECT namechar AS name,lv AS score,0 AS secondary FROM tob_vantieu WHERE namechar<>'' AND lv>0 ORDER BY lv DESC,idchar ASC LIMIT 10"
        ],
        'arena' => [
            'label'=>'Điểm đấu hạng','short'=>'Đấu hạng','metric'=>'Điểm hạng','secondary'=>'Điểm đấu',
            'description'=>'Bảng điểm xếp hạng thách đấu.',
            'sql'=>"SELECT charname AS name,pointrank AS score,point AS secondary FROM tob_char_thach_dau WHERE charname<>'' AND pointrank>0 ORDER BY pointrank DESC,point DESC,id ASC LIMIT 10"
        ],
        'level' => [
            'label'=>'Cấp độ','short'=>'Cấp độ','metric'=>'Cấp','secondary'=>'EXP',
            'description'=>'Những nhân vật có cấp độ cao nhất; cùng cấp thì so kinh nghiệm.',
            'source'=>'tob_char_luong.lv / exp',
            'sql'=>"SELECT charname AS name,lv AS score,exp AS secondary
                    FROM tob_char_luong WHERE charname <> ''
                    ORDER BY lv DESC,exp DESC,id ASC LIMIT 10"
        ],
        'honor' => [
            'label'=>'Danh vọng','short'=>'Danh vọng','metric'=>'Danh vọng','secondary'=>'Cấp',
            'description'=>'Những nhân vật có danh vọng cao nhất.',
            'source'=>'tob_char_luong.honor',
            'sql'=>"SELECT charname AS name,honor AS score,lv AS secondary
                    FROM tob_char_luong WHERE charname <> '' AND honor > 0
                    ORDER BY honor DESC,lv DESC,id ASC LIMIT 10"
        ],
        'pk' => [
            'label'=>'Điểm PK','short'=>'PK','metric'=>'Điểm PK','secondary'=>'Danh vọng',
            'description'=>'Những nhân vật dẫn đầu về điểm PK.',
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
            'description'=>'Những người chơi tích lũy nhiều điểm sự kiện nhất.',
            'source'=>'tob_top_event.point1',
            'sql'=>"SELECT charname AS name,point1 AS score,luong AS secondary
                    FROM tob_top_event WHERE charname <> '' AND point1 > 0
                    ORDER BY point1 DESC,luong DESC,id ASC LIMIT 10"
        ],
        'duel' => [
            'label'=>'Thách đấu','short'=>'Thách đấu','metric'=>'Điểm thách đấu','secondary'=>'Cấp',
            'description'=>'Những đối thủ đang dẫn đầu về điểm thách đấu.',
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
            'description'=>'Thành tích của người chơi trong sự kiện WC.',
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
