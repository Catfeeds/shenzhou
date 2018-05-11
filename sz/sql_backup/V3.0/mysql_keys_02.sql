
ALTER TABLE `factory_repair_pay_record` DROP INDEX if exists `factory_id`;
ALTER TABLE `factory_repair_pay_record` ADD INDEX (`factory_id`);
ALTER TABLE `factory_repair_pay_record` DROP INDEX if exists `worker_order_id`;
ALTER TABLE `factory_repair_pay_record` ADD INDEX (`worker_order_id`);

/*
ALTER TABLE `worker_order_system_message` ADD INDEX (`data_id`);
ALTER TABLE `worker_order_system_message` ADD INDEX (`user_id`);
*/

ALTER TABLE `worker_repair_money_record` DROP INDEX if exists `factory_id`;
ALTER TABLE `worker_repair_money_record` ADD INDEX (`factory_id`);
ALTER TABLE `worker_repair_money_record` DROP INDEX if exists `worker_order_id`;
ALTER TABLE `worker_repair_money_record` ADD INDEX (`worker_order_id`);
ALTER TABLE `worker_repair_money_record` DROP INDEX if exists `worker_id`;
ALTER TABLE `worker_repair_money_record` ADD INDEX (`worker_id`);

ALTER TABLE `factory_fee_config_record` DROP INDEX if exists `admin_id`;
ALTER TABLE `factory_fee_config_record` ADD INDEX (`admin_id`);
ALTER TABLE `factory_fee_config_record` DROP INDEX if exists `factory_id`;
ALTER TABLE `factory_fee_config_record` ADD INDEX (`factory_id`);

ALTER TABLE `worker_money_record` DROP INDEX if exists `worker_id`;
ALTER TABLE `worker_money_record` ADD INDEX (`worker_id`);

ALTER TABLE `worker_order_apply_cost` DROP INDEX if exists `admin_id`;
ALTER TABLE `worker_order_apply_cost` ADD INDEX (`admin_id`);
ALTER TABLE `worker_order_apply_cost` DROP INDEX if exists `factory_id`;
ALTER TABLE `worker_order_apply_cost` ADD INDEX (`factory_id`);
ALTER TABLE `worker_order_apply_cost` DROP INDEX if exists `worker_order_product_id`;
ALTER TABLE `worker_order_apply_cost` ADD INDEX (`worker_order_product_id`);
ALTER TABLE `worker_order_apply_cost` DROP INDEX if exists `worker_order_id`;
ALTER TABLE `worker_order_apply_cost` ADD INDEX (`worker_order_id`);
ALTER TABLE `worker_order_apply_cost` DROP INDEX if exists `worker_id`;
ALTER TABLE `worker_order_apply_cost` ADD INDEX (`worker_id`);

ALTER TABLE `worker_order_apply_cost_record` DROP INDEX if exists `worker_order_apply_cost_id`;
ALTER TABLE `worker_order_apply_cost_record` ADD INDEX (`worker_order_apply_cost_id`);

ALTER TABLE `worker_order_apply_allowance` DROP INDEX if exists `admin_id`;
ALTER TABLE `worker_order_apply_allowance` ADD INDEX (`admin_id`);
ALTER TABLE `worker_order_apply_allowance` DROP INDEX if exists `auditor_id`;
ALTER TABLE `worker_order_apply_allowance` ADD INDEX (`auditor_id`);
ALTER TABLE `worker_order_apply_allowance` DROP INDEX if exists `worker_order_id`;
ALTER TABLE `worker_order_apply_allowance` ADD INDEX (`worker_order_id`);

ALTER TABLE `worker_add_apply` DROP INDEX if exists `apply_admin_id`;
ALTER TABLE `worker_add_apply` ADD INDEX (`apply_admin_id`);
ALTER TABLE `worker_add_apply` DROP INDEX if exists `auditor_id`;
ALTER TABLE `worker_add_apply` ADD INDEX (`auditor_id`);
ALTER TABLE `worker_add_apply` DROP INDEX if exists `worker_order_id`;
ALTER TABLE `worker_add_apply` ADD INDEX (`worker_order_id`);

ALTER TABLE `worker_order_complaint` DROP INDEX if exists `replier_id`;
ALTER TABLE `worker_order_complaint` ADD INDEX (`replier_id`);
ALTER TABLE `worker_order_complaint` DROP INDEX if exists `complaint_type_id`;
ALTER TABLE `worker_order_complaint` ADD INDEX (`complaint_type_id`);
ALTER TABLE `worker_order_complaint` DROP INDEX if exists `complaint_modify_type_id`;
ALTER TABLE `worker_order_complaint` ADD INDEX (`complaint_modify_type_id`);
ALTER TABLE `worker_order_complaint` DROP INDEX if exists `verifier_id`;
ALTER TABLE `worker_order_complaint` ADD INDEX (`verifier_id`);
ALTER TABLE `worker_order_complaint` DROP INDEX if exists `worker_order_id`;
ALTER TABLE `worker_order_complaint` ADD INDEX (`worker_order_id`);

ALTER TABLE `worker_order_system_message` DROP INDEX if exists `user_id`;
ALTER TABLE `worker_order_system_message` ADD INDEX (`user_id`);
ALTER TABLE `worker_order_system_message` DROP INDEX if exists `user_type`;
ALTER TABLE `worker_order_system_message` ADD INDEX (`user_type`);

ALTER TABLE `worker_withdrawcash_record` DROP INDEX if exists `city_id`;
ALTER TABLE `worker_withdrawcash_record` ADD INDEX (`city_id`);
ALTER TABLE `worker_withdrawcash_record` DROP INDEX if exists `province_id`;
ALTER TABLE `worker_withdrawcash_record` ADD INDEX (`province_id`);
ALTER TABLE `worker_withdrawcash_record` DROP INDEX if exists `worker_id`;
ALTER TABLE `worker_withdrawcash_record` ADD INDEX (`worker_id`);
ALTER TABLE `worker_withdrawcash_record` DROP INDEX if exists `withdrawcash_excel_id`;
ALTER TABLE `worker_withdrawcash_record` ADD INDEX (`withdrawcash_excel_id`);

ALTER TABLE `worker_money_adjust_record` DROP INDEX if exists `admin_id`;
ALTER TABLE `worker_money_adjust_record` ADD INDEX (`admin_id`);
ALTER TABLE `worker_money_adjust_record` DROP INDEX if exists `worker_id`;
ALTER TABLE `worker_money_adjust_record` ADD INDEX (`worker_id`);
ALTER TABLE `worker_money_adjust_record` DROP INDEX if exists `worker_order_id`;
ALTER TABLE `worker_money_adjust_record` ADD INDEX (`worker_order_id`);