CREATE TABLE `developers` (
  `account` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
  `name` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
  `email` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
  `nickname` varchar(30) COLLATE utf8_unicode_ci DEFAULT NULL,
  `dob` date DEFAULT NULL,
  `gender` enum('male','female') COLLATE utf8_unicode_ci DEFAULT NULL,
  `motivation` enum('volunteer','commercial') COLLATE utf8_unicode_ci DEFAULT NULL,
  `employer` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
  `colour` varchar(20) COLLATE utf8_unicode_ci DEFAULT NULL,
  `continent` enum('europe','north-america','south-america','oceania','africa','asia') COLLATE utf8_unicode_ci DEFAULT NULL,
  `country` varchar(2) COLLATE utf8_unicode_ci DEFAULT NULL,
  `location` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
  `latitude` float DEFAULT NULL,
  `longitude` float DEFAULT NULL,
  `homepage` text COLLATE utf8_unicode_ci,
  `blog` text COLLATE utf8_unicode_ci,
  `lastfm` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
  `microblog_type` enum('twitter','identica') COLLATE utf8_unicode_ci DEFAULT NULL,
  `microblog_user` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
  UNIQUE KEY `unqiue` (`account`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci