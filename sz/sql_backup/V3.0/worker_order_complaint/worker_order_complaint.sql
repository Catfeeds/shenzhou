CREATE TABLE worker_order_complaint_backup SELECT * FROM worker_order_complaint;
drop table if exists worker_order_complaint;

/*==============================================================*/
/* Table: worker_order_complaint                                */
/*==============================================================*/
create table worker_order_complaint
(
   id                   int(11) not null auto_increment,
   complaint_modify_type_id int not null,
   complaint_type_id    int not null,
   worker_order_id      int(11) not null comment 'order_id 维修工单id',
   complaint_number     varchar(30) not null comment 'copltno  投诉单号',
   cp_complaint_type_name varchar(16) not null comment '投诉类型',
   complaint_from_id    int not null comment 'complaint_from  投诉人ID',
   complaint_from_type  tinyint not null comment '投诉人类别，# F：厂家，W技工，S：客服 1：厂家，2技工，3：客服',
   complaint_to_id      int not null comment 'complaint_to 被投诉人的ID',
   complaint_to_type    tinyint not null comment '投诉对象类型 #（S：客服，：W：技工，F：厂家） 1：厂家，2技工，3：客服',
   content              text not null comment '投诉具体内容',
   create_time          int not null comment '投诉提交时间',
   contact_name         varchar(50) not null comment 'link_name 联系人名称 (处理结果,通知该人的联系信息)',
   contact_tell         varchar(50) not null comment 'link_tell 联系人电话',
   reply_result         text not null comment '处理(回复)',
   reply_time           int not null comment '处理（回复）时间',
   is_true              tinyint not null default 0 comment 'is_check 投诉是否属实，0否，1是 (核实处理结果,,客诉专员)',
   cp_complaint_type_name_modify varchar(20) not null comment 'comfirm_type 处理结果,核实后的投诉类型 ( complaint_to_type )',
   response_type        varchar(20) not null comment '责任方：1维修商，2客服，3下单人，4用户',
   is_saticsfy          tinyint not null comment '投诉人是否满意，1是，2否',
   comfirm_remark       text not null comment 'remark  客诉主管备注',
   worker_deductions    tinyint not null default 0 comment 'deduction 技工扣分',
   primary key (id),
	 key `worker_order_id` (`worker_order_id`)
)ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='工单投诉';

INSERT into worker_order_complaint(id, worker_order_id, complaint_number, `cp_complaint_type_name`, complaint_from_id, complaint_from_type,
complaint_to_id, complaint_to_type, content, create_time, contact_name, contact_tell, reply_result, reply_time, is_true,
cp_complaint_type_name_modify, response_type, is_saticsfy, comfirm_remark, worker_deductions) SELECT
id, worker_order_id, copltno, comfirm_type, complaint_from, complaint_from_type, complaint_to, complaint_to_type, content, addtime, link_name,
link_tell, reply_result, reply_time, is_check, comfirm_type, response_type, is_saticsfy, remark, deduction FROM worker_order_complaint_backup;