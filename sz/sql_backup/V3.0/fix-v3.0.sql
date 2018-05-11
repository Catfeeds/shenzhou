/* ================================================20180111======================================================================== */
ALTER TABLE `worker_order_appoint_record` DROP INDEX if exists `appoint_time`;
ALTER TABLE `worker_order_appoint_record` ADD INDEX (`appoint_time`);

ALTER TABLE `worker_order_system_message` DROP INDEX if exists `user_type`;
ALTER TABLE `worker_order_system_message` DROP INDEX if exists `user_id`;
ALTER TABLE `worker_order_system_message` ADD INDEX (`user_type`, `user_id`);

ALTER TABLE `worker_order_user_info` CHANGE `phone` `phone` VARCHAR(35)  CHARACTER SET utf8  COLLATE utf8_general_ci  NOT NULL  DEFAULT ''  COMMENT '用户电话';

/* ================================================20180112======================================================================== */
update factory_money_change_record  set change_money = -change_money where id in (select a.id from (select * from factory_money_change_record where change_type = 4 and change_money <> 0 and create_time > 1515407400 and money - change_money = last_money) a left join worker_order b on a.out_trade_number = b.orno left join worker_order_fee c on b.id = c.worker_order_id where a.change_money = c.factory_total_fee_modify);

/* ================================================20180116======================================================================== */
DELETE FROM `factory_money_frozen_record` WHERE 1;
ALTER TABLE `factory_money_frozen_record` ADD if not exists `factory_frozen_money` DECIMAL(10,2)  NOT NULL  DEFAULT '0.00'  COMMENT '变动前总厂家冻结金' AFTER `type`;
ALTER TABLE `factory_money_frozen_record` ADD if not exists `last_factory_frozen_money` DECIMAL(10,2)  NOT NULL  DEFAULT '0.00'  COMMENT '变动后总厂家冻结金' AFTER `last_frozen_money`;

update worker_order_ext_info set service_evaluate = 'A' where worker_order_id in (select b.id from worker_order_operation_record a right join worker_order b on a.worker_order_id = b.id where a.operation_type = '1012' and a.create_time >= 1515407400 and b.worker_order_status > 13) and (service_evaluate is null or service_evaluate = '');

ALTER TABLE `worker_order` ADD INDEX (`cp_worker_phone`);

/* ================================================20180118======================================================================== */
update worker_order set factory_check_order_type = 2 where origin_type = 2 and factory_check_order_type = 1 and create_time <= 1515493800;

/* ================================================20180124======================================================================== */
update worker_order_reputation set quality_standard_fraction = 30,repair_nums_fraction = 10 where worker_order_id in (select id from worker_order where return_time >= 1515493800 and worker_order_status >= 13);

/* ================================================20180125======================================================================== */
ALTER TABLE `wx_user` CHANGE `area_id` `area_id` INT(11)  NOT NULL  DEFAULT '0'  COMMENT '地区id';
ALTER TABLE `wx_user` CHANGE `city_id` `city_id` INT(11)  NOT NULL  DEFAULT '0'  COMMENT '城市id';
ALTER TABLE `wx_user` CHANGE `province_id` `province_id` INT(11)  NOT NULL  DEFAULT '0'  COMMENT '省份id';

/* ================================================20180201======================================================================== */
ALTER TABLE `wx_user` CHANGE `bind_time` `bind_time` INT(11)  NOT NULL DEFAULT 0  COMMENT '绑定时间（即为用户在C端验证手机号码的时间）';

update factory_money_change_record  set change_money = -change_money,last_money = -last_money where id in (select id from (select c.id from worker_order a inner join worker_order_fee b on a.id = b.worker_order_id inner join (select * from factory_money_change_record where change_type = 4) c on a.orno = c.out_trade_number where a.worker_order_status = 18 and b.factory_total_fee_modify = c.change_money and c.money  = c.change_money -c.last_money and c.create_time >= 1515493800) demo);