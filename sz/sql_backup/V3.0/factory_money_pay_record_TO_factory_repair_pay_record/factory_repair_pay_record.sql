CREATE TABLE factory_money_pay_record_backup SELECT * FROM factory_money_pay_record;
drop table if exists factory_repair_pay_record;
/*==============================================================*/
/* Table: factory_repair_pay_record     1                         */
/*==============================================================*/
create table factory_repair_pay_record
(
   id                   int(11) not null auto_increment,
   factory_id           int(11) not null,
   worker_order_id      int(11) not null comment 'order_id 维修工单id',
   orno                 varchar(255) not null comment '工单号',
   pay_money            decimal(10,2) not null default 0.00 comment '支付金额',
   last_money           decimal(10,2) not null comment '厂家余额',
   create_time          int not null default 0 comment 'add_time 添加时间',
   primary key (id),
	 KEY `factory_id` (`factory_id`),
	 key `worker_order_id_orno_index` (`worker_order_id`,`orno`)
)ENGINE=InnoDB DEFAULT CHARSET=utf8;

alter table factory_repair_pay_record comment '厂家 支付　平台 factory_money_pay_record';

INSERT into factory_repair_pay_record(id,factory_id,worker_order_id,orno,pay_money,last_money,create_time)
SELECT id,factory_id,order_id,orno,pay_money,last_money,add_time FROM factory_money_pay_record_backup;