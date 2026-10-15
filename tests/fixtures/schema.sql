-- Schema only, extracted from supplied kpah.sql. No player rows or credentials.

SET SESSION sql_mode = "NO_AUTO_VALUE_ON_ZERO";

CREATE TABLE `team_user` (
  `id` int(11) NOT NULL,
  `userid` int(10) UNSIGNED NOT NULL,
  `provider` int(10) UNSIGNED NOT NULL,
  `agent` int(11) NOT NULL DEFAULT -1,
  `newagent` varchar(45) NOT NULL DEFAULT '-1',
  `newprovider` varchar(45) NOT NULL DEFAULT '-1',
  `username` varchar(20) DEFAULT NULL,
  `password` varchar(100) DEFAULT NULL,
  `phone` varchar(20) NOT NULL DEFAULT '',
  `regdate` datetime DEFAULT NULL,
  `ban` tinyint(1) NOT NULL DEFAULT 0,
  `fromgame` tinyint(4) NOT NULL DEFAULT 0,
  `email` varchar(100) DEFAULT NULL,
  `vnd` int(11) NOT NULL DEFAULT 0,
  `tongnap` int(11) NOT NULL DEFAULT 0
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci ROW_FORMAT=DYNAMIC;

ALTER TABLE `team_user`
  ADD PRIMARY KEY (`id`) USING BTREE,
  ADD UNIQUE KEY `Index_2` (`userid`) USING BTREE,
  ADD UNIQUE KEY `uk_team_user_username` (`username`) USING BTREE,
  ADD KEY `Index_3` (`provider`) USING BTREE,
  ADD KEY `Index_4` (`agent`) USING BTREE,
  ADD KEY `newagent` (`newagent`) USING BTREE,
  ADD KEY `newprovider` (`newprovider`) USING BTREE;

ALTER TABLE `team_user`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=117;

CREATE TABLE `tob_char` (
  `id` int(11) NOT NULL,
  `charname` varchar(20) NOT NULL,
  `userId` int(11) NOT NULL,
  `xp` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `lastLv` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `hp` int(11) NOT NULL DEFAULT 0,
  `mp` int(11) NOT NULL DEFAULT 0,
  `map` int(11) NOT NULL DEFAULT -1,
  `x` int(11) NOT NULL DEFAULT 0,
  `y` int(11) NOT NULL DEFAULT 0,
  `gold` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `headStyle` int(11) NOT NULL DEFAULT 0,
  `class` int(11) NOT NULL DEFAULT 0,
  `nHP1` int(11) NOT NULL DEFAULT 0,
  `nHP2` int(11) NOT NULL DEFAULT 0,
  `nHP3` int(11) NOT NULL DEFAULT 0,
  `nMP1` int(11) NOT NULL DEFAULT 0,
  `nMP2` int(11) NOT NULL DEFAULT 0,
  `nMP3` int(11) NOT NULL DEFAULT 0,
  `nAr1` int(11) NOT NULL DEFAULT 0,
  `nAr2` int(11) NOT NULL DEFAULT 0,
  `nAr3` int(11) NOT NULL DEFAULT 0,
  `skill1` int(11) NOT NULL DEFAULT 0,
  `skill2` int(11) NOT NULL DEFAULT 0,
  `skill3` int(11) NOT NULL DEFAULT 0,
  `skill4` int(11) NOT NULL DEFAULT 0,
  `skill5` int(11) NOT NULL DEFAULT 0,
  `strength` int(11) NOT NULL DEFAULT 0,
  `agitity` int(11) NOT NULL DEFAULT 0,
  `spirit` int(11) NOT NULL DEFAULT 0,
  `dexterity` int(11) NOT NULL DEFAULT 0,
  `luck` int(11) NOT NULL DEFAULT 0,
  `basepoint` int(11) NOT NULL DEFAULT 3,
  `skillpoint` int(11) NOT NULL DEFAULT 0,
  `itemq1` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `itemq2` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `itemq3` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `itemq4` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `skill6` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `skill7` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `skill8` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `skill9` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `skill10` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `skill11` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `skill12` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `skill13` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `skill14` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `skill15` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `killer` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `khan1` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `khan2` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `khan3` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `khan4` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `khan5` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `book` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `timeKiller` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `nPKill` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `gender` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `ticket` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `totalTimePlay` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `fullGoldTime` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `halfGoldTime` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `luong` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `isAdmin` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `nHP4` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `nHP5` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `nMP4` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `nMP5` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `potion` varchar(2000) DEFAULT NULL,
  `skill` varchar(2000) DEFAULT NULL,
  `basic` varchar(2000) DEFAULT NULL,
  `pInfo` varchar(2000) DEFAULT NULL,
  `del` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `tDelChar` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `blacklist` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `nhack` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `newiddb` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `newuserid` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `ban` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `idClan` int(11) NOT NULL DEFAULT -1,
  `reupclass` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `lastLog` datetime NOT NULL DEFAULT '2010-01-01 00:00:00',
  `infotop` varchar(2000) NOT NULL DEFAULT '-1',
  `itemquest` varchar(200) DEFAULT NULL,
  `allitem` text DEFAULT NULL,
  `equip` text DEFAULT NULL,
  `inven` text DEFAULT NULL,
  `bag` text DEFAULT NULL,
  `killme` varchar(600) DEFAULT NULL,
  `luonglock` int(10) UNSIGNED DEFAULT 0,
  `idserver` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `tuido` text DEFAULT NULL,
  `tichluy` int(11) DEFAULT 0,
  `tichluy_bosung` int(11) NOT NULL DEFAULT 0,
  `tichluy_tuan` int(11) DEFAULT 0,
  `topNap` int(11) NOT NULL DEFAULT 0,
  `qua_npc` int(11) DEFAULT 0,
  `isPP` int(11) DEFAULT 0,
  `isMN` int(11) NOT NULL DEFAULT 0,
  `diemluong` int(11) DEFAULT 0
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci ROW_FORMAT=DYNAMIC;

ALTER TABLE `tob_char`
  ADD PRIMARY KEY (`id`) USING BTREE,
  ADD UNIQUE KEY `charname` (`charname`) USING BTREE,
  ADD KEY `Index_3` (`userId`) USING BTREE,
  ADD KEY `Index_6` (`idClan`) USING BTREE,
  ADD KEY `Index_5` (`pInfo`(333)) USING BTREE,
  ADD KEY `Index_7` (`gold`) USING BTREE,
  ADD KEY `Index_8` (`xp`) USING BTREE,
  ADD KEY `Index_9` (`ticket`) USING BTREE,
  ADD KEY `Index_10` (`basepoint`) USING BTREE;

ALTER TABLE `tob_char`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=87;

CREATE TABLE `tob_web_info_char` (
  `charname` varchar(45) NOT NULL,
  `infoWearing` varchar(150) NOT NULL,
  `otherInfo` varchar(300) NOT NULL,
  `userid` int(10) UNSIGNED NOT NULL,
  `lv` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `acountname` varchar(45) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci ROW_FORMAT=DYNAMIC;

ALTER TABLE `tob_web_info_char`
  ADD PRIMARY KEY (`charname`) USING BTREE,
  ADD KEY `userid` (`userid`) USING BTREE,
  ADD KEY `lv` (`lv`) USING BTREE,
  ADD KEY `acountname` (`acountname`) USING BTREE;

CREATE TABLE `tob_char_luong` (
  `id` int(10) UNSIGNED NOT NULL,
  `charname` varchar(45) NOT NULL,
  `country` tinyint(4) NOT NULL DEFAULT -1,
  `exp` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `idclan` int(11) NOT NULL DEFAULT -1,
  `honor` int(10) UNSIGNED NOT NULL,
  `lv` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `nPK` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `wearing` varchar(500) NOT NULL DEFAULT '""',
  `luong` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `gold` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `headStyle` int(10) UNSIGNED NOT NULL DEFAULT 0
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci ROW_FORMAT=DYNAMIC;

ALTER TABLE `tob_char_luong`
  ADD PRIMARY KEY (`id`) USING BTREE,
  ADD UNIQUE KEY `charname` (`charname`) USING BTREE,
  ADD KEY `country` (`country`) USING BTREE,
  ADD KEY `exp` (`exp`) USING BTREE,
  ADD KEY `Index_5` (`honor`) USING BTREE,
  ADD KEY `Index_6` (`nPK`) USING BTREE;

ALTER TABLE `tob_char_luong`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=83;

CREATE TABLE `tob_clan` (
  `id_clan` int(10) UNSIGNED NOT NULL,
  `clanName` varchar(45) DEFAULT NULL,
  `charMaster` varchar(45) DEFAULT NULL,
  `lvClan` int(10) UNSIGNED DEFAULT NULL,
  `member` varchar(8000) DEFAULT NULL,
  `money` bigint(20) UNSIGNED DEFAULT NULL,
  `xp` bigint(20) UNSIGNED DEFAULT NULL,
  `datecreate` datetime NOT NULL,
  `slogan` varchar(300) NOT NULL,
  `isdel` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `timedel` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `town` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `tax` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `devote` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `otherinfo` varchar(800) NOT NULL DEFAULT '""',
  `skillclan` varchar(800) NOT NULL DEFAULT '""',
  `country` int(11) NOT NULL DEFAULT -1,
  `tcheckdel` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `id_cu` int(10) UNSIGNED NOT NULL DEFAULT 0
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci ROW_FORMAT=DYNAMIC;

ALTER TABLE `tob_clan`
  ADD PRIMARY KEY (`id_clan`) USING BTREE,
  ADD KEY `Index_2` (`lvClan`) USING BTREE,
  ADD KEY `Index_3` (`clanName`) USING BTREE,
  ADD KEY `Index_4` (`charMaster`) USING BTREE,
  ADD KEY `Index_5` (`money`) USING BTREE,
  ADD KEY `Index_6` (`xp`) USING BTREE;

CREATE TABLE `tob_event_top` (
  `id` int(10) UNSIGNED NOT NULL,
  `charname` varchar(100) NOT NULL,
  `luongnap` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `toppoint` bigint(20) UNSIGNED NOT NULL DEFAULT 0
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci ROW_FORMAT=DYNAMIC;

ALTER TABLE `tob_event_top`
  ADD PRIMARY KEY (`id`) USING BTREE,
  ADD KEY `Index_2` (`charname`) USING BTREE;

ALTER TABLE `tob_event_top`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

CREATE TABLE `tob_top_event` (
  `id` int(10) UNSIGNED NOT NULL,
  `charname` varchar(50) NOT NULL,
  `point1` int(11) NOT NULL DEFAULT 0,
  `timeupdate` datetime DEFAULT current_timestamp(),
  `xu` bigint(20) DEFAULT 0,
  `luong` int(11) DEFAULT 0,
  `datexu` datetime DEFAULT current_timestamp(),
  `dateluong` datetime DEFAULT current_timestamp(),
  `crtime` bigint(20) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;

ALTER TABLE `tob_top_event`
  ADD PRIMARY KEY (`id`) USING BTREE,
  ADD UNIQUE KEY `charname` (`charname`) USING BTREE;

ALTER TABLE `tob_top_event`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2803;

CREATE TABLE `tob_char_thach_dau` (
  `id` int(10) UNSIGNED NOT NULL,
  `charname` varchar(45) NOT NULL,
  `point` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `lv` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `timeupdate` datetime NOT NULL,
  `timeReg` datetime NOT NULL,
  `nhom` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `pointrank` int(10) UNSIGNED NOT NULL DEFAULT 0
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci ROW_FORMAT=DYNAMIC;

ALTER TABLE `tob_char_thach_dau`
  ADD PRIMARY KEY (`id`) USING BTREE,
  ADD UNIQUE KEY `Index_2` (`charname`) USING BTREE,
  ADD KEY `Index_3` (`point`) USING BTREE;

CREATE TABLE `tob_chiemthanh` (
  `clan` varchar(255) NOT NULL,
  `bangchu` varchar(255) NOT NULL,
  `win` int(11) NOT NULL DEFAULT 0,
  `xu` varchar(255) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;

ALTER TABLE `tob_chiemthanh`
  ADD PRIMARY KEY (`clan`,`bangchu`) USING BTREE;

CREATE TABLE `tob_top_event_wc` (
  `id` int(10) UNSIGNED NOT NULL,
  `charname` varchar(200) NOT NULL,
  `point1` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `timeupdate` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `xu` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `luong` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `datexu` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `dateluong` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `crtime` bigint(20) UNSIGNED DEFAULT 0
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci ROW_FORMAT=DYNAMIC;

ALTER TABLE `tob_top_event_wc`
  ADD PRIMARY KEY (`id`) USING BTREE,
  ADD UNIQUE KEY `Index_2` (`charname`) USING BTREE;

ALTER TABLE `tob_top_event_wc`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

CREATE TABLE `board_naptien` (
  `id` int(10) UNSIGNED NOT NULL,
  `xu` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `luong` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `svID` int(11) NOT NULL DEFAULT -1,
  `username` varchar(45) NOT NULL,
  `ve` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `luongKhoa` int(11) NOT NULL DEFAULT 0
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci ROW_FORMAT=DYNAMIC;

ALTER TABLE `board_naptien`
  ADD PRIMARY KEY (`id`) USING BTREE,
  ADD KEY `Index_2` (`username`) USING BTREE,
  ADD KEY `Index_3` (`svID`) USING BTREE;

ALTER TABLE `board_naptien`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

CREATE TABLE `board_created` (
  `id` int(10) UNSIGNED NOT NULL,
  `xu` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `luong` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `luongK` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `svID` int(11) NOT NULL DEFAULT -1,
  `username` varchar(45) NOT NULL,
  `ve` int(10) UNSIGNED NOT NULL DEFAULT 0
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci ROW_FORMAT=DYNAMIC;

ALTER TABLE `board_created`
  ADD PRIMARY KEY (`id`) USING BTREE,
  ADD KEY `Index_2` (`username`) USING BTREE,
  ADD KEY `Index_3` (`svID`) USING BTREE;

ALTER TABLE `board_created`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;
