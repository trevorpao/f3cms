--
-- 資料表結構 `tbl_message`
--

CREATE TABLE `tbl_message` (
  `id` int(11) NOT NULL,
  `status` enum('Disabled','Enabled') DEFAULT 'Disabled',
  `slug` varchar(255) NOT NULL,
  `cover` varchar(255) NOT NULL,
  `last_ts` timestamp NULL DEFAULT current_timestamp(),
  `last_user` int(11) DEFAULT NULL,
  `insert_ts` timestamp NULL DEFAULT NULL,
  `insert_user` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

ALTER TABLE `tbl_message`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `tbl_message`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- 資料表結構 `tbl_message_meta`
--

CREATE TABLE `tbl_message_meta` (
  `id` int(11) NOT NULL,
  `parent_id` int(11) NOT NULL,
  `last_ts` timestamp NULL DEFAULT current_timestamp(),
  `k` varchar(50) DEFAULT NULL,
  `v` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

ALTER TABLE `tbl_message_meta`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_meta_press_idx` (`parent_id`);

ALTER TABLE `tbl_message_meta`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;


--
-- 資料表結構 `tbl_message_tag`
--

CREATE TABLE `tbl_message_tag` (
  `message_id` int(11) NOT NULL,
  `tag_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- 已匯出資料表的索引
--

--
-- 資料表索引 `tbl_message_tag`
--
ALTER TABLE `tbl_message_tag`
  ADD PRIMARY KEY (`message_id`,`tag_id`);
