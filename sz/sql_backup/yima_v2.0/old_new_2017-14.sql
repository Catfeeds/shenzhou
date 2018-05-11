ALTER TABLE `factory_excel_datas_0` DROP INDEX `factory_id`;
ALTER TABLE `factory_excel_datas_1` DROP INDEX `factory_id`;
ALTER TABLE `factory_excel_datas_2` DROP INDEX `factory_id`;
ALTER TABLE `factory_excel_datas_3` DROP INDEX `factory_id`;
ALTER TABLE `factory_excel_datas_4` DROP INDEX `factory_id`;
ALTER TABLE `factory_excel_datas_5` DROP INDEX `factory_id`;
ALTER TABLE `factory_excel_datas_6` DROP INDEX `factory_id`;
ALTER TABLE `factory_excel_datas_7` DROP INDEX `factory_id`;
ALTER TABLE `factory_excel_datas_8` DROP INDEX `factory_id`;
ALTER TABLE `factory_excel_datas_9` DROP INDEX `factory_id`;
ALTER TABLE `factory_excel_datas_a` DROP INDEX `factory_id`;
ALTER TABLE `factory_excel_datas_b` DROP INDEX `factory_id`;
ALTER TABLE `factory_excel_datas_c` DROP INDEX `factory_id`;
ALTER TABLE `factory_excel_datas_d` DROP INDEX `factory_id`;
ALTER TABLE `factory_excel_datas_e` DROP INDEX `factory_id`;
ALTER TABLE `factory_excel_datas_f` DROP INDEX `factory_id`;

ALTER TABLE `factory_excel_datas_0` ADD INDEX `factory_water_index` (`factory_id`, `water`);
ALTER TABLE `factory_excel_datas_1` ADD INDEX `factory_water_index` (`factory_id`, `water`);
ALTER TABLE `factory_excel_datas_2` ADD INDEX `factory_water_index` (`factory_id`, `water`);
ALTER TABLE `factory_excel_datas_3` ADD INDEX `factory_water_index` (`factory_id`, `water`);
ALTER TABLE `factory_excel_datas_4` ADD INDEX `factory_water_index` (`factory_id`, `water`);
ALTER TABLE `factory_excel_datas_5` ADD INDEX `factory_water_index` (`factory_id`, `water`);
ALTER TABLE `factory_excel_datas_6` ADD INDEX `factory_water_index` (`factory_id`, `water`);
ALTER TABLE `factory_excel_datas_7` ADD INDEX `factory_water_index` (`factory_id`, `water`);
ALTER TABLE `factory_excel_datas_8` ADD INDEX `factory_water_index` (`factory_id`, `water`);
ALTER TABLE `factory_excel_datas_9` ADD INDEX `factory_water_index` (`factory_id`, `water`);
ALTER TABLE `factory_excel_datas_a` ADD INDEX `factory_water_index` (`factory_id`, `water`);
ALTER TABLE `factory_excel_datas_b` ADD INDEX `factory_water_index` (`factory_id`, `water`);
ALTER TABLE `factory_excel_datas_c` ADD INDEX `factory_water_index` (`factory_id`, `water`);
ALTER TABLE `factory_excel_datas_d` ADD INDEX `factory_water_index` (`factory_id`, `water`);
ALTER TABLE `factory_excel_datas_e` ADD INDEX `factory_water_index` (`factory_id`, `water`);
ALTER TABLE `factory_excel_datas_f` ADD INDEX `factory_water_index` (`factory_id`, `water`);

ALTER TABLE `factory_excel` DROP `url`;
DELETE FROM `factory_excel` WHERE `factory_id` = 0;
ALTER TABLE `factory_excel` ADD `qr_type` INT  NULL  DEFAULT '0'  AFTER `last_code`;
ALTER TABLE `factory_excel` ADD `qr_guige` INT  NULL  DEFAULT '0'  AFTER `qr_type`;
ALTER TABLE `factory_excel` ADD `is_check` INT(1)  NOT NULL  DEFAULT '0'  COMMENT '是否审核通过  0 未审核 1 审核通过 2 审核不通过 3 厂家自行取消 5 系统取消' AFTER `qr_guige`;
ALTER TABLE `factory_excel` ADD `check_time` INT(10)  NULL  DEFAULT '0'  COMMENT '审核时间' AFTER `is_check`;
ALTER TABLE `factory_excel` ADD `remarks` varchar(500) DEFAULT NULL COMMENT '申请备注' AFTER `qr_guige`;

UPDATE `factory_excel` SET `is_check` = '1' WHERE `add_time` IS NOT NULL;
UPDATE `factory_excel` SET `check_time` = `add_time` WHERE `add_time` IS NOT NULL;

DELETE FROM `factory_product_qrcode` WHERE `factory_id` = 0;
ALTER TABLE `factory_product_qrcode` ADD  `factory_excel_id` int(11) DEFAULT NULL AFTER `nums`;
ALTER TABLE `factory_product_qrcode` ADD  `shengchan_time`  int(10) NOT NULL DEFAULT '0' COMMENT '生产时间';
ALTER TABLE `factory_product_qrcode` ADD  `chuchang_time`  int(10) NOT NULL DEFAULT '0' COMMENT '出厂时间';
ALTER TABLE `factory_product_qrcode` ADD  `zhibao_time`  int(5) NOT NULL DEFAULT '12' COMMENT '质保期 单位月 默认12月';
ALTER TABLE `factory_product_qrcode` ADD  `remarks` varchar(500) DEFAULT NULL COMMENT '备注';
ALTER TABLE `factory_product_qrcode` ADD  `diy_remarks` varchar(500) DEFAULT NULL COMMENT '自定义备注';
ALTER TABLE `factory_product_qrcode` ADD  `active_json` JSON NOT NULL COMMENT '激活策略 is_active_type 允许激活身份 is_order_type 允许报修身份 active_credence_day 提供购买凭证 cant_active_credence_day 禁止激活 active_reward_moth 激活赠送延保';
UPDATE `factory_product_qrcode` set `active_json`='{"is_active_type":"1,2","is_order_type":"1,2","active_credence_day":0,"cant_active_credence_day":0,"active_reward_moth":0}';
ALTER TABLE `factory_product_qrcode` DROP `qr_first_code`;
ALTER TABLE `factory_product_qrcode` DROP `qr_last_code`;

ALTER TABLE `factory_product` ADD  `yima_status` int(1) NOT NULL DEFAULT '1'  COMMENT '0启用 1禁用' AFTER `product_status`;

