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
-- 資料表結構 `tbl_advs`
--

CREATE TABLE IF NOT EXISTS `tbl_advs` (
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
-- 資料表結構 `tbl_categories`
--

CREATE TABLE IF NOT EXISTS `tbl_categories` (
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
-- 資料表結構 `tbl_medias`
--

CREATE TABLE IF NOT EXISTS `tbl_medias` (
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
-- 資料表結構 `tbl_options`
--

CREATE TABLE IF NOT EXISTS `tbl_options` (
`id` int(11) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `content` text,
  `last_ts` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `last_user` int(11) DEFAULT NULL,
  `insert_ts` timestamp NULL DEFAULT NULL,
  `insert_user` int(11) DEFAULT NULL
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- 資料表結構 `tbl_posts`
--

CREATE TABLE IF NOT EXISTS `tbl_posts` (
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
-- 資料表結構 `tbl_tags`
--

CREATE TABLE IF NOT EXISTS `tbl_tags` (
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
-- 資料表結構 `tbl_users`
--

CREATE TABLE IF NOT EXISTS `tbl_users` (
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
-- 資料表索引 `tbl_advs`
--
ALTER TABLE `tbl_advs`
 ADD PRIMARY KEY (`id`), ADD KEY `category_id` (`position_id`), ADD KEY `uri` (`uri`);

--
-- 資料表索引 `tbl_categories`
--
ALTER TABLE `tbl_categories`
 ADD PRIMARY KEY (`id`), ADD UNIQUE KEY `uri` (`slug`), ADD KEY `parent_id` (`parent_id`);

--
-- 資料表索引 `tbl_contact`
--
ALTER TABLE `tbl_contact`
 ADD PRIMARY KEY (`id`);

--
-- 資料表索引 `tbl_medias`
--
ALTER TABLE `tbl_medias`
 ADD PRIMARY KEY (`id`);

--
-- 資料表索引 `tbl_options`
--
ALTER TABLE `tbl_options`
 ADD PRIMARY KEY (`id`);

--
-- 資料表索引 `tbl_posts`
--
ALTER TABLE `tbl_posts`
 ADD PRIMARY KEY (`id`);

--
-- 資料表索引 `tbl_tags`
--
ALTER TABLE `tbl_tags`
 ADD PRIMARY KEY (`id`);

--
-- 資料表索引 `tbl_users`
--
ALTER TABLE `tbl_users`
 ADD PRIMARY KEY (`id`);

--
-- 在匯出的資料表使用 AUTO_INCREMENT
--

--
-- 使用資料表 AUTO_INCREMENT `tbl_advs`
--
ALTER TABLE `tbl_advs`
MODIFY `id` int(11) NOT NULL AUTO_INCREMENT,AUTO_INCREMENT=1;
--
-- 使用資料表 AUTO_INCREMENT `tbl_categories`
--
ALTER TABLE `tbl_categories`
MODIFY `id` int(11) NOT NULL AUTO_INCREMENT,AUTO_INCREMENT=1;
--
-- 使用資料表 AUTO_INCREMENT `tbl_contact`
--
ALTER TABLE `tbl_contact`
MODIFY `id` int(11) NOT NULL AUTO_INCREMENT,AUTO_INCREMENT=1;
--
-- 使用資料表 AUTO_INCREMENT `tbl_medias`
--
ALTER TABLE `tbl_medias`
MODIFY `id` int(11) NOT NULL AUTO_INCREMENT,AUTO_INCREMENT=1;
--
-- 使用資料表 AUTO_INCREMENT `tbl_options`
--
ALTER TABLE `tbl_options`
MODIFY `id` int(11) NOT NULL AUTO_INCREMENT,AUTO_INCREMENT=1;
--
-- 使用資料表 AUTO_INCREMENT `tbl_posts`
--
ALTER TABLE `tbl_posts`
MODIFY `id` int(11) NOT NULL AUTO_INCREMENT,AUTO_INCREMENT=1;
--
-- 使用資料表 AUTO_INCREMENT `tbl_tags`
--
ALTER TABLE `tbl_tags`
MODIFY `id` int(11) NOT NULL AUTO_INCREMENT,AUTO_INCREMENT=1;
--
-- 使用資料表 AUTO_INCREMENT `tbl_users`
--
ALTER TABLE `tbl_users`
MODIFY `id` int(11) NOT NULL AUTO_INCREMENT,AUTO_INCREMENT=1;

INSERT INTO `tbl_users` (`id`, `status`, `account`, `pwd`, `last_ts`, `last_user`, `insert_ts`, `insert_user`) VALUES
(1, 'Verified', 'admin', '6fb42da0e32e07b61c9f0251fe627a9c', '2015-08-04 19:13:01', 1, '2015-08-05 00:41:20', 1);
