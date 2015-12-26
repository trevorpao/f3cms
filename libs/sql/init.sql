-- phpMyAdmin SQL Dump
-- version 4.2.11
-- http://www.phpmyadmin.net
--
-- 主機: localhost
-- 產生時間： 2015 年 10 月 18 日 12:14
-- 伺服器版本: 5.6.21
-- PHP 版本： 5.5.19

--
-- 資料庫： `f3cms`
--

-- --------------------------------------------------------

--
-- 資料表結構 `tbl_adv`
--

CREATE TABLE IF NOT EXISTS `tbl_adv` (
`id` int(11) NOT NULL,
  `position_id` int(11) NOT NULL,
  `counter` int(11) NOT NULL,
  `status` enum('Enabled','Disabled') NOT NULL DEFAULT 'Disabled',
  `end_date` date NOT NULL,
  `uri` varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
  `title` varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
  `pic` varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
  `background` varchar(255) CHARACTER SET utf8 NOT NULL,
  `summary` text CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
  `last_ts` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_user` int(11) NOT NULL,
  `insert_user` int(11) NOT NULL,
  `insert_ts` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- 資料表結構 `tbl_category`
--

CREATE TABLE IF NOT EXISTS `tbl_category` (
`id` int(11) NOT NULL,
  `status` enum('Enabled','Disabled') NOT NULL DEFAULT 'Disabled',
  `parent_id` int(11) NOT NULL,
  `slug` varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
  `title` varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
  `last_ts` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_user` int(11) NOT NULL,
  `insert_user` int(11) NOT NULL,
  `insert_ts` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- 資料表結構 `tbl_contact`
--

CREATE TABLE IF NOT EXISTS `tbl_contact` (
`id` int(11) NOT NULL,
  `status` enum('New','Process','Done') NOT NULL DEFAULT 'New',
  `name` varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
  `phone` varchar(50) NOT NULL,
  `email` varchar(255) NOT NULL,
  `message` text CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
  `response` text CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
  `last_ts` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_user` int(11) NOT NULL,
  `insert_user` int(11) NOT NULL,
  `insert_ts` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- 資料表結構 `tbl_media`
--

CREATE TABLE IF NOT EXISTS `tbl_media` (
`id` int(11) NOT NULL,
  `status` enum('Disabled','Enabled') DEFAULT 'Disabled',
  `slug` varchar(255) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `pic` varchar(255) NOT NULL,
  `content` varchar(255) DEFAULT NULL,
  `last_ts` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `last_user` int(11) DEFAULT NULL,
  `insert_ts` timestamp NULL DEFAULT NULL,
  `insert_user` int(11) DEFAULT NULL
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- 資料表結構 `tbl_option`
--

CREATE TABLE IF NOT EXISTS `tbl_option` (
`id` int(11) NOT NULL,
  `status` enum('Enabled','Disabled') NOT NULL DEFAULT 'Enabled',
  `name` varchar(255) DEFAULT NULL,
  `content` text,
  `last_ts` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `last_user` int(11) DEFAULT NULL,
  `insert_ts` timestamp NULL DEFAULT NULL,
  `insert_user` int(11) DEFAULT NULL
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- 資料表結構 `tbl_post`
--

CREATE TABLE IF NOT EXISTS `tbl_post` (
`id` int(11) NOT NULL,
  `status` enum('Disabled','Enabled') DEFAULT 'Disabled',
  `type` enum('Ancestor','Draft','Backup') NOT NULL DEFAULT 'Ancestor',
  `slug` varchar(255) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `pic` varchar(255) NOT NULL,
  `content` text,
  `last_ts` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `last_user` int(11) DEFAULT NULL,
  `insert_ts` timestamp NULL DEFAULT NULL,
  `insert_user` int(11) DEFAULT NULL
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- 資料表結構 `tbl_tag`
--

CREATE TABLE IF NOT EXISTS `tbl_tag` (
`id` int(11) NOT NULL,
  `status` enum('Disabled','Enabled') DEFAULT 'Disabled',
  `parent_id` int(11) NOT NULL DEFAULT '0',
  `counter` int(11) DEFAULT NULL,
  `slug` varchar(255) DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `last_ts` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `last_user` int(11) DEFAULT NULL,
  `insert_ts` timestamp NULL DEFAULT NULL,
  `insert_user` int(11) DEFAULT NULL
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- 資料表結構 `tbl_staff`
--

CREATE TABLE IF NOT EXISTS `tbl_staff` (
`id` int(11) NOT NULL,
  `status` enum('New','Verified','Freeze') DEFAULT 'New',
  `account` varchar(45) DEFAULT NULL,
  `pwd` varchar(45) DEFAULT NULL,
  `last_ts` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `last_user` int(11) DEFAULT NULL,
  `insert_ts` timestamp NULL DEFAULT NULL,
  `insert_user` int(11) DEFAULT NULL
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;

--
-- 已匯出資料表的索引
--

--
-- 資料表索引 `tbl_adv`
--
ALTER TABLE `tbl_adv`
 ADD PRIMARY KEY (`id`), ADD KEY `category_id` (`position_id`), ADD KEY `uri` (`uri`);

--
-- 資料表索引 `tbl_category`
--
ALTER TABLE `tbl_category`
 ADD PRIMARY KEY (`id`), ADD UNIQUE KEY `uri` (`slug`), ADD KEY `parent_id` (`parent_id`);

--
-- 資料表索引 `tbl_contact`
--
ALTER TABLE `tbl_contact`
 ADD PRIMARY KEY (`id`);

--
-- 資料表索引 `tbl_media`
--
ALTER TABLE `tbl_media`
 ADD PRIMARY KEY (`id`);

--
-- 資料表索引 `tbl_option`
--
ALTER TABLE `tbl_option`
 ADD PRIMARY KEY (`id`);

--
-- 資料表索引 `tbl_post`
--
ALTER TABLE `tbl_post`
 ADD PRIMARY KEY (`id`);

--
-- 資料表索引 `tbl_tag`
--
ALTER TABLE `tbl_tag`
 ADD PRIMARY KEY (`id`);

--
-- 資料表索引 `tbl_staff`
--
ALTER TABLE `tbl_staff`
 ADD PRIMARY KEY (`id`);

--
-- 在匯出的資料表使用 AUTO_INCREMENT
--

--
-- 使用資料表 AUTO_INCREMENT `tbl_adv`
--
ALTER TABLE `tbl_adv`
MODIFY `id` int(11) NOT NULL AUTO_INCREMENT,AUTO_INCREMENT=1;
--
-- 使用資料表 AUTO_INCREMENT `tbl_category`
--
ALTER TABLE `tbl_category`
MODIFY `id` int(11) NOT NULL AUTO_INCREMENT,AUTO_INCREMENT=1;
--
-- 使用資料表 AUTO_INCREMENT `tbl_contact`
--
ALTER TABLE `tbl_contact`
MODIFY `id` int(11) NOT NULL AUTO_INCREMENT,AUTO_INCREMENT=1;
--
-- 使用資料表 AUTO_INCREMENT `tbl_media`
--
ALTER TABLE `tbl_media`
MODIFY `id` int(11) NOT NULL AUTO_INCREMENT,AUTO_INCREMENT=1;
--
-- 使用資料表 AUTO_INCREMENT `tbl_option`
--
ALTER TABLE `tbl_option`
MODIFY `id` int(11) NOT NULL AUTO_INCREMENT,AUTO_INCREMENT=1;
--
-- 使用資料表 AUTO_INCREMENT `tbl_post`
--
ALTER TABLE `tbl_post`
MODIFY `id` int(11) NOT NULL AUTO_INCREMENT,AUTO_INCREMENT=1;
--
-- 使用資料表 AUTO_INCREMENT `tbl_tag`
--
ALTER TABLE `tbl_tag`
MODIFY `id` int(11) NOT NULL AUTO_INCREMENT,AUTO_INCREMENT=1;
--
-- 使用資料表 AUTO_INCREMENT `tbl_staff`
--
ALTER TABLE `tbl_staff`
MODIFY `id` int(11) NOT NULL AUTO_INCREMENT,AUTO_INCREMENT=1;

INSERT INTO `tbl_staff` (`id`, `status`, `account`, `pwd`, `last_ts`, `last_user`, `insert_ts`, `insert_user`) VALUES
(1, 'Verified', 'admin', '6fb42da0e32e07b61c9f0251fe627a9c', '2015-08-04 19:13:01', 1, '2015-08-05 00:41:20', 1);
