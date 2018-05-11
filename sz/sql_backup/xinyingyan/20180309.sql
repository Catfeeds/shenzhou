<?php
/**
 * Created by PhpStorm.
 * User: seanzen
 * Date: 2018/3/9
 * Time: 15:24
 */

ALTER TABLE `worker_order_ext_info` ADD `out_trade_number` varchar(125) DEFAULT NULL  COMMENT '外部订单号' AFTER is_worker_show_factory;
ALTER TABLE `worker_order_ext_info` ADD `out_platform` TINYINT(2)  NOT NULL  DEFAULT 0  COMMENT '外部订单号来源：0无；1新迎燕平台；2新飞平台' AFTER out_trade_number;

ALTER TABLE `worker_order_user_info` CHANGE `phone` `phone` VARCHAR(64)  CHARACTER SET utf8  COLLATE utf8_general_ci  NOT NULL  DEFAULT ''  COMMENT '用户电话';
