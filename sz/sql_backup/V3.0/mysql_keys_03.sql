ALTER TABLE `worker_order` ADD UNIQUE INDEX (`orno`);

ALTER TABLE `worker_order` ADD INDEX (`worker_order_status`, `cancel_status`);

ALTER TABLE `worker_order_product` ADD INDEX (`yima_code`);

ALTER TABLE `worker_order_operation_record` ADD INDEX (`operation_type`, `operator_id`);

ALTER TABLE `worker_order_user_info` ADD INDEX (`phone`);

ALTER TABLE `worker_order_apply_accessory` ADD INDEX (`factory_id`);

ALTER TABLE `worker_order_apply_accessory` ADD INDEX (`worker_order_id`);

ALTER TABLE `worker_order_apply_accessory` ADD INDEX (`worker_id`);

ALTER TABLE `worker_order_apply_accessory` ADD INDEX (`worker_order_product_id`);

ALTER TABLE `worker_order_apply_accessory` ADD INDEX (`accessory_number`);

ALTER TABLE `worker_order_apply_accessory` ADD INDEX (`accessory_status`, `cancel_status`, `is_giveup_return`);

ALTER TABLE `worker_order_apply_accessory_item` ADD INDEX (`accessory_order_id`);

ALTER TABLE `worker_order_apply_accessory_item` ADD INDEX (`worker_id`);

ALTER TABLE `worker_order_apply_accessory_item` ADD INDEX (`accessory_id`);

ALTER TABLE `worker_order_apply_accessory_record` ADD INDEX (`accessory_order_id`);

ALTER TABLE `worker_order_apply_cost` ADD INDEX (`status`);

ALTER TABLE `worker_order_apply_allowance` ADD INDEX (`create_time`);

ALTER TABLE `worker_add_apply` ADD INDEX (`status`);

ALTER TABLE `area` ADD INDEX (`parent_id`);

ALTER TABLE `admin` ADD INDEX (`tell`);

ALTER TABLE `dealer_bind_products` ADD INDEX (`dealer_id`);

ALTER TABLE `express_tracking` ADD INDEX (`type`, `data_id`);

ALTER TABLE `factory_admin` ADD INDEX (`tell`);

ALTER TABLE `factory_category_service_cost` ADD INDEX (`factory_id`);

ALTER TABLE `factory_excel` ADD INDEX (`factory_id`);

ALTER TABLE `factory_helper` ADD INDEX (`factory_id`);

ALTER TABLE `factory_money_change_record` ADD INDEX (`change_type`, `status`, `create_time`);

ALTER TABLE `factory_money_frozen_record` ADD INDEX (`worker_order_id`);

ALTER TABLE `factory_product` ADD INDEX (`factory_id`);

ALTER TABLE `factory_product` ADD INDEX (`product_category`, `product_guige`, `product_brand`);

ALTER TABLE `factory_product_attr` ADD INDEX (`factory_id`);

ALTER TABLE `factory_product_attr` ADD INDEX (`product_cat_id`);

ALTER TABLE `factory_product_brand` ADD INDEX (`factory_id`, `product_cat_id`);

ALTER TABLE `factory_product_qrcode` ADD INDEX (`product_id`);

ALTER TABLE `factory_product_qrcode` ADD INDEX (`factory_id`);

ALTER TABLE `factory_product_qrcode` ADD INDEX (`qr_first_int`, `qr_last_int`);

ALTER TABLE `factory_product_white_list` ADD INDEX (`factory_id`);

ALTER TABLE `factory_product_white_list` ADD INDEX (`user_name`);

ALTER TABLE `factory_qr_code_apply` ADD INDEX (`factory_id`);

ALTER TABLE `pay_password_logs` ADD INDEX (`member_id`, `add_time`, `result`);

ALTER TABLE `product_category` ADD INDEX (`parent_id`);

ALTER TABLE `product_fault` ADD INDEX (`product_id`);

ALTER TABLE `product_fault_label` ADD INDEX (`product_id`);

ALTER TABLE `product_fault_price` ADD INDEX (`product_id`);

ALTER TABLE `product_fault_price` ADD INDEX (`fault_id`);

ALTER TABLE `product_fault_price` ADD INDEX (`standard_id`);

ALTER TABLE `worker_addressee` ADD INDEX (`worker_id`);

ALTER TABLE `worker_contact_record` ADD INDEX (`worker_order_id`);

ALTER TABLE `worker_feedback` ADD INDEX (`worker_id`);


ALTER TABLE `worker_notification` ADD INDEX (`worker_id`, `type`);

ALTER TABLE `worker_order_audit_remark` ADD INDEX (`worker_order_id`);

ALTER TABLE `worker_order_message` ADD INDEX (`worker_order_id`);

ALTER TABLE `worker_order_quality` ADD INDEX (`worker_order_id`);

ALTER TABLE `worker_order_quality` ADD INDEX (`worker_id`);

ALTER TABLE `worker_order_reputation` ADD INDEX (`worker_order_id`);

ALTER TABLE `worker_quality_money_record` ADD INDEX (`worker_id`);

ALTER TABLE `wx_user` ADD INDEX (`telephone`);

ALTER TABLE `wx_user_product` ADD INDEX (`wx_user_id`);



