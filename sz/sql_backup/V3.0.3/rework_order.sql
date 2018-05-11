ALTER TABLE `worker_order` ADD `parent_id` INT  NOT NULL  DEFAULT '0'  COMMENT '父工单ID'  AFTER `factory_check_order_id`;

ALTER TABLE `worker_order` CHANGE `worker_order_type` `worker_order_type` SMALLINT(6)  NOT NULL  DEFAULT '0'  COMMENT 'order_type，v3.0类型：1 厂家保内；2 厂家保外；3 厂家导单保内；4 厂家导单保外；5 易码保内；6易码保外；7 平台保外； 8微信保外；9 返修保内； 10返修保外；';

ALTER TABLE `worker_order` CHANGE `distribute_mode` `distribute_mode` TINYINT(4)  NOT NULL  DEFAULT '2'  COMMENT 'send_model，派发模式 0-首选 1-放入抢单池 2-指定维修商 3-母工单派单技工';

ALTER TABLE `worker_notification` CHANGE `type` `type` SMALLINT(6)  NOT NULL  COMMENT '类型：0=全部，101=业务通告，102活动消息；201=结算消息，202=提现消息，204=提现中，205=提现成功，206=提现失败，207=钱包余额调整，208=质保金调整；301=新工单，302=明天需上门，303=回访通过，305=安装预发件工单已签收提醒，306=回访不通过；307=工单返修；308=新返修单；401=待发件，402=已发件，403=待返件，404=其他，405=待厂家审核，406=客服/厂家审核不通过，407=厂家延时发件，408=厂家放弃旧配件返还，409=客服/厂家终止配件单；501=待厂家审核，502=审核通过，503=审核不通过；601=接单必读';
