DROP TABLE if exists `product_category`;
create table if not exists product_category
(
   id                   int(11) not null auto_increment,
   name      			varchar(115) not null comment '分类名称',
   parent_id			int(11) not null comment '分类父级id',
   thumb 				varchar(255) default '' comment '分类图片',
   create_time 			int(10) not null default 0 comment '创建时间',
   primary key (id)
)ENGINE=INNODB DEFAULT CHARSET=utf8 comment='产品分类';
INSERT INTO `product_category`(`id`,`name`,`parent_id`,`thumb`,`create_time`) (SELECT `list_item_id`,`item_desc`,`item_parent`,`item_thumb`,unix_timestamp() as `create_time` FROM `cm_list_item` WHERE list_id = 12);

DROP TABLE if exists `area`;
create table if not exists `area`
(
   id                   int(11) not null auto_increment,
   name      			varchar(115) not null comment '地区名称',
   parent_id			int(11) not null comment '地区父级id',
   create_time 			int(10) not null default 0 comment '创建时间',
   primary key (id)
)ENGINE=INNODB DEFAULT CHARSET=utf8 comment='产品分类';
INSERT INTO `area`(`id`,`name`,`parent_id`,`create_time`) (SELECT `list_item_id`,`item_desc`,`item_parent`,unix_timestamp() as `create_time` FROM `cm_list_item` WHERE list_id = 13);


ALTER TABLE `worker` ADD COLUMN `jpush_alias` varchar(150) NOT NULL DEFAULT '' COMMENT 'jpush alias';

RENAME TABLE `factory_help_person` TO `factory_helper`;
ALTER TABLE `factory_helper` ADD is_default tinyint not null DEFAULT '0' COMMENT '0:不是,1:是';

create table pay_platform_record
(
   id                   int(11) not null auto_increment,
   platform_type        tinyint not null default 0 comment '支付平台，1 易联支付平台（PC收银台），2 微信支付平台',
   out_order_no         varchar(50) not null comment '（外部订单、第三方）交易号',
   money                decimal(10,2) not null default 0.00 comment '支付金额',
   pay_type             tinyint not null default 0 comment '支付类型 1 厂家资金充值',
   data_id              int not null default 0 comment '支付类型 外键id，pay_type为1，factory_money_change_record',
   user_id              int not null default 0 comment '操作人id',
   user_type            tinyint not null default 0 comment '操作人类型  1 平台客服；2 厂家客服；3 厂家子账号；4 技工；5 微信用户(普通用户)；6 微信用户(经销商)',
   status               smallint not null default 0 comment '支付状态，支付平台的支付状态',
   pay_ment             tinyint not null default 0 comment '支付平台的支付类型（一般回调接口返回或者选择支付平台的支付类型决定）',
   remark               text not null comment '发起支付的备注',
   create_time          int not null default 0 comment '发起支付的时间',
   pay_time             int not null default 0 comment '支付成功时间',
   syn_url              text not null comment '支付结果同步回调后跳转连接（神州系统控制跳转）',
   primary key (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='支付平台支付记录 V3.0：add';

/* ================================================20171107(优先级1)======================================================================== */


ALTER TABLE `sms_order_service_code` CHANGE `addtime` `create_ime` INT(11)  NOT NULL  COMMENT 'addtime,添加时间';


/* ================================================20171207(优先级1)======================================================================== */
ALTER TABLE `factory` ADD `can_read_worker_info` tinyint  NOT NULL  DEFAULT 0  COMMENT '是否允许查看 工单的技工信息 默认0（不允许）；1允许'  AFTER `group_id`;
ALTER TABLE `factory` ADD `money_not_enouth` DECIMAL(10,2)  NOT NULL  DEFAULT '1000.00'  COMMENT '可下单余额预警额度'  AFTER `can_read_worker_info`;
ALTER TABLE `factory` ADD `auto_settlement_days` tinyint(2)  NOT NULL DEFAULT 5  COMMENT '厂家待财务审核的工单超过N天进入自动结算，单位/天'  AFTER `money_not_enouth`;


/* ================================================数据更新======================================================================== */
update worker_withdrawcash_record set other_bank_name = '' where bank_id != 659004728;
update worker set other_bank_name = '' where bank_id = 659004728;
update admin_config set `type` = 2 where `name` = 'app_start_page';

CREATE TABLE `message_statistic` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `data_type` tinyint(3) unsigned NOT NULL DEFAULT 0 COMMENT '数据类型',
  `data_id` int(10) unsigned NOT NULL DEFAULT 0 COMMENT '数据类型对应数据主键id',
  `times` smallint(6) unsigned NOT NULL COMMENT '发送次数',
  PRIMARY KEY (`id`),
  KEY `data_id` (`data_id`,`data_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='消息推送次数记录';


CREATE TABLE `queue_failed_jobs` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `queue_type` varchar(128) NOT NULL COMMENT '队列类型',
  `context` text NOT NULL COMMENT '上下文',
  `exception` text NOT NULL COMMENT '异常信息',
  `create_at` int NOT NULL COMMENT '创建时间',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='队列失败任务表';

CREATE TABLE `short_url` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(12) DEFAULT NULL COMMENT '短链接编码',
  `link` varchar(255) NOT NULL COMMENT '地址',
  `create_time` int(11) NOT NULL COMMENT '创建时间',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `short_url` (`id`, `code`, `link`, `create_time`) VALUES ('40', 'mqxWE8', 'http://www.shenzhoulianbao.com/app.html', '1515383014');

/* only one */
UPDATE `shenzhou`.`worker_order_operation_record` SET `shenzhou`.`worker_order_operation_record`.`see_auth` = '1' WHERE (`operation_type`='1011' and see_auth != 1);

update worker_money_record set `type` = 5 where data_id in (select id from worker_order where worker_order_type not in (1,3,5)) and type = 1;
/* only one */