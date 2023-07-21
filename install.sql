DROP TABLE IF EXISTS `cloud_config`;
CREATE TABLE `cloud_config` (
  `key` varchar(32) NOT NULL,
  `value` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `cloud_config` (`key`, `value`) VALUES
('admin_username', 'admin'),
('admin_password', '123456'),
('bt_url', ''),
('bt_key', ''),
('whitelist', '0'),
('download_page', '1'),
('new_version', '8.0.1'),
('update_msg', '暂无更新日志'),
('update_date', '2023-07-20'),
('new_version_win', '7.9.0'),
('update_msg_win', '暂无更新日志'),
('update_date_win', '2023-07-20'),
('new_version_btm', '2.2.5'),
('update_msg_btm', '暂无更新日志'),
('update_date_btm', '2023-07-06'),
('updateall_type', '0'),
('syskey', 'UqP94LtI8eWAIgCP');


DROP TABLE IF EXISTS `cloud_black`;
CREATE TABLE `cloud_black` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ip` varchar(20) NOT NULL,
  `enable` tinyint(1) NOT NULL DEFAULT '1',
  `addtime` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ip`(`ip`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `cloud_white`;
CREATE TABLE `cloud_white` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ip` varchar(20) NOT NULL,
  `enable` tinyint(1) NOT NULL DEFAULT '1',
  `addtime` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ip`(`ip`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `cloud_record`;
CREATE TABLE `cloud_record` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ip` varchar(20) NOT NULL,
  `addtime` datetime NOT NULL,
  `usetime` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ip`(`ip`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `cloud_log`;
CREATE TABLE `cloud_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uid` tinyint(4) NOT NULL DEFAULT '1',
  `action` varchar(40) NOT NULL,
  `data` varchar(150) DEFAULT NULL,
  `addtime` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