CREATE TABLE `yima_0` (
  `code` varchar(150) NOT NULL DEFAULT '' COMMENT '易码号',
  `water` int(11) NOT NULL COMMENT '号',
  `factory_product_qrcode_id` int(11) NOT NULL COMMENT '二维码绑定记录id',
  `factory_id` int(11) NOT NULL COMMENT '厂家id',
  `product_id` int(11) DEFAULT NULL COMMENT '产品id',
  `shengchan_time` int(10) NOT NULL DEFAULT '0' COMMENT '生产时间',
  `chuchang_time` int(10) NOT NULL DEFAULT '0' COMMENT '出厂时间',
  `zhibao_time` int(5) NOT NULL DEFAULT '12' COMMENT '质保期 单位月 默认12月',
  `remarks` varchar(500) DEFAULT NULL COMMENT '备注',
  `diy_remarks` varchar(500) DEFAULT NULL COMMENT '自定义备注',
  `active_json` JSON NOT NULL COMMENT '激活策略 is_active_type 允许激活身份 is_order_type 允许报修身份 active_credence_day 提供购买凭证 cant_active_credence_day 禁止激活 active_reward_moth 激活赠送延保',
  `member_id` int(11) DEFAULT NULL COMMENT 'we_user 主键 用户id',
  `user_name` varchar(225) DEFAULT NULL COMMENT '激活用户姓名',
  `user_tel` varchar(225) DEFAULT NULL COMMENT '激活的用户电话',
  `user_address` varchar(500) DEFAULT NULL COMMENT '激活的用户地址',
  `active_time` int(10) NOT NULL DEFAULT '0' COMMENT '购买时间',
  `register_time` int(10) NOT NULL DEFAULT '0' COMMENT '激活时间',
  `saomiao` int(11) DEFAULT '0' COMMENT '扫描次数',
  `is_disable` INT(10)  NULL  DEFAULT '0'   COMMENT '是否停用',
  PRIMARY KEY (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `yima_1` (
  `code` varchar(150) NOT NULL DEFAULT '' COMMENT '易码号',
  `water` int(11) NOT NULL COMMENT '号',
  `factory_product_qrcode_id` int(11) NOT NULL COMMENT '二维码绑定记录id',
  `factory_id` int(11) NOT NULL COMMENT '厂家id',
  `product_id` int(11) DEFAULT NULL COMMENT '产品id',
  `shengchan_time` int(10) NOT NULL DEFAULT '0' COMMENT '生产时间',
  `chuchang_time` int(10) NOT NULL DEFAULT '0' COMMENT '出厂时间',
  `zhibao_time` int(5) NOT NULL DEFAULT '12' COMMENT '质保期 单位月 默认12月',
  `remarks` varchar(500) DEFAULT NULL COMMENT '备注',
  `diy_remarks` varchar(500) DEFAULT NULL COMMENT '自定义备注',
  `active_json` JSON NOT NULL COMMENT '激活策略 is_active_type 允许激活身份 is_order_type 允许报修身份 active_credence_day 提供购买凭证 cant_active_credence_day 禁止激活 active_reward_moth 激活赠送延保',
  `member_id` int(11) DEFAULT NULL COMMENT 'we_user 主键 用户id',
  `user_name` varchar(225) DEFAULT NULL COMMENT '激活用户姓名',
  `user_tel` varchar(225) DEFAULT NULL COMMENT '激活的用户电话',
  `user_address` varchar(500) DEFAULT NULL COMMENT '激活的用户地址',
  `active_time` int(10) NOT NULL DEFAULT '0' COMMENT '购买时间',
  `register_time` int(10) NOT NULL DEFAULT '0' COMMENT '激活时间',
  `saomiao` int(11) DEFAULT '0' COMMENT '扫描次数',
  `is_disable` INT(10)  NULL  DEFAULT '0'   COMMENT '是否停用',
  PRIMARY KEY (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `yima_2` (
  `code` varchar(150) NOT NULL DEFAULT '' COMMENT '易码号',
  `water` int(11) NOT NULL COMMENT '号',
  `factory_product_qrcode_id` int(11) NOT NULL COMMENT '二维码绑定记录id',
  `factory_id` int(11) NOT NULL COMMENT '厂家id',
  `product_id` int(11) DEFAULT NULL COMMENT '产品id',
  `shengchan_time` int(10) NOT NULL DEFAULT '0' COMMENT '生产时间',
  `chuchang_time` int(10) NOT NULL DEFAULT '0' COMMENT '出厂时间',
  `zhibao_time` int(5) NOT NULL DEFAULT '12' COMMENT '质保期 单位月 默认12月',
  `remarks` varchar(500) DEFAULT NULL COMMENT '备注',
  `diy_remarks` varchar(500) DEFAULT NULL COMMENT '自定义备注',
  `active_json` JSON NOT NULL COMMENT '激活策略 is_active_type 允许激活身份 is_order_type 允许报修身份 active_credence_day 提供购买凭证 cant_active_credence_day 禁止激活 active_reward_moth 激活赠送延保',
  `member_id` int(11) DEFAULT NULL COMMENT 'we_user 主键 用户id',
  `user_name` varchar(225) DEFAULT NULL COMMENT '激活用户姓名',
  `user_tel` varchar(225) DEFAULT NULL COMMENT '激活的用户电话',
  `user_address` varchar(500) DEFAULT NULL COMMENT '激活的用户地址',
  `active_time` int(10) NOT NULL DEFAULT '0' COMMENT '购买时间',
  `register_time` int(10) NOT NULL DEFAULT '0' COMMENT '激活时间',
  `saomiao` int(11) DEFAULT '0' COMMENT '扫描次数',
  `is_disable` INT(10)  NULL  DEFAULT '0'   COMMENT '是否停用',
  PRIMARY KEY (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `yima_3` (
  `code` varchar(150) NOT NULL DEFAULT '' COMMENT '易码号',
  `water` int(11) NOT NULL COMMENT '号',
  `factory_product_qrcode_id` int(11) NOT NULL COMMENT '二维码绑定记录id',
  `factory_id` int(11) NOT NULL COMMENT '厂家id',
  `product_id` int(11) DEFAULT NULL COMMENT '产品id',
  `shengchan_time` int(10) NOT NULL DEFAULT '0' COMMENT '生产时间',
  `chuchang_time` int(10) NOT NULL DEFAULT '0' COMMENT '出厂时间',
  `zhibao_time` int(5) NOT NULL DEFAULT '12' COMMENT '质保期 单位月 默认12月',
  `remarks` varchar(500) DEFAULT NULL COMMENT '备注',
  `diy_remarks` varchar(500) DEFAULT NULL COMMENT '自定义备注',
  `active_json` JSON NOT NULL COMMENT '激活策略 is_active_type 允许激活身份 is_order_type 允许报修身份 active_credence_day 提供购买凭证 cant_active_credence_day 禁止激活 active_reward_moth 激活赠送延保',
  `member_id` int(11) DEFAULT NULL COMMENT 'we_user 主键 用户id',
  `user_name` varchar(225) DEFAULT NULL COMMENT '激活用户姓名',
  `user_tel` varchar(225) DEFAULT NULL COMMENT '激活的用户电话',
  `user_address` varchar(500) DEFAULT NULL COMMENT '激活的用户地址',
  `active_time` int(10) NOT NULL DEFAULT '0' COMMENT '购买时间',
  `register_time` int(10) NOT NULL DEFAULT '0' COMMENT '激活时间',
  `saomiao` int(11) DEFAULT '0' COMMENT '扫描次数',
  `is_disable` INT(10)  NULL  DEFAULT '0'   COMMENT '是否停用',
  PRIMARY KEY (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `yima_4` (
  `code` varchar(150) NOT NULL DEFAULT '' COMMENT '易码号',
  `water` int(11) NOT NULL COMMENT '号',
  `factory_product_qrcode_id` int(11) NOT NULL COMMENT '二维码绑定记录id',
  `factory_id` int(11) NOT NULL COMMENT '厂家id',
  `product_id` int(11) DEFAULT NULL COMMENT '产品id',
  `shengchan_time` int(10) NOT NULL DEFAULT '0' COMMENT '生产时间',
  `chuchang_time` int(10) NOT NULL DEFAULT '0' COMMENT '出厂时间',
  `zhibao_time` int(5) NOT NULL DEFAULT '12' COMMENT '质保期 单位月 默认12月',
  `remarks` varchar(500) DEFAULT NULL COMMENT '备注',
  `diy_remarks` varchar(500) DEFAULT NULL COMMENT '自定义备注',
  `active_json` JSON NOT NULL COMMENT '激活策略 is_active_type 允许激活身份 is_order_type 允许报修身份 active_credence_day 提供购买凭证 cant_active_credence_day 禁止激活 active_reward_moth 激活赠送延保',
  `member_id` int(11) DEFAULT NULL COMMENT 'we_user 主键 用户id',
  `user_name` varchar(225) DEFAULT NULL COMMENT '激活用户姓名',
  `user_tel` varchar(225) DEFAULT NULL COMMENT '激活的用户电话',
  `user_address` varchar(500) DEFAULT NULL COMMENT '激活的用户地址',
  `active_time` int(10) NOT NULL DEFAULT '0' COMMENT '购买时间',
  `register_time` int(10) NOT NULL DEFAULT '0' COMMENT '激活时间',
  `saomiao` int(11) DEFAULT '0' COMMENT '扫描次数',
  `is_disable` INT(10)  NULL  DEFAULT '0'   COMMENT '是否停用',
  PRIMARY KEY (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `yima_5` (
  `code` varchar(150) NOT NULL DEFAULT '' COMMENT '易码号',
  `water` int(11) NOT NULL COMMENT '号',
  `factory_product_qrcode_id` int(11) NOT NULL COMMENT '二维码绑定记录id',
  `factory_id` int(11) NOT NULL COMMENT '厂家id',
  `product_id` int(11) DEFAULT NULL COMMENT '产品id',
  `shengchan_time` int(10) NOT NULL DEFAULT '0' COMMENT '生产时间',
  `chuchang_time` int(10) NOT NULL DEFAULT '0' COMMENT '出厂时间',
  `zhibao_time` int(5) NOT NULL DEFAULT '12' COMMENT '质保期 单位月 默认12月',
  `remarks` varchar(500) DEFAULT NULL COMMENT '备注',
  `diy_remarks` varchar(500) DEFAULT NULL COMMENT '自定义备注',
  `active_json` JSON NOT NULL COMMENT '激活策略 is_active_type 允许激活身份 is_order_type 允许报修身份 active_credence_day 提供购买凭证 cant_active_credence_day 禁止激活 active_reward_moth 激活赠送延保',
  `member_id` int(11) DEFAULT NULL COMMENT 'we_user 主键 用户id',
  `user_name` varchar(225) DEFAULT NULL COMMENT '激活用户姓名',
  `user_tel` varchar(225) DEFAULT NULL COMMENT '激活的用户电话',
  `user_address` varchar(500) DEFAULT NULL COMMENT '激活的用户地址',
  `active_time` int(10) NOT NULL DEFAULT '0' COMMENT '购买时间',
  `register_time` int(10) NOT NULL DEFAULT '0' COMMENT '激活时间',
  `saomiao` int(11) DEFAULT '0' COMMENT '扫描次数',
  `is_disable` INT(10)  NULL  DEFAULT '0'   COMMENT '是否停用',
  PRIMARY KEY (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `yima_6` (
  `code` varchar(150) NOT NULL DEFAULT '' COMMENT '易码号',
  `water` int(11) NOT NULL COMMENT '号',
  `factory_product_qrcode_id` int(11) NOT NULL COMMENT '二维码绑定记录id',
  `factory_id` int(11) NOT NULL COMMENT '厂家id',
  `product_id` int(11) DEFAULT NULL COMMENT '产品id',
  `shengchan_time` int(10) NOT NULL DEFAULT '0' COMMENT '生产时间',
  `chuchang_time` int(10) NOT NULL DEFAULT '0' COMMENT '出厂时间',
  `zhibao_time` int(5) NOT NULL DEFAULT '12' COMMENT '质保期 单位月 默认12月',
  `remarks` varchar(500) DEFAULT NULL COMMENT '备注',
  `diy_remarks` varchar(500) DEFAULT NULL COMMENT '自定义备注',
  `active_json` JSON NOT NULL COMMENT '激活策略 is_active_type 允许激活身份 is_order_type 允许报修身份 active_credence_day 提供购买凭证 cant_active_credence_day 禁止激活 active_reward_moth 激活赠送延保',
  `member_id` int(11) DEFAULT NULL COMMENT 'we_user 主键 用户id',
  `user_name` varchar(225) DEFAULT NULL COMMENT '激活用户姓名',
  `user_tel` varchar(225) DEFAULT NULL COMMENT '激活的用户电话',
  `user_address` varchar(500) DEFAULT NULL COMMENT '激活的用户地址',
  `active_time` int(10) NOT NULL DEFAULT '0' COMMENT '购买时间',
  `register_time` int(10) NOT NULL DEFAULT '0' COMMENT '激活时间',
  `saomiao` int(11) DEFAULT '0' COMMENT '扫描次数',
  `is_disable` INT(10)  NULL  DEFAULT '0'   COMMENT '是否停用',
  PRIMARY KEY (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `yima_7` (
  `code` varchar(150) NOT NULL DEFAULT '' COMMENT '易码号',
  `water` int(11) NOT NULL COMMENT '号',
  `factory_product_qrcode_id` int(11) NOT NULL COMMENT '二维码绑定记录id',
  `factory_id` int(11) NOT NULL COMMENT '厂家id',
  `product_id` int(11) DEFAULT NULL COMMENT '产品id',
  `shengchan_time` int(10) NOT NULL DEFAULT '0' COMMENT '生产时间',
  `chuchang_time` int(10) NOT NULL DEFAULT '0' COMMENT '出厂时间',
  `zhibao_time` int(5) NOT NULL DEFAULT '12' COMMENT '质保期 单位月 默认12月',
  `remarks` varchar(500) DEFAULT NULL COMMENT '备注',
  `diy_remarks` varchar(500) DEFAULT NULL COMMENT '自定义备注',
  `active_json` JSON NOT NULL COMMENT '激活策略 is_active_type 允许激活身份 is_order_type 允许报修身份 active_credence_day 提供购买凭证 cant_active_credence_day 禁止激活 active_reward_moth 激活赠送延保',
  `member_id` int(11) DEFAULT NULL COMMENT 'we_user 主键 用户id',
  `user_name` varchar(225) DEFAULT NULL COMMENT '激活用户姓名',
  `user_tel` varchar(225) DEFAULT NULL COMMENT '激活的用户电话',
  `user_address` varchar(500) DEFAULT NULL COMMENT '激活的用户地址',
  `active_time` int(10) NOT NULL DEFAULT '0' COMMENT '购买时间',
  `register_time` int(10) NOT NULL DEFAULT '0' COMMENT '激活时间',
  `saomiao` int(11) DEFAULT '0' COMMENT '扫描次数',
  `is_disable` INT(10)  NULL  DEFAULT '0'   COMMENT '是否停用',
  PRIMARY KEY (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `yima_8` (
  `code` varchar(150) NOT NULL DEFAULT '' COMMENT '易码号',
  `water` int(11) NOT NULL COMMENT '号',
  `factory_product_qrcode_id` int(11) NOT NULL COMMENT '二维码绑定记录id',
  `factory_id` int(11) NOT NULL COMMENT '厂家id',
  `product_id` int(11) DEFAULT NULL COMMENT '产品id',
  `shengchan_time` int(10) NOT NULL DEFAULT '0' COMMENT '生产时间',
  `chuchang_time` int(10) NOT NULL DEFAULT '0' COMMENT '出厂时间',
  `zhibao_time` int(5) NOT NULL DEFAULT '12' COMMENT '质保期 单位月 默认12月',
  `remarks` varchar(500) DEFAULT NULL COMMENT '备注',
  `diy_remarks` varchar(500) DEFAULT NULL COMMENT '自定义备注',
  `active_json` JSON NOT NULL COMMENT '激活策略 is_active_type 允许激活身份 is_order_type 允许报修身份 active_credence_day 提供购买凭证 cant_active_credence_day 禁止激活 active_reward_moth 激活赠送延保',
  `member_id` int(11) DEFAULT NULL COMMENT 'we_user 主键 用户id',
  `user_name` varchar(225) DEFAULT NULL COMMENT '激活用户姓名',
  `user_tel` varchar(225) DEFAULT NULL COMMENT '激活的用户电话',
  `user_address` varchar(500) DEFAULT NULL COMMENT '激活的用户地址',
  `active_time` int(10) NOT NULL DEFAULT '0' COMMENT '购买时间',
  `register_time` int(10) NOT NULL DEFAULT '0' COMMENT '激活时间',
  `saomiao` int(11) DEFAULT '0' COMMENT '扫描次数',
  `is_disable` INT(10)  NULL  DEFAULT '0'   COMMENT '是否停用',
  PRIMARY KEY (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `yima_9` (
  `code` varchar(150) NOT NULL DEFAULT '' COMMENT '易码号',
  `water` int(11) NOT NULL COMMENT '号',
  `factory_product_qrcode_id` int(11) NOT NULL COMMENT '二维码绑定记录id',
  `factory_id` int(11) NOT NULL COMMENT '厂家id',
  `product_id` int(11) DEFAULT NULL COMMENT '产品id',
  `shengchan_time` int(10) NOT NULL DEFAULT '0' COMMENT '生产时间',
  `chuchang_time` int(10) NOT NULL DEFAULT '0' COMMENT '出厂时间',
  `zhibao_time` int(5) NOT NULL DEFAULT '12' COMMENT '质保期 单位月 默认12月',
  `remarks` varchar(500) DEFAULT NULL COMMENT '备注',
  `diy_remarks` varchar(500) DEFAULT NULL COMMENT '自定义备注',
  `active_json` JSON NOT NULL COMMENT '激活策略 is_active_type 允许激活身份 is_order_type 允许报修身份 active_credence_day 提供购买凭证 cant_active_credence_day 禁止激活 active_reward_moth 激活赠送延保',
  `member_id` int(11) DEFAULT NULL COMMENT 'we_user 主键 用户id',
  `user_name` varchar(225) DEFAULT NULL COMMENT '激活用户姓名',
  `user_tel` varchar(225) DEFAULT NULL COMMENT '激活的用户电话',
  `user_address` varchar(500) DEFAULT NULL COMMENT '激活的用户地址',
  `active_time` int(10) NOT NULL DEFAULT '0' COMMENT '购买时间',
  `register_time` int(10) NOT NULL DEFAULT '0' COMMENT '激活时间',
  `saomiao` int(11) DEFAULT '0' COMMENT '扫描次数',
  `is_disable` INT(10)  NULL  DEFAULT '0'   COMMENT '是否停用',
  PRIMARY KEY (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `yima_10` (
  `code` varchar(150) NOT NULL DEFAULT '' COMMENT '易码号',
  `water` int(11) NOT NULL COMMENT '号',
  `factory_product_qrcode_id` int(11) NOT NULL COMMENT '二维码绑定记录id',
  `factory_id` int(11) NOT NULL COMMENT '厂家id',
  `product_id` int(11) DEFAULT NULL COMMENT '产品id',
  `shengchan_time` int(10) NOT NULL DEFAULT '0' COMMENT '生产时间',
  `chuchang_time` int(10) NOT NULL DEFAULT '0' COMMENT '出厂时间',
  `zhibao_time` int(5) NOT NULL DEFAULT '12' COMMENT '质保期 单位月 默认12月',
  `remarks` varchar(500) DEFAULT NULL COMMENT '备注',
  `diy_remarks` varchar(500) DEFAULT NULL COMMENT '自定义备注',
  `active_json` JSON NOT NULL COMMENT '激活策略 is_active_type 允许激活身份 is_order_type 允许报修身份 active_credence_day 提供购买凭证 cant_active_credence_day 禁止激活 active_reward_moth 激活赠送延保',
  `member_id` int(11) DEFAULT NULL COMMENT 'we_user 主键 用户id',
  `user_name` varchar(225) DEFAULT NULL COMMENT '激活用户姓名',
  `user_tel` varchar(225) DEFAULT NULL COMMENT '激活的用户电话',
  `user_address` varchar(500) DEFAULT NULL COMMENT '激活的用户地址',
  `active_time` int(10) NOT NULL DEFAULT '0' COMMENT '购买时间',
  `register_time` int(10) NOT NULL DEFAULT '0' COMMENT '激活时间',
  `saomiao` int(11) DEFAULT '0' COMMENT '扫描次数',
  `is_disable` INT(10)  NULL  DEFAULT '0'   COMMENT '是否停用',
  PRIMARY KEY (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `yima_11` (
  `code` varchar(150) NOT NULL DEFAULT '' COMMENT '易码号',
  `water` int(11) NOT NULL COMMENT '号',
  `factory_product_qrcode_id` int(11) NOT NULL COMMENT '二维码绑定记录id',
  `factory_id` int(11) NOT NULL COMMENT '厂家id',
  `product_id` int(11) DEFAULT NULL COMMENT '产品id',
  `shengchan_time` int(10) NOT NULL DEFAULT '0' COMMENT '生产时间',
  `chuchang_time` int(10) NOT NULL DEFAULT '0' COMMENT '出厂时间',
  `zhibao_time` int(5) NOT NULL DEFAULT '12' COMMENT '质保期 单位月 默认12月',
  `remarks` varchar(500) DEFAULT NULL COMMENT '备注',
  `diy_remarks` varchar(500) DEFAULT NULL COMMENT '自定义备注',
  `active_json` JSON NOT NULL COMMENT '激活策略 is_active_type 允许激活身份 is_order_type 允许报修身份 active_credence_day 提供购买凭证 cant_active_credence_day 禁止激活 active_reward_moth 激活赠送延保',
  `member_id` int(11) DEFAULT NULL COMMENT 'we_user 主键 用户id',
  `user_name` varchar(225) DEFAULT NULL COMMENT '激活用户姓名',
  `user_tel` varchar(225) DEFAULT NULL COMMENT '激活的用户电话',
  `user_address` varchar(500) DEFAULT NULL COMMENT '激活的用户地址',
  `active_time` int(10) NOT NULL DEFAULT '0' COMMENT '购买时间',
  `register_time` int(10) NOT NULL DEFAULT '0' COMMENT '激活时间',
  `saomiao` int(11) DEFAULT '0' COMMENT '扫描次数',
  `is_disable` INT(10)  NULL  DEFAULT '0'   COMMENT '是否停用',
  PRIMARY KEY (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `yima_12` (
  `code` varchar(150) NOT NULL DEFAULT '' COMMENT '易码号',
  `water` int(11) NOT NULL COMMENT '号',
  `factory_product_qrcode_id` int(11) NOT NULL COMMENT '二维码绑定记录id',
  `factory_id` int(11) NOT NULL COMMENT '厂家id',
  `product_id` int(11) DEFAULT NULL COMMENT '产品id',
  `shengchan_time` int(10) NOT NULL DEFAULT '0' COMMENT '生产时间',
  `chuchang_time` int(10) NOT NULL DEFAULT '0' COMMENT '出厂时间',
  `zhibao_time` int(5) NOT NULL DEFAULT '12' COMMENT '质保期 单位月 默认12月',
  `remarks` varchar(500) DEFAULT NULL COMMENT '备注',
  `diy_remarks` varchar(500) DEFAULT NULL COMMENT '自定义备注',
  `active_json` JSON NOT NULL COMMENT '激活策略 is_active_type 允许激活身份 is_order_type 允许报修身份 active_credence_day 提供购买凭证 cant_active_credence_day 禁止激活 active_reward_moth 激活赠送延保',
  `member_id` int(11) DEFAULT NULL COMMENT 'we_user 主键 用户id',
  `user_name` varchar(225) DEFAULT NULL COMMENT '激活用户姓名',
  `user_tel` varchar(225) DEFAULT NULL COMMENT '激活的用户电话',
  `user_address` varchar(500) DEFAULT NULL COMMENT '激活的用户地址',
  `active_time` int(10) NOT NULL DEFAULT '0' COMMENT '购买时间',
  `register_time` int(10) NOT NULL DEFAULT '0' COMMENT '激活时间',
  `saomiao` int(11) DEFAULT '0' COMMENT '扫描次数',
  `is_disable` INT(10)  NULL  DEFAULT '0'   COMMENT '是否停用',
  PRIMARY KEY (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `yima_13` (
  `code` varchar(150) NOT NULL DEFAULT '' COMMENT '易码号',
  `water` int(11) NOT NULL COMMENT '号',
  `factory_product_qrcode_id` int(11) NOT NULL COMMENT '二维码绑定记录id',
  `factory_id` int(11) NOT NULL COMMENT '厂家id',
  `product_id` int(11) DEFAULT NULL COMMENT '产品id',
  `shengchan_time` int(10) NOT NULL DEFAULT '0' COMMENT '生产时间',
  `chuchang_time` int(10) NOT NULL DEFAULT '0' COMMENT '出厂时间',
  `zhibao_time` int(5) NOT NULL DEFAULT '12' COMMENT '质保期 单位月 默认12月',
  `remarks` varchar(500) DEFAULT NULL COMMENT '备注',
  `diy_remarks` varchar(500) DEFAULT NULL COMMENT '自定义备注',
  `active_json` JSON NOT NULL COMMENT '激活策略 is_active_type 允许激活身份 is_order_type 允许报修身份 active_credence_day 提供购买凭证 cant_active_credence_day 禁止激活 active_reward_moth 激活赠送延保',
  `member_id` int(11) DEFAULT NULL COMMENT 'we_user 主键 用户id',
  `user_name` varchar(225) DEFAULT NULL COMMENT '激活用户姓名',
  `user_tel` varchar(225) DEFAULT NULL COMMENT '激活的用户电话',
  `user_address` varchar(500) DEFAULT NULL COMMENT '激活的用户地址',
  `active_time` int(10) NOT NULL DEFAULT '0' COMMENT '购买时间',
  `register_time` int(10) NOT NULL DEFAULT '0' COMMENT '激活时间',
  `saomiao` int(11) DEFAULT '0' COMMENT '扫描次数',
  `is_disable` INT(10)  NULL  DEFAULT '0'   COMMENT '是否停用',
  PRIMARY KEY (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `yima_14` (
  `code` varchar(150) NOT NULL DEFAULT '' COMMENT '易码号',
  `water` int(11) NOT NULL COMMENT '号',
  `factory_product_qrcode_id` int(11) NOT NULL COMMENT '二维码绑定记录id',
  `factory_id` int(11) NOT NULL COMMENT '厂家id',
  `product_id` int(11) DEFAULT NULL COMMENT '产品id',
  `shengchan_time` int(10) NOT NULL DEFAULT '0' COMMENT '生产时间',
  `chuchang_time` int(10) NOT NULL DEFAULT '0' COMMENT '出厂时间',
  `zhibao_time` int(5) NOT NULL DEFAULT '12' COMMENT '质保期 单位月 默认12月',
  `remarks` varchar(500) DEFAULT NULL COMMENT '备注',
  `diy_remarks` varchar(500) DEFAULT NULL COMMENT '自定义备注',
  `active_json` JSON NOT NULL COMMENT '激活策略 is_active_type 允许激活身份 is_order_type 允许报修身份 active_credence_day 提供购买凭证 cant_active_credence_day 禁止激活 active_reward_moth 激活赠送延保',
  `member_id` int(11) DEFAULT NULL COMMENT 'we_user 主键 用户id',
  `user_name` varchar(225) DEFAULT NULL COMMENT '激活用户姓名',
  `user_tel` varchar(225) DEFAULT NULL COMMENT '激活的用户电话',
  `user_address` varchar(500) DEFAULT NULL COMMENT '激活的用户地址',
  `active_time` int(10) NOT NULL DEFAULT '0' COMMENT '购买时间',
  `register_time` int(10) NOT NULL DEFAULT '0' COMMENT '激活时间',
  `saomiao` int(11) DEFAULT '0' COMMENT '扫描次数',
  `is_disable` INT(10)  NULL  DEFAULT '0'   COMMENT '是否停用',
  PRIMARY KEY (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `yima_15` (
  `code` varchar(150) NOT NULL DEFAULT '' COMMENT '易码号',
  `water` int(11) NOT NULL COMMENT '号',
  `factory_product_qrcode_id` int(11) NOT NULL COMMENT '二维码绑定记录id',
  `factory_id` int(11) NOT NULL COMMENT '厂家id',
  `product_id` int(11) DEFAULT NULL COMMENT '产品id',
  `shengchan_time` int(10) NOT NULL DEFAULT '0' COMMENT '生产时间',
  `chuchang_time` int(10) NOT NULL DEFAULT '0' COMMENT '出厂时间',
  `zhibao_time` int(5) NOT NULL DEFAULT '12' COMMENT '质保期 单位月 默认12月',
  `remarks` varchar(500) DEFAULT NULL COMMENT '备注',
  `diy_remarks` varchar(500) DEFAULT NULL COMMENT '自定义备注',
  `active_json` JSON NOT NULL COMMENT '激活策略 is_active_type 允许激活身份 is_order_type 允许报修身份 active_credence_day 提供购买凭证 cant_active_credence_day 禁止激活 active_reward_moth 激活赠送延保',
  `member_id` int(11) DEFAULT NULL COMMENT 'we_user 主键 用户id',
  `user_name` varchar(225) DEFAULT NULL COMMENT '激活用户姓名',
  `user_tel` varchar(225) DEFAULT NULL COMMENT '激活的用户电话',
  `user_address` varchar(500) DEFAULT NULL COMMENT '激活的用户地址',
  `active_time` int(10) NOT NULL DEFAULT '0' COMMENT '购买时间',
  `register_time` int(10) NOT NULL DEFAULT '0' COMMENT '激活时间',
  `saomiao` int(11) DEFAULT '0' COMMENT '扫描次数',
  `is_disable` INT(10)  NULL  DEFAULT '0'   COMMENT '是否停用',
  PRIMARY KEY (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `old_yima_code_index_0` (
  `md5code` varchar(32) NOT NULL DEFAULT '',
  `code` varchar(150) NOT NULL DEFAULT '',
  `url` varchar(500) DEFAULT NULL,
  PRIMARY KEY (`md5code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='旧易码 迁移中间表';

CREATE TABLE `old_yima_code_index_1` (
  `md5code` varchar(32) NOT NULL DEFAULT '',
  `code` varchar(150) NOT NULL DEFAULT '',
  `url` varchar(500) DEFAULT NULL,
  PRIMARY KEY (`md5code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='旧易码 迁移中间表';

CREATE TABLE `old_yima_code_index_2` (
  `md5code` varchar(32) NOT NULL DEFAULT '',
  `code` varchar(150) NOT NULL DEFAULT '',
  `url` varchar(500) DEFAULT NULL,
  PRIMARY KEY (`md5code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='旧易码 迁移中间表';

CREATE TABLE `old_yima_code_index_3` (
  `md5code` varchar(32) NOT NULL DEFAULT '',
  `code` varchar(150) NOT NULL DEFAULT '',
  `url` varchar(500) DEFAULT NULL,
  PRIMARY KEY (`md5code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='旧易码 迁移中间表';

CREATE TABLE `old_yima_code_index_4` (
  `md5code` varchar(32) NOT NULL DEFAULT '',
  `code` varchar(150) NOT NULL DEFAULT '',
  `url` varchar(500) DEFAULT NULL,
  PRIMARY KEY (`md5code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='旧易码 迁移中间表';

CREATE TABLE `old_yima_code_index_5` (
  `md5code` varchar(32) NOT NULL DEFAULT '',
  `code` varchar(150) NOT NULL DEFAULT '',
  `url` varchar(500) DEFAULT NULL,
  PRIMARY KEY (`md5code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='旧易码 迁移中间表';

CREATE TABLE `old_yima_code_index_6` (
  `md5code` varchar(32) NOT NULL DEFAULT '',
  `code` varchar(150) NOT NULL DEFAULT '',
  `url` varchar(500) DEFAULT NULL,
  PRIMARY KEY (`md5code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='旧易码 迁移中间表';

CREATE TABLE `old_yima_code_index_7` (
  `md5code` varchar(32) NOT NULL DEFAULT '',
  `code` varchar(150) NOT NULL DEFAULT '',
  `url` varchar(500) DEFAULT NULL,
  PRIMARY KEY (`md5code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='旧易码 迁移中间表';

CREATE TABLE `old_yima_code_index_8` (
  `md5code` varchar(32) NOT NULL DEFAULT '',
  `code` varchar(150) NOT NULL DEFAULT '',
  `url` varchar(500) DEFAULT NULL,
  PRIMARY KEY (`md5code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='旧易码 迁移中间表';

CREATE TABLE `old_yima_code_index_9` (
  `md5code` varchar(32) NOT NULL DEFAULT '',
  `code` varchar(150) NOT NULL DEFAULT '',
  `url` varchar(500) DEFAULT NULL,
  PRIMARY KEY (`md5code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='旧易码 迁移中间表';

CREATE TABLE `old_yima_code_index_a` (
  `md5code` varchar(32) NOT NULL DEFAULT '',
  `code` varchar(150) NOT NULL DEFAULT '',
  `url` varchar(500) DEFAULT NULL,
  PRIMARY KEY (`md5code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='旧易码 迁移中间表';

CREATE TABLE `old_yima_code_index_b` (
  `md5code` varchar(32) NOT NULL DEFAULT '',
  `code` varchar(150) NOT NULL DEFAULT '',
  `url` varchar(500) DEFAULT NULL,
  PRIMARY KEY (`md5code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='旧易码 迁移中间表';

CREATE TABLE `old_yima_code_index_c` (
  `md5code` varchar(32) NOT NULL DEFAULT '',
  `code` varchar(150) NOT NULL DEFAULT '',
  `url` varchar(500) DEFAULT NULL,
  PRIMARY KEY (`md5code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='旧易码 迁移中间表';

CREATE TABLE `old_yima_code_index_d` (
  `md5code` varchar(32) NOT NULL DEFAULT '',
  `code` varchar(150) NOT NULL DEFAULT '',
  `url` varchar(500) DEFAULT NULL,
  PRIMARY KEY (`md5code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='旧易码 迁移中间表';

CREATE TABLE `old_yima_code_index_e` (
  `md5code` varchar(32) NOT NULL DEFAULT '',
  `code` varchar(150) NOT NULL DEFAULT '',
  `url` varchar(500) DEFAULT NULL,
  PRIMARY KEY (`md5code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='旧易码 迁移中间表';

CREATE TABLE `old_yima_code_index_f` (
  `md5code` varchar(32) NOT NULL DEFAULT '',
  `code` varchar(150) NOT NULL DEFAULT '',
  `url` varchar(500) DEFAULT NULL,
  PRIMARY KEY (`md5code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='旧易码 迁移中间表';


ALTER TABLE `yima_0` ADD INDEX `factory_id_water_index` (`factory_id`, `water`);
ALTER TABLE `yima_1` ADD INDEX `factory_id_water_index` (`factory_id`, `water`);
ALTER TABLE `yima_2` ADD INDEX `factory_id_water_index` (`factory_id`, `water`);
ALTER TABLE `yima_3` ADD INDEX `factory_id_water_index` (`factory_id`, `water`);
ALTER TABLE `yima_4` ADD INDEX `factory_id_water_index` (`factory_id`, `water`);
ALTER TABLE `yima_5` ADD INDEX `factory_id_water_index` (`factory_id`, `water`);
ALTER TABLE `yima_6` ADD INDEX `factory_id_water_index` (`factory_id`, `water`);
ALTER TABLE `yima_7` ADD INDEX `factory_id_water_index` (`factory_id`, `water`);
ALTER TABLE `yima_8` ADD INDEX `factory_id_water_index` (`factory_id`, `water`);
ALTER TABLE `yima_9` ADD INDEX `factory_id_water_index` (`factory_id`, `water`);
ALTER TABLE `yima_10` ADD INDEX `factory_id_water_index` (`factory_id`, `water`);
ALTER TABLE `yima_11` ADD INDEX `factory_id_water_index` (`factory_id`, `water`);
ALTER TABLE `yima_12` ADD INDEX `factory_id_water_index` (`factory_id`, `water`);
ALTER TABLE `yima_13` ADD INDEX `factory_id_water_index` (`factory_id`, `water`);
ALTER TABLE `yima_14` ADD INDEX `factory_id_water_index` (`factory_id`, `water`);
ALTER TABLE `yima_15` ADD INDEX `factory_id_water_index` (`factory_id`, `water`);

ALTER TABLE `yima_0` ADD INDEX (`factory_product_qrcode_id`);
ALTER TABLE `yima_1` ADD INDEX (`factory_product_qrcode_id`);
ALTER TABLE `yima_2` ADD INDEX (`factory_product_qrcode_id`);
ALTER TABLE `yima_3` ADD INDEX (`factory_product_qrcode_id`);
ALTER TABLE `yima_4` ADD INDEX (`factory_product_qrcode_id`);
ALTER TABLE `yima_5` ADD INDEX (`factory_product_qrcode_id`);
ALTER TABLE `yima_6` ADD INDEX (`factory_product_qrcode_id`);
ALTER TABLE `yima_7` ADD INDEX (`factory_product_qrcode_id`);
ALTER TABLE `yima_8` ADD INDEX (`factory_product_qrcode_id`);
ALTER TABLE `yima_9` ADD INDEX (`factory_product_qrcode_id`);
ALTER TABLE `yima_10` ADD INDEX (`factory_product_qrcode_id`);
ALTER TABLE `yima_11` ADD INDEX (`factory_product_qrcode_id`);
ALTER TABLE `yima_12` ADD INDEX (`factory_product_qrcode_id`);
ALTER TABLE `yima_13` ADD INDEX (`factory_product_qrcode_id`);
ALTER TABLE `yima_14` ADD INDEX (`factory_product_qrcode_id`);
ALTER TABLE `yima_15` ADD INDEX (`factory_product_qrcode_id`);


# Dump of table yima_qr_category
# ------------------------------------------------------------

DROP TABLE IF EXISTS `yima_qr_category`;

CREATE TABLE `yima_qr_category` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `type` tinyint(1) NOT NULL,
  `title` varchar(500) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

LOCK TABLES `yima_qr_category` WRITE;

INSERT INTO `yima_qr_category` (`id`, `type`, `title`)
VALUES
  (1,1,'蓝色单排'),
  (2,1,'红色单排'),
  (3,1,'蓝色双排'),
  (4,1,'红色双排'),
  (5,1,'数据源'),
  (6,1,'自定义'),
  (7,2,'3cm*5cm'),
  (8,2,'4cm*6cm'),
  (9,2,'6cm*9cm');

UNLOCK TABLES;


# Dump of table yima_qr_category_index
# ------------------------------------------------------------

DROP TABLE IF EXISTS `yima_qr_category_index`;

CREATE TABLE `yima_qr_category_index` (
  `master_id` int(11) NOT NULL,
  `release_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

LOCK TABLES `yima_qr_category_index` WRITE;

INSERT INTO `yima_qr_category_index` (`master_id`, `release_id`)
VALUES
  (1,7),
  (1,8),
  (1,9),
  (2,7),
  (3,7),
  (4,7),
  (5,0),
  (6,0);

UNLOCK TABLES;

ALTER TABLE `wx_user_product` ADD `code` varchar(150) DEFAULT ''  AFTER `wx_factory_id`;


DROP table tmp;
CREATE table tmp as SELECT id FROM factory_excel_datas_0 WHERE md5code IN ( SELECT md5code FROM factory_excel_datas_0 WHERE factory_id > 0 AND  `code` IS NOT NULL GROUP BY md5code HAVING count(md5code) > 1 ) AND factory_id = 38;
DELETE FROM factory_excel_datas_0 WHERE id IN ( SELECT id FROM tmp);
DROP table tmp;

CREATE table tmp as SELECT id FROM factory_excel_datas_1 WHERE md5code IN ( SELECT md5code FROM factory_excel_datas_1 WHERE factory_id > 0 AND  `code` IS NOT NULL GROUP BY md5code HAVING count(md5code) > 1 ) AND factory_id = 38;
DELETE FROM factory_excel_datas_1 WHERE id IN ( SELECT id FROM tmp);
DROP table tmp;

CREATE table tmp as SELECT id FROM factory_excel_datas_2 WHERE md5code IN ( SELECT md5code FROM factory_excel_datas_2 WHERE factory_id > 0 AND  `code` IS NOT NULL GROUP BY md5code HAVING count(md5code) > 1 ) AND factory_id = 38;
DELETE FROM factory_excel_datas_2 WHERE id IN ( SELECT id FROM tmp);
DROP table tmp;

CREATE table tmp as SELECT id FROM factory_excel_datas_3 WHERE md5code IN ( SELECT md5code FROM factory_excel_datas_3 WHERE factory_id > 0 AND  `code` IS NOT NULL GROUP BY md5code HAVING count(md5code) > 1 ) AND factory_id = 38;
DELETE FROM factory_excel_datas_3 WHERE id IN ( SELECT id FROM tmp);
DROP table tmp;

CREATE table tmp as SELECT id FROM factory_excel_datas_4 WHERE md5code IN ( SELECT md5code FROM factory_excel_datas_4 WHERE factory_id > 0 AND  `code` IS NOT NULL GROUP BY md5code HAVING count(md5code) > 1 ) AND factory_id = 38;
DELETE FROM factory_excel_datas_4 WHERE id IN ( SELECT id FROM tmp);
DROP table tmp;

CREATE table tmp as SELECT id FROM factory_excel_datas_5 WHERE md5code IN ( SELECT md5code FROM factory_excel_datas_5 WHERE factory_id > 0 AND  `code` IS NOT NULL GROUP BY md5code HAVING count(md5code) > 1 ) AND factory_id = 38;
DELETE FROM factory_excel_datas_5 WHERE id IN ( SELECT id FROM tmp);
DROP table tmp;

CREATE table tmp as SELECT id FROM factory_excel_datas_6 WHERE md5code IN ( SELECT md5code FROM factory_excel_datas_6 WHERE factory_id > 0 AND  `code` IS NOT NULL GROUP BY md5code HAVING count(md5code) > 1 ) AND factory_id = 38;
DELETE FROM factory_excel_datas_6 WHERE id IN ( SELECT id FROM tmp);
DROP table tmp;

CREATE table tmp as SELECT id FROM factory_excel_datas_7 WHERE md5code IN ( SELECT md5code FROM factory_excel_datas_7 WHERE factory_id > 0 AND  `code` IS NOT NULL GROUP BY md5code HAVING count(md5code) > 1 ) AND factory_id = 38;
DELETE FROM factory_excel_datas_7 WHERE id IN ( SELECT id FROM tmp);
DROP table tmp;

CREATE table tmp as SELECT id FROM factory_excel_datas_8 WHERE md5code IN ( SELECT md5code FROM factory_excel_datas_8 WHERE factory_id > 0 AND  `code` IS NOT NULL GROUP BY md5code HAVING count(md5code) > 1 ) AND factory_id = 38;
DELETE FROM factory_excel_datas_8 WHERE id IN ( SELECT id FROM tmp);
DROP table tmp;

CREATE table tmp as SELECT id FROM factory_excel_datas_9 WHERE md5code IN ( SELECT md5code FROM factory_excel_datas_9 WHERE factory_id > 0 AND  `code` IS NOT NULL GROUP BY md5code HAVING count(md5code) > 1 ) AND factory_id = 38;
DELETE FROM factory_excel_datas_9 WHERE id IN ( SELECT id FROM tmp);
DROP table tmp;

CREATE table tmp as SELECT id FROM factory_excel_datas_a WHERE md5code IN ( SELECT md5code FROM factory_excel_datas_a WHERE factory_id > 0 AND  `code` IS NOT NULL GROUP BY md5code HAVING count(md5code) > 1 ) AND factory_id = 38;
DELETE FROM factory_excel_datas_a WHERE id IN ( SELECT id FROM tmp);
DROP table tmp;

CREATE table tmp as SELECT id FROM factory_excel_datas_b WHERE md5code IN ( SELECT md5code FROM factory_excel_datas_b WHERE factory_id > 0 AND  `code` IS NOT NULL GROUP BY md5code HAVING count(md5code) > 1 ) AND factory_id = 38;
DELETE FROM factory_excel_datas_b WHERE id IN ( SELECT id FROM tmp);
DROP table tmp;

CREATE table tmp as SELECT id FROM factory_excel_datas_c WHERE md5code IN ( SELECT md5code FROM factory_excel_datas_c WHERE factory_id > 0 AND  `code` IS NOT NULL GROUP BY md5code HAVING count(md5code) > 1 ) AND factory_id = 38;
DELETE FROM factory_excel_datas_c WHERE id IN ( SELECT id FROM tmp);
DROP table tmp;

CREATE table tmp as SELECT id FROM factory_excel_datas_d WHERE md5code IN ( SELECT md5code FROM factory_excel_datas_d WHERE factory_id > 0 AND  `code` IS NOT NULL GROUP BY md5code HAVING count(md5code) > 1 ) AND factory_id = 38;
DELETE FROM factory_excel_datas_d WHERE id IN ( SELECT id FROM tmp);
DROP table tmp;

CREATE table tmp as SELECT id FROM factory_excel_datas_e WHERE md5code IN ( SELECT md5code FROM factory_excel_datas_e WHERE factory_id > 0 AND  `code` IS NOT NULL GROUP BY md5code HAVING count(md5code) > 1 ) AND factory_id = 38;
DELETE FROM factory_excel_datas_e WHERE id IN ( SELECT id FROM tmp);
DROP table tmp;

CREATE table tmp as SELECT id FROM factory_excel_datas_f WHERE md5code IN ( SELECT md5code FROM factory_excel_datas_f WHERE factory_id > 0 AND  `code` IS NOT NULL GROUP BY md5code HAVING count(md5code) > 1 ) AND factory_id = 38;
DELETE FROM factory_excel_datas_f WHERE id IN ( SELECT id FROM tmp);
DROP table tmp;


