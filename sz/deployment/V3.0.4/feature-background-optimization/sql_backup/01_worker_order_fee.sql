/*********************************************************************************************************/
/****                     worker_order_fee                                                            ****/
/*********************************************************************************************************/

ALTER TABLE `worker_order_fee` CHANGE `accessory_return_fee` `worker_accessory_return_fee` DECIMAL(10,2)  NOT NULL  DEFAULT '0.00' COMMENT '技工配件返件总费（改前）';
ALTER TABLE `worker_order_fee` ADD `worker_accessory_return_fee_modify` DECIMAL(10,2)  NOT NULL  DEFAULT '0.00' COMMENT '技工配件返件总费（改后）'  AFTER `worker_accessory_return_fee`;

ALTER TABLE `worker_order_fee` ADD `factory_accessory_return_fee` DECIMAL(10,2)  NOT NULL  DEFAULT '0.00' COMMENT  '厂家配件返件总费（改前）'  AFTER `worker_accessory_return_fee_modify`;
ALTER TABLE `worker_order_fee` ADD `factory_accessory_return_fee_modify` DECIMAL(10,2)  NOT NULL  DEFAULT '0.00' COMMENT  '厂家配件返件总费（改后）'  AFTER `factory_accessory_return_fee`;


update worker_order_fee set worker_accessory_return_fee_modify =  worker_accessory_return_fee,factory_accessory_return_fee =  worker_accessory_return_fee,factory_accessory_return_fee_modify = worker_accessory_return_fee;


/*********************************************************************************************************/
/****                     worker_order_apply_accessory                                                ****/
/*********************************************************************************************************/
ALTER TABLE `worker_order_apply_accessory` CHANGE `worker_transport_fee` `worker_transport_fee` DECIMAL(10,2)  NOT NULL  DEFAULT '0.00' COMMENT '技工配件返件费（改前）';
ALTER TABLE `worker_order_apply_accessory` add `worker_transport_fee_modify` DECIMAL(10,2)  NOT NULL  DEFAULT '0.00' COMMENT  '技工配件返件费（改后）'  AFTER `worker_transport_fee`;
ALTER TABLE `worker_order_apply_accessory` ADD `worker_transport_fee_reason` text NOT NULL COMMENT  '修改技工配件返件费原因'  AFTER `worker_transport_fee_modify`;
ALTER TABLE `worker_order_apply_accessory` add `factory_transport_fee` DECIMAL(10,2)  NOT NULL  DEFAULT '0.00' COMMENT  '厂家配件返件费（改前）'  AFTER `worker_transport_fee_reason`;
ALTER TABLE `worker_order_apply_accessory` add `factory_transport_fee_modify` DECIMAL(10,2)  NOT NULL  DEFAULT '0.00' COMMENT  '厂家配件返件费（改后）'  AFTER `factory_transport_fee`;
ALTER TABLE `worker_order_apply_accessory` ADD `factory_transport_fee_reason` text NOT NULL COMMENT  '修改厂家配件返件费原因'  AFTER `factory_transport_fee_modify`;

update `worker_order_apply_accessory` set worker_transport_fee_modify = worker_transport_fee,factory_transport_fee = worker_transport_fee,factory_transport_fee_modify = worker_transport_fee;