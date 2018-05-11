CREATE TABLE sms_order_service_code_backup SELECT * FROM sms_order_service_code;
/*==============================================================*/
/* Table: sms_order_service_code     1                           */
/*==============================================================*/
	drop table if exists sms_order_service_code;
create table sms_order_service_code
(
   worker_order_id      int(11) not null comment 'order_id 维修工单id',
   level_a              char(11) comment 'A',
   level_b              char(11) comment 'B',
   level_c              char(11) comment 'C',
   create_time          int comment 'addtime 添加时间'
)ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='工单服务质量（客户）短信';

INSERT INTO sms_order_service_code(worker_order_id,level_a,level_b,level_c,create_time)
SELECT order_id,level_a,level_b,level_c,addtime FROM sms_order_service_code_backup;
