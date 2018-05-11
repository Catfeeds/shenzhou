CREATE TABLE `admin_ip_address` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ip_address` bigint NOT NULL COMMENT 'ip地址',
  `create_time` int(11) NOT NULL COMMENT '创建ip地址时间',
  `last_update_time` int(11) NOT NULL DEFAULT 0 COMMENT 'ip最后一次修改时间',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='登录ip表';


alter table `admin` add COLUMN `is_limit_ip` tinyint(4) NOT NULL DEFAULT 1 COMMENT 'ip限制:1代表限制，0代表不限制';
