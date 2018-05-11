/*  */

ALTER TABLE `worker_order` DROP INDEX if exists `factory_id`;
ALTER TABLE `worker_order` ADD INDEX (`factory_id`);
ALTER TABLE `worker_order` DROP INDEX if exists `checker_id`;
ALTER TABLE `worker_order` ADD INDEX (`checker_id`);
ALTER TABLE `worker_order` DROP INDEX if exists `distributor_id`;
ALTER TABLE `worker_order` ADD INDEX (`distributor_id`);
ALTER TABLE `worker_order` DROP INDEX if exists `auditor_id`;
ALTER TABLE `worker_order` ADD INDEX (`auditor_id`);
ALTER TABLE `worker_order` DROP INDEX if exists `returnee_id`;
ALTER TABLE `worker_order` ADD INDEX (`returnee_id`);
ALTER TABLE `worker_order` DROP INDEX if exists `worker_id`;
ALTER TABLE `worker_order` ADD INDEX (`worker_id`);


ALTER TABLE `worker_order_product` DROP INDEX if exists `product_brand_id`;
ALTER TABLE `worker_order_product` ADD INDEX (`product_brand_id`);
ALTER TABLE `worker_order_product` DROP INDEX if exists `product_category_id`;
ALTER TABLE `worker_order_product` ADD INDEX (`product_category_id`);
ALTER TABLE `worker_order_product` DROP INDEX if exists `product_standard_id`;
ALTER TABLE `worker_order_product` ADD INDEX (`product_standard_id`);
ALTER TABLE `worker_order_product` DROP INDEX if exists `worker_order_id`;
ALTER TABLE `worker_order_product` ADD INDEX (`worker_order_id`);
ALTER TABLE `worker_order_product` DROP INDEX if exists `fault_id`;
ALTER TABLE `worker_order_product` ADD INDEX (`fault_id`);
ALTER TABLE `worker_order_product` DROP INDEX if exists `product_id`;
ALTER TABLE `worker_order_product` ADD INDEX (`product_id`);

ALTER TABLE `worker_order_user_info` DROP INDEX if exists `area_id`;
ALTER TABLE `worker_order_user_info` ADD INDEX (`area_id`);
ALTER TABLE `worker_order_user_info` DROP INDEX if exists `city_id`;
ALTER TABLE `worker_order_user_info` ADD INDEX (`city_id`);
ALTER TABLE `worker_order_user_info` DROP INDEX if exists `province_id`;
ALTER TABLE `worker_order_user_info` ADD INDEX (`province_id`);
ALTER TABLE `worker_order_user_info` DROP INDEX if exists `wx_user_id`;
ALTER TABLE `worker_order_user_info` ADD INDEX (`wx_user_id`);

ALTER TABLE `worker_order_ext_info` DROP INDEX if exists `factory_helper_id`;
ALTER TABLE `worker_order_ext_info` ADD INDEX (`factory_helper_id`);

ALTER TABLE `factory_money_frozen` DROP INDEX if exists `worker_order_id`;
ALTER TABLE `factory_money_frozen` ADD INDEX (`worker_order_id`);
ALTER TABLE `factory_money_frozen` DROP INDEX if exists `factory_id`;
ALTER TABLE `factory_money_frozen` ADD INDEX (`factory_id`);

ALTER TABLE `factory_money_frozen_record` DROP INDEX if exists `factory_id`;
ALTER TABLE `factory_money_frozen_record` ADD INDEX (`factory_id`);

ALTER TABLE `worker_order_appoint_record` DROP INDEX if exists `worker_order_id`;
ALTER TABLE `worker_order_appoint_record` ADD INDEX (`worker_order_id`);
ALTER TABLE `worker_order_appoint_record` DROP INDEX if exists `worker_id`;
ALTER TABLE `worker_order_appoint_record` ADD INDEX (`worker_id`);

ALTER TABLE `worker_order_operation_record` DROP INDEX if exists `worker_order_product_id`;
ALTER TABLE `worker_order_operation_record` ADD INDEX (`worker_order_product_id`);
ALTER TABLE `worker_order_operation_record` DROP INDEX if exists `worker_order_id`;
ALTER TABLE `worker_order_operation_record` ADD INDEX (`worker_order_id`);

ALTER TABLE `worker_order_revisit_record` DROP INDEX if exists `admin_id`;
ALTER TABLE `worker_order_revisit_record` ADD INDEX (`admin_id`);
ALTER TABLE `worker_order_revisit_record` DROP INDEX if exists `worker_order_id`;
ALTER TABLE `worker_order_revisit_record` ADD INDEX (`worker_order_id`);