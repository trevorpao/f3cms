
--
-- 資料庫： `target_db` f3cms
-- f3cms sample db
--

CREATE DATABASE IF NOT EXISTS `target_db` DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;
USE `target_db`;

-- --------------------------------------------------------

--
-- 資料表結構 `sessions`
--

CREATE TABLE `sessions` (
  `session_id` varchar(255) NOT NULL,
  `data` text DEFAULT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `agent` varchar(300) DEFAULT NULL,
  `stamp` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- 資料表結構 `tbl_adv`
--

CREATE TABLE `tbl_adv` (
  `id` int(11) NOT NULL,
  `position_id` int(11) NOT NULL,
  `counter` int(11) NOT NULL,
  `exposure` int(11) NOT NULL DEFAULT 0,
  `status` enum('Enabled','Disabled') NOT NULL DEFAULT 'Disabled',
  `weight` int(11) NOT NULL DEFAULT 0,
  `theme` varchar(10) DEFAULT NULL,
  `start_date` timestamp NULL DEFAULT NULL,
  `end_date` timestamp NULL DEFAULT NULL,
  `uri` varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
  `cover` varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
  `background` varchar(255) NOT NULL,
  `last_ts` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_user` int(11) NOT NULL,
  `insert_user` int(11) NOT NULL,
  `insert_ts` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- 資料表結構 `tbl_adv_lang`
--

CREATE TABLE `tbl_adv_lang` (
  `id` int(11) NOT NULL,
  `lang` varchar(5) NOT NULL DEFAULT 'tw',
  `parent_id` int(11) NOT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 DEFAULT NULL,
  `subtitle` varchar(255) CHARACTER SET utf8mb4 DEFAULT NULL,
  `content` text CHARACTER SET utf8mb4 DEFAULT NULL,
  `last_ts` timestamp NULL DEFAULT current_timestamp(),
  `last_user` int(11) DEFAULT NULL,
  `insert_ts` timestamp NULL DEFAULT NULL,
  `insert_user` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 ROW_FORMAT=COMPACT;

-- --------------------------------------------------------

--
-- 資料表結構 `tbl_author`
--

CREATE TABLE `tbl_author` (
  `id` int(11) NOT NULL,
  `status` enum('Disabled','Enabled') DEFAULT 'Disabled',
  `slug` varchar(255) NOT NULL,
  `online_date` date DEFAULT NULL,
  `cover` varchar(255) NOT NULL,
  `last_ts` timestamp NULL DEFAULT current_timestamp(),
  `last_user` int(11) DEFAULT NULL,
  `insert_ts` timestamp NULL DEFAULT NULL,
  `insert_user` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- 資料表結構 `tbl_author_lang`
--

CREATE TABLE `tbl_author_lang` (
  `id` int(11) NOT NULL,
  `lang` varchar(5) NOT NULL DEFAULT 'tw',
  `parent_id` int(11) NOT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 DEFAULT NULL,
  `subtitle` varchar(255) CHARACTER SET utf8mb4 DEFAULT NULL,
  `content` text CHARACTER SET utf8mb4 DEFAULT NULL,
  `last_ts` timestamp NULL DEFAULT current_timestamp(),
  `last_user` int(11) DEFAULT NULL,
  `insert_ts` timestamp NULL DEFAULT NULL,
  `insert_user` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 ROW_FORMAT=COMPACT;

-- --------------------------------------------------------

--
-- 資料表結構 `tbl_author_tag`
--

CREATE TABLE `tbl_author_tag` (
  `author_id` int(11) NOT NULL,
  `tag_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- 資料表結構 `tbl_collection`
--

CREATE TABLE `tbl_collection` (
  `id` int(11) NOT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `cover` varchar(255) DEFAULT NULL,
  `txt_color` varchar(10) NOT NULL DEFAULT 'dark',
  `txt_algin` varchar(10) NOT NULL DEFAULT 'left',
  `last_ts` timestamp NULL DEFAULT current_timestamp(),
  `last_user` int(11) DEFAULT NULL,
  `insert_ts` timestamp NULL DEFAULT NULL,
  `insert_user` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- 資料表結構 `tbl_collection_lang`
--

CREATE TABLE `tbl_collection_lang` (
  `id` int(11) NOT NULL,
  `lang` varchar(5) NOT NULL DEFAULT 'tw',
  `parent_id` int(11) NOT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 DEFAULT NULL,
  `content` text CHARACTER SET utf8mb4 DEFAULT NULL,
  `last_ts` timestamp NULL DEFAULT current_timestamp(),
  `last_user` int(11) DEFAULT NULL,
  `insert_ts` timestamp NULL DEFAULT NULL,
  `insert_user` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- 資料表結構 `tbl_contact`
--

CREATE TABLE `tbl_contact` (
  `id` int(11) NOT NULL,
  `status` enum('New','Process','Done') NOT NULL DEFAULT 'New',
  `type` varchar(50) DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
  `phone` varchar(50) NOT NULL,
  `email` varchar(255) NOT NULL,
  `message` text CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
  `other` text DEFAULT NULL,
  `response` text CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
  `last_ts` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_user` int(11) NOT NULL,
  `insert_user` int(11) NOT NULL,
  `insert_ts` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- 資料表結構 `tbl_dictionary`
--

CREATE TABLE `tbl_dictionary` (
  `id` int(11) NOT NULL,
  `status` enum('Disabled','Enabled') DEFAULT 'Disabled',
  `slug` varchar(255) NOT NULL,
  `last_ts` timestamp NULL DEFAULT current_timestamp(),
  `last_user` int(11) DEFAULT NULL,
  `insert_ts` timestamp NULL DEFAULT NULL,
  `insert_user` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- 資料表結構 `tbl_dictionary_lang`
--

CREATE TABLE `tbl_dictionary_lang` (
  `id` int(11) NOT NULL,
  `lang` varchar(5) NOT NULL DEFAULT 'tw',
  `parent_id` int(11) NOT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 DEFAULT NULL,
  `subtitle` varchar(255) CHARACTER SET utf8mb4 DEFAULT NULL,
  `content` text CHARACTER SET utf8mb4 DEFAULT NULL,
  `last_ts` timestamp NULL DEFAULT current_timestamp(),
  `last_user` int(11) DEFAULT NULL,
  `insert_ts` timestamp NULL DEFAULT NULL,
  `insert_user` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 ROW_FORMAT=COMPACT;

-- --------------------------------------------------------

--
-- 資料表結構 `tbl_media`
--

CREATE TABLE `tbl_media` (
  `id` int(11) NOT NULL,
  `status` enum('Disabled','Enabled') DEFAULT 'Disabled',
  `slug` varchar(255) NOT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 DEFAULT NULL,
  `pic` varchar(255) NOT NULL,
  `info` varchar(255) CHARACTER SET utf8mb4 DEFAULT NULL,
  `last_ts` timestamp NULL DEFAULT current_timestamp(),
  `last_user` int(11) DEFAULT NULL,
  `insert_ts` timestamp NULL DEFAULT NULL,
  `insert_user` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- 資料表結構 `tbl_media_meta`
--

CREATE TABLE `tbl_media_meta` (
  `id` int(11) NOT NULL,
  `parent_id` int(11) NOT NULL,
  `last_ts` timestamp NULL DEFAULT current_timestamp(),
  `k` varchar(50) DEFAULT NULL,
  `v` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- 資料表結構 `tbl_media_tag`
--

CREATE TABLE `tbl_media_tag` (
  `media_id` int(11) NOT NULL,
  `tag_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- 資料表結構 `tbl_menu`
--

CREATE TABLE `tbl_menu` (
  `id` int(11) NOT NULL,
  `status` enum('Enabled','Disabled') NOT NULL DEFAULT 'Disabled',
  `parent_id` int(11) DEFAULT 0,
  `uri` varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
  `type` varchar(30) NOT NULL,
  `sorter` int(11) NOT NULL DEFAULT 0,
  `cover` varchar(150) DEFAULT NULL,
  `last_ts` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_user` int(11) NOT NULL,
  `insert_user` int(11) NOT NULL,
  `insert_ts` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- 資料表的匯出資料 `tbl_menu`
--

INSERT INTO `tbl_menu` (`id`, `status`, `parent_id`, `uri`, `type`, `sorter`, `cover`, `last_ts`, `last_user`, `insert_user`, `insert_ts`) VALUES
(1, 'Enabled', 0, '/nav', 'None', 1, '', '2017-01-19 13:09:45', 1, 1, NULL),
(2, 'Enabled', 0, '/sidebar', 'None', 3, '', '2015-12-10 02:02:02', 1, 1, NULL);

-- --------------------------------------------------------

--
-- 資料表結構 `tbl_menu_lang`
--

CREATE TABLE `tbl_menu_lang` (
  `id` int(11) NOT NULL,
  `lang` varchar(5) NOT NULL DEFAULT 'tw',
  `parent_id` int(11) NOT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 DEFAULT NULL,
  `info` varchar(255) CHARACTER SET utf8mb4 DEFAULT NULL,
  `last_ts` timestamp NULL DEFAULT current_timestamp(),
  `last_user` int(11) DEFAULT NULL,
  `insert_ts` timestamp NULL DEFAULT NULL,
  `insert_user` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- 資料表結構 `tbl_option`
--

CREATE TABLE `tbl_option` (
  `id` int(11) NOT NULL,
  `status` enum('Enabled','Disabled') NOT NULL DEFAULT 'Enabled',
  `loader` enum('Preload','Demand') NOT NULL DEFAULT 'Demand',
  `group` varchar(50) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `content` text CHARACTER SET utf8mb4 DEFAULT NULL,
  `last_ts` timestamp NULL DEFAULT current_timestamp(),
  `last_user` int(11) DEFAULT NULL,
  `insert_ts` timestamp NULL DEFAULT NULL,
  `insert_user` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- 資料表的匯出資料 `tbl_option`
--

INSERT INTO `tbl_option` (`id`, `status`, `loader`, `group`, `name`, `content`, `last_ts`, `last_user`, `insert_ts`, `insert_user`) VALUES
(1, 'Enabled', 'Demand', 'page', 'title', 'F3CMS DEMO', '2017-12-28 21:49:32', 1, '2015-12-29 14:43:32', 1),
(2, 'Enabled', 'Demand', 'page', 'keyword', 'key1,key2,key3', '2017-12-29 09:44:23', 1, '2015-12-29 14:44:11', 1),
(4, 'Enabled', 'Demand', 'page', 'img', 'demo.png', '2017-12-28 21:45:11', 1, '2015-12-29 14:46:44', 1),
(5, 'Enabled', 'Demand', 'social', 'facebook_page', 'https://www.facebook.com/', '2015-12-29 18:35:46', 1, '2015-12-29 18:35:46', 1),
(8, 'Enabled', 'Preload', 'default', 'contact_mail', 'sense.info.co@gmail.com', '2016-02-10 06:58:13', 1, '2016-02-02 10:08:41', 1),
(12, 'Enabled', 'Demand', 'page', 'ga', '', '2017-03-27 02:52:28', 1, '2016-05-04 07:51:12', 1),
(17, 'Enabled', 'Demand', 'page', 'pagetest', '', '2017-12-28 23:24:26', 1, '2017-12-28 23:24:26', 1),
(18, 'Enabled', 'Demand', '這是G', '這是N', '這是C', '2017-12-29 09:24:48', 1, '2017-12-29 09:24:48', 1),
(19, 'Enabled', 'Demand', 'Group01', 'soso', 'C1,C2,C3', '2017-12-29 09:26:51', 1, '2017-12-29 09:26:51', 1),
(20, 'Enabled', 'Demand', 'Group02', 'soso2', 'C1,C2,C5', '2017-12-29 09:27:02', 1, '2017-12-29 09:27:02', 1),
(21, 'Enabled', 'Demand', 'Group03', 'soso3', 'C1,C2,C7', '2017-12-29 09:27:11', 1, '2017-12-29 09:27:11', 1),
(22, 'Enabled', 'Demand', 'Group04', 'soso3', 'C1,C2,C9', '2017-12-29 09:27:20', 1, '2017-12-29 09:27:20', 1),
(23, 'Enabled', 'Demand', 'Group05', 'soso4', 'C1,C2,C10', '2017-12-29 09:28:17', 1, '2017-12-29 09:28:17', 1),
(24, 'Enabled', 'Demand', 'Group06', 'soso5', 'C1,C2,C11', '2017-12-29 09:28:28', 1, '2017-12-29 09:28:28', 1),
(25, 'Enabled', 'Demand', 'G1', 'N1', 'C1', '2017-12-29 09:32:02', 1, '2017-12-29 09:32:02', 1);

-- --------------------------------------------------------

--
-- 資料表結構 `tbl_post`
--

CREATE TABLE `tbl_post` (
  `id` int(11) NOT NULL,
  `status` enum('Disabled','Enabled') DEFAULT 'Disabled',
  `slug` varchar(255) NOT NULL,
  `cover` varchar(255) NOT NULL,
  `last_ts` timestamp NULL DEFAULT current_timestamp(),
  `last_user` int(11) DEFAULT NULL,
  `insert_ts` timestamp NULL DEFAULT NULL,
  `insert_user` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- 資料表的匯出資料 `tbl_post`
--

INSERT INTO `tbl_post` (`id`, `status`, `slug`, `cover`, `last_ts`, `last_user`, `insert_ts`, `insert_user`) VALUES
(3, 'Enabled', '/about', '', '2017-04-01 15:55:14', 1, '2017-01-17 18:07:53', 1),
(5, 'Enabled', '/contact', '', '2017-04-01 04:22:37', 1, '2017-03-26 02:22:21', 1);

-- --------------------------------------------------------

--
-- 資料表結構 `tbl_post_lang`
--

CREATE TABLE `tbl_post_lang` (
  `id` int(11) NOT NULL,
  `lang` varchar(5) NOT NULL DEFAULT 'tw',
  `parent_id` int(11) NOT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 DEFAULT NULL,
  `content` text CHARACTER SET utf8mb4 DEFAULT NULL,
  `last_ts` timestamp NULL DEFAULT current_timestamp(),
  `last_user` int(11) DEFAULT NULL,
  `insert_ts` timestamp NULL DEFAULT NULL,
  `insert_user` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- 資料表結構 `tbl_post_meta`
--

CREATE TABLE `tbl_post_meta` (
  `id` int(11) NOT NULL,
  `parent_id` int(11) NOT NULL,
  `last_ts` timestamp NULL DEFAULT current_timestamp(),
  `k` varchar(50) DEFAULT NULL,
  `v` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- 資料表結構 `tbl_post_tag`
--

CREATE TABLE `tbl_post_tag` (
  `post_id` int(11) NOT NULL,
  `tag_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- 資料表結構 `tbl_press`
--

CREATE TABLE `tbl_press` (
  `id` int(11) NOT NULL,
  `status` enum('Disabled','Enabled') DEFAULT 'Disabled',
  `mode` enum('Article','Slide') NOT NULL DEFAULT 'Article',
  `on_homepage` enum('Yes','No') NOT NULL DEFAULT 'No',
  `on_top` enum('Yes','No') NOT NULL DEFAULT 'No',
  `slug` varchar(255) NOT NULL,
  `online_date` date NOT NULL,
  `cover` varchar(255) NOT NULL,
  `last_ts` timestamp NULL DEFAULT current_timestamp(),
  `last_user` int(11) DEFAULT NULL,
  `insert_ts` timestamp NULL DEFAULT NULL,
  `insert_user` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- 資料表結構 `tbl_press_lang`
--

CREATE TABLE `tbl_press_lang` (
  `id` int(11) NOT NULL,
  `lang` varchar(5) NOT NULL DEFAULT 'tw',
  `parent_id` int(11) DEFAULT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 DEFAULT NULL,
  `keyword` varchar(255) CHARACTER SET utf8mb4 DEFAULT NULL,
  `info` varchar(255) CHARACTER SET utf8mb4 DEFAULT NULL,
  `content` text CHARACTER SET utf8mb4 DEFAULT NULL,
  `last_ts` timestamp NULL DEFAULT current_timestamp(),
  `last_user` int(11) DEFAULT NULL,
  `insert_ts` timestamp NULL DEFAULT NULL,
  `insert_user` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- 資料表結構 `tbl_press_meta`
--

CREATE TABLE `tbl_press_meta` (
  `id` int(11) NOT NULL,
  `parent_id` int(11) NOT NULL,
  `last_ts` timestamp NULL DEFAULT current_timestamp(),
  `k` varchar(50) DEFAULT NULL,
  `v` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- 資料表結構 `tbl_press_related`
--

CREATE TABLE `tbl_press_related` (
  `press_id` int(10) UNSIGNED NOT NULL,
  `related_id` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- 資料表結構 `tbl_press_tag`
--

CREATE TABLE `tbl_press_tag` (
  `press_id` int(11) NOT NULL,
  `tag_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- 資料表結構 `tbl_search`
--

CREATE TABLE `tbl_search` (
  `id` int(11) NOT NULL,
  `lang` varchar(5) NOT NULL DEFAULT 'tw',
  `status` enum('Disabled','Enabled') NOT NULL DEFAULT 'Disabled',
  `site_id` int(11) DEFAULT NULL,
  `counter` int(11) NOT NULL DEFAULT 0,
  `title` varchar(255) DEFAULT NULL,
  `info` varchar(255) DEFAULT NULL,
  `last_ts` timestamp NULL DEFAULT current_timestamp(),
  `last_user` int(11) DEFAULT NULL,
  `insert_ts` timestamp NULL DEFAULT NULL,
  `insert_user` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- 資料表結構 `tbl_search_press`
--

CREATE TABLE `tbl_search_press` (
  `press_id` int(11) NOT NULL,
  `search_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- 資料表結構 `tbl_staff`
--

CREATE TABLE `tbl_staff` (
  `id` int(11) NOT NULL,
  `status` enum('New','Verified','Freeze') DEFAULT 'New',
  `account` varchar(45) DEFAULT NULL,
  `pwd` varchar(45) DEFAULT NULL,
  `last_ts` timestamp NULL DEFAULT current_timestamp(),
  `last_user` int(11) DEFAULT NULL,
  `insert_ts` timestamp NULL DEFAULT NULL,
  `insert_user` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- 資料表的匯出資料 `tbl_staff`
--

INSERT INTO `tbl_staff` (`id`, `status`, `account`, `pwd`, `last_ts`, `last_user`, `insert_ts`, `insert_user`) VALUES
(1, 'Verified', 'admin', '81dc9bdb52d04dc20036dbd8313ed055', '2017-04-02 18:01:05', 1, '2015-08-05 04:41:20', 1);

-- --------------------------------------------------------

--
-- 資料表結構 `tbl_tag`
--

CREATE TABLE `tbl_tag` (
  `id` int(11) NOT NULL,
  `status` enum('Disabled','Enabled') DEFAULT 'Disabled',
  `parent_id` int(11) NOT NULL DEFAULT 0,
  `counter` int(11) DEFAULT NULL,
  `slug` varchar(255) DEFAULT NULL,
  `last_ts` timestamp NULL DEFAULT current_timestamp(),
  `last_user` int(11) DEFAULT NULL,
  `insert_ts` timestamp NULL DEFAULT NULL,
  `insert_user` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- 資料表結構 `tbl_tag_lang`
--

CREATE TABLE `tbl_tag_lang` (
  `id` int(11) NOT NULL,
  `lang` varchar(5) NOT NULL DEFAULT 'tw',
  `parent_id` int(11) NOT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 DEFAULT NULL,
  `alias` varchar(255) CHARACTER SET utf8mb4 DEFAULT NULL,
  `info` varchar(255) CHARACTER SET utf8mb4 DEFAULT NULL,
  `last_ts` timestamp NULL DEFAULT current_timestamp(),
  `last_user` int(11) DEFAULT NULL,
  `insert_ts` timestamp NULL DEFAULT NULL,
  `insert_user` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 ROW_FORMAT=COMPACT;

-- --------------------------------------------------------

--
-- 資料表結構 `tbl_tag_related`
--

CREATE TABLE `tbl_tag_related` (
  `related_id` int(11) NOT NULL,
  `tag_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- 已匯出資料表的索引
--

--
-- 資料表索引 `sessions`
--
ALTER TABLE `sessions`
  ADD PRIMARY KEY (`session_id`);

--
-- 資料表索引 `tbl_adv`
--
ALTER TABLE `tbl_adv`
  ADD PRIMARY KEY (`id`),
  ADD KEY `category_id` (`position_id`),
  ADD KEY `uri` (`uri`);

--
-- 資料表索引 `tbl_adv_lang`
--
ALTER TABLE `tbl_adv_lang`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `lang_pid` (`lang`,`parent_id`);

--
-- 資料表索引 `tbl_author`
--
ALTER TABLE `tbl_author`
  ADD PRIMARY KEY (`id`);

--
-- 資料表索引 `tbl_author_lang`
--
ALTER TABLE `tbl_author_lang`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `lang_pid` (`lang`,`parent_id`);

--
-- 資料表索引 `tbl_author_tag`
--
ALTER TABLE `tbl_author_tag`
  ADD PRIMARY KEY (`author_id`,`tag_id`);

--
-- 資料表索引 `tbl_collection`
--
ALTER TABLE `tbl_collection`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `parent_id` (`parent_id`);

--
-- 資料表索引 `tbl_collection_lang`
--
ALTER TABLE `tbl_collection_lang`
  ADD PRIMARY KEY (`id`),
  ADD KEY `lang_pid` (`lang`,`parent_id`);

--
-- 資料表索引 `tbl_contact`
--
ALTER TABLE `tbl_contact`
  ADD PRIMARY KEY (`id`);

--
-- 資料表索引 `tbl_dictionary`
--
ALTER TABLE `tbl_dictionary`
  ADD PRIMARY KEY (`id`);

--
-- 資料表索引 `tbl_dictionary_lang`
--
ALTER TABLE `tbl_dictionary_lang`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `lang_pid` (`lang`,`parent_id`);

--
-- 資料表索引 `tbl_media`
--
ALTER TABLE `tbl_media`
  ADD PRIMARY KEY (`id`);

--
-- 資料表索引 `tbl_media_meta`
--
ALTER TABLE `tbl_media_meta`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_meta_media_idx` (`parent_id`);

--
-- 資料表索引 `tbl_media_tag`
--
ALTER TABLE `tbl_media_tag`
  ADD PRIMARY KEY (`media_id`,`tag_id`);

--
-- 資料表索引 `tbl_menu`
--
ALTER TABLE `tbl_menu`
  ADD PRIMARY KEY (`id`),
  ADD KEY `parent_id` (`parent_id`);

--
-- 資料表索引 `tbl_menu_lang`
--
ALTER TABLE `tbl_menu_lang`
  ADD PRIMARY KEY (`id`),
  ADD KEY `lang_pid` (`lang`,`parent_id`);

--
-- 資料表索引 `tbl_option`
--
ALTER TABLE `tbl_option`
  ADD PRIMARY KEY (`id`),
  ADD KEY `group` (`group`);

--
-- 資料表索引 `tbl_post`
--
ALTER TABLE `tbl_post`
  ADD PRIMARY KEY (`id`);

--
-- 資料表索引 `tbl_post_lang`
--
ALTER TABLE `tbl_post_lang`
  ADD PRIMARY KEY (`id`),
  ADD KEY `lang_pid` (`lang`,`parent_id`);

--
-- 資料表索引 `tbl_post_meta`
--
ALTER TABLE `tbl_post_meta`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_meta_press_idx` (`parent_id`);

--
-- 資料表索引 `tbl_post_tag`
--
ALTER TABLE `tbl_post_tag`
  ADD PRIMARY KEY (`post_id`,`tag_id`);

--
-- 資料表索引 `tbl_press`
--
ALTER TABLE `tbl_press`
  ADD PRIMARY KEY (`id`);

--
-- 資料表索引 `tbl_press_lang`
--
ALTER TABLE `tbl_press_lang`
  ADD PRIMARY KEY (`id`),
  ADD KEY `lang_pid` (`lang`,`parent_id`);

--
-- 資料表索引 `tbl_press_meta`
--
ALTER TABLE `tbl_press_meta`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_meta_press_idx` (`parent_id`);

--
-- 資料表索引 `tbl_press_related`
--
ALTER TABLE `tbl_press_related`
  ADD PRIMARY KEY (`related_id`,`press_id`);

--
-- 資料表索引 `tbl_press_tag`
--
ALTER TABLE `tbl_press_tag`
  ADD PRIMARY KEY (`press_id`,`tag_id`);

--
-- 資料表索引 `tbl_staff`
--
ALTER TABLE `tbl_staff`
  ADD PRIMARY KEY (`id`);

--
-- 資料表索引 `tbl_tag`
--
ALTER TABLE `tbl_tag`
  ADD PRIMARY KEY (`id`);

--
-- 資料表索引 `tbl_tag_lang`
--
ALTER TABLE `tbl_tag_lang`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `lang_pid` (`lang`,`parent_id`);

--
-- 在匯出的資料表使用 AUTO_INCREMENT
--

--
-- 使用資料表 AUTO_INCREMENT `tbl_adv`
--
ALTER TABLE `tbl_adv`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- 使用資料表 AUTO_INCREMENT `tbl_adv_lang`
--
ALTER TABLE `tbl_adv_lang`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- 使用資料表 AUTO_INCREMENT `tbl_author`
--
ALTER TABLE `tbl_author`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- 使用資料表 AUTO_INCREMENT `tbl_author_lang`
--
ALTER TABLE `tbl_author_lang`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- 使用資料表 AUTO_INCREMENT `tbl_contact`
--
ALTER TABLE `tbl_contact`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- 使用資料表 AUTO_INCREMENT `tbl_dictionary`
--
ALTER TABLE `tbl_dictionary`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- 使用資料表 AUTO_INCREMENT `tbl_dictionary_lang`
--
ALTER TABLE `tbl_dictionary_lang`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- 使用資料表 AUTO_INCREMENT `tbl_media`
--
ALTER TABLE `tbl_media`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- 使用資料表 AUTO_INCREMENT `tbl_menu`
--
ALTER TABLE `tbl_menu`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- 使用資料表 AUTO_INCREMENT `tbl_menu_lang`
--
ALTER TABLE `tbl_menu_lang`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- 使用資料表 AUTO_INCREMENT `tbl_option`
--
ALTER TABLE `tbl_option`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- 使用資料表 AUTO_INCREMENT `tbl_post`
--
ALTER TABLE `tbl_post`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- 使用資料表 AUTO_INCREMENT `tbl_post_lang`
--
ALTER TABLE `tbl_post_lang`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- 使用資料表 AUTO_INCREMENT `tbl_press`
--
ALTER TABLE `tbl_press`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- 使用資料表 AUTO_INCREMENT `tbl_press_lang`
--
ALTER TABLE `tbl_press_lang`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- 使用資料表 AUTO_INCREMENT `tbl_staff`
--
ALTER TABLE `tbl_staff`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- 使用資料表 AUTO_INCREMENT `tbl_tag`
--
ALTER TABLE `tbl_tag`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- 使用資料表 AUTO_INCREMENT `tbl_tag_lang`
--
ALTER TABLE `tbl_tag_lang`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

