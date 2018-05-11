/*********************************************************************************************************/
/****                     worker_order_out_worker_add_fee                                             ****/
/*********************************************************************************************************/
CREATE TABLE IF NOT EXISTS `worker_order_out_worker_add_fee` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `worker_order_id` int(11) unsigned NOT NULL COMMENT '订单id',
  `worker_id` int(11) unsigned NOT NULL COMMENT '技工id',
  `worker_order_product_id` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '工单厂品id',
  `is_add_fee` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否是加收费用：0 否；1 是；',
  `pay_type` tinyint(1) NOT NULL DEFAULT 0 COMMENT '支付类别：0 未支付；1 技工代微信用户支付通道支付；2 客服确认用户现金支付；3 技工确认现金支付；4 微信用户支付通道支付',
  `worker_repair_fee` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT '维修费/安装费（改前）',
  `worker_repair_fee_modify` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT '维修费/安装费（改后）',
  `accessory_out_fee` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT '配件费用（改前）',
  `accessory_out_fee_modify` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT '配件费用（改后）',
  `total_fee` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT '总费用（改后）',
  `total_fee_modify` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT '总费用（改后）',
  `create_time` int(10) NOT NULL DEFAULT 0 COMMENT '申请时间',
  `pay_time` int(10) NOT NULL DEFAULT 0 COMMENT '支付时间',
  `out_order_no` varchar(50) NOT NULL DEFAULT '' COMMENT '（外部订单、第三方）交易号,取自pay_platform_record',
  PRIMARY KEY (`id`),
  KEY `worker_order_id` (`worker_order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='保外单技工加收费用表';


ALTER TABLE `worker_order_ext_info` add `worker_repair_out_fee_reason` text  NOT NULL  COMMENT '工单总费用更改原因（暂时只有保外单生效）' after out_platform;
ALTER TABLE `worker_order_ext_info` add `accessory_out_fee_reason` text  NOT NULL  COMMENT '工单总配件单费用（不是返件费）更改原因' after worker_repair_out_fee_reason;

/*********************************************************************************************************/
/****                     worker_order_fee                                                            ****/
/*********************************************************************************************************/
/**
 * order_type，v3.0类型：1 厂家保内；2 厂家保外；3 厂家导单保内；4 厂家导单保外；5 易码保内；6易码保外；7 平台保外； 8微信保外；9 返修保内； 10返修保外；
 */
ALTER TABLE `worker_order_fee` CHANGE `accessory_out_fee` `accessory_out_fee` DECIMAL(10,2)  NOT NULL  DEFAULT '0.00'  COMMENT '保外单配件总费（改前）';
ALTER TABLE `worker_order_fee` ADD `accessory_out_fee_modify` DECIMAL(10,2)  NOT NULL  DEFAULT '0.00'  COMMENT '保外单配件总费（改后）'  AFTER `accessory_out_fee`;

update worker_order_fee set `accessory_out_fee_modify` = `accessory_out_fee` where worker_order_id in (select id from `worker_order` where worker_order_type in (2,4,6,8,10) and audit_time <= 1525446583);

insert into worker_order_out_worker_add_fee(worker_order_id,worker_id,is_add_fee,pay_type,worker_repair_fee,worker_repair_fee_modify,accessory_out_fee,accessory_out_fee_modify,total_fee,total_fee_modify) select worker_order_id, 0 as worker_id,0 as is_add_fee,0 as pay_type,worker_repair_fee,worker_repair_fee_modify,accessory_out_fee,accessory_out_fee_modify,(worker_repair_fee+accessory_out_fee) as total_fee,(worker_repair_fee_modify+accessory_out_fee_modify) as total_fee_modify from worker_order_fee where worker_order_id in (select id from `worker_order` where worker_order_type in (2,4,6,8,10) and audit_time <= 1525446583);

/**
 * worker_order_out_worker_add_fee.pay_type: 0 未支付；1 技工代微信用户支付通道支付；2 客服确认用户现金支付；3 技工确认现金支付；4 微信用户支付通道支付
 * worker_order_user_info..pay_type: 0=暂未支付 ，1=微信支付(微信用户支付通道支付)，2=现金支付(技工代微信用户支付通道支付,客服确认用户现金支付,技工确认现金支付)
 */
update worker_order_out_worker_add_fee woowaf set woowaf.pay_type=4,woowaf.pay_time =  (select wooui.pay_time from worker_order_user_info wooui where wooui.worker_order_id = woowaf.worker_order_id) where worker_order_id in (select worker_order_id from worker_order_user_info where is_user_pay = 1 AND  pay_type = 1) AND pay_time = 0;