  CREATE TABLE worker_contact_record_backup SELECT * FROM worker_contact_record;
	drop table if exists worker_contact_record;
	/*==============================================================*/
	/* Table: worker_contact_record      1                           */
	/*==============================================================*/
	create table worker_contact_record
	(
	   id                   int(11) not null auto_increment,
	   admin_id             int(11) not null,
	   worker_id            int(11) not null comment 'worker_id 技工id',
	   worker_order_id      int(11) comment 'order_id 维修工单id',
	   contact_object       int not null comment 'object_type 0其他，1维修商，2零售商，3零售商带维修商，4商家，5批发商，6批发商带维修商',
	   contact_object_other varchar(32) comment '联系对象的其他信息',
	   contact_method       tinyint not null comment '联系方式 1电话 2微信 3QQ, 4短信',
	   contact_type         tinyint not null comment '联系类型 1派单咨询，2例行联系，3维修报价，4技术咨询，5代找网点，6其他',
	   contact_result       tinyint not null comment '联系结果 1可以，2不可以，3其他',
	   contact_report       tinyint not null comment 'contact_gu 客服评估 1可以合作，2考虑合作，3不再考虑，4其他',
	   contact_remark       text not null comment 'contact_desc  联系备注',
	   create_time          int not null default 0 comment '联系时间',
	   primary key (id),
		 KEY `admin_id` (`admin_id`),
		 key `worker_id` (`worker_id`),
		 KEY `worker_order_id` (`worker_order_id`)
	)ENGINE=InnoDB DEFAULT CHARSET=utf8 comment '技工联系记录 大多数据应该使用tinyint类型保存,不应该使用文字保存';

INSERT into worker_contact_record(
id, admin_id, worker_id, contact_remark, create_time) SELECT
id, admin_id, worker_id, contact_desc, add_time FROM worker_contact_record_backup;