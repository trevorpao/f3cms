--
-- 資料表結構 `tbl_asset`
--

CREATE TABLE `tbl_asset` (
  `id` int(11) NOT NULL,
  `status` enum('Disabled','Enabled') DEFAULT 'Disabled',
  `slug` varchar(255) NOT NULL,
  `cover` varchar(255) NOT NULL,
  `last_ts` timestamp NULL DEFAULT current_timestamp(),
  `last_user` int(11) DEFAULT NULL,
  `insert_ts` timestamp NULL DEFAULT NULL,
  `insert_user` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

ALTER TABLE `tbl_asset`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `tbl_asset`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- 資料表結構 `tbl_asset_meta`
--

CREATE TABLE `tbl_asset_meta` (
  `id` int(11) NOT NULL,
  `parent_id` int(11) NOT NULL,
  `last_ts` timestamp NULL DEFAULT current_timestamp(),
  `k` varchar(50) DEFAULT NULL,
  `v` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

ALTER TABLE `tbl_asset_meta`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_meta_press_idx` (`parent_id`);

ALTER TABLE `tbl_asset_meta`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- 資料表結構 `tbl_asset_tag`
--

CREATE TABLE `tbl_asset_tag` (
  `asset_id` int(11) NOT NULL,
  `tag_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- 已匯出資料表的索引
--

--
-- 資料表索引 `tbl_asset_tag`
--
ALTER TABLE `tbl_asset_tag`
  ADD PRIMARY KEY (`asset_id`,`tag_id`);
