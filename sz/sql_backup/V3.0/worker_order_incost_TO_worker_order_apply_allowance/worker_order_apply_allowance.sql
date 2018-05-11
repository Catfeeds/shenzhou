CREATE TABLE worker_order_incost_backup SELECT * FROM worker_order_incost;
drop table if exists worker_order_apply_allowance;

/*==============================================================*/
/* Table: worker_order_apply_allowance      1                    */
/*==============================================================*/
create table worker_order_apply_allowance
(
   id                   int(11) not null auto_increment,
   admin_id             int(11) not null comment 'add_id',
   auditor_id           int(11),
   worker_order_id      int(11) not null comment 'order_id 维修工单id',
   type                 int not null comment 'change_type 补贴类型 1 调整上门费， 2 调整维修费，  3 工单奖励',
   apply_fee            decimal(10,2) not null default 0.00 comment 'amount 申请费用额度',
   apply_remark         text not null comment 'reason 申请原因',
   create_time          int not null comment 'addtime 申请时间',
   is_check             tinyint not null default 0 comment '是否审核通过，0为否，1为是',
   check_time           int not null default 0 comment '审核时间',
   check_remark         text not null comment 'remarks 审核备注',
   apply_fee_modify     decimal(10,2) not null default 0.00 comment 'amount_modify 补贴费用修改（后）',
   modify_reason        text not null comment '补贴费用修改原因',
   primary key (id),
	 KEY `admin_id` (`admin_id`),
	 KEY `auditor_id` (`auditor_id`),
	 KEY `worker_order_id` (`worker_order_id`)
)ENGINE=InnoDB DEFAULT CHARSET=utf8;

alter table worker_order_apply_allowance COMMENT='工单内部费用 worker_order_incost';


INSERT into worker_order_apply_allowance(id,admin_id,worker_order_id,`type`,apply_fee,apply_remark,
create_time,is_check,check_remark,apply_fee_modify,modify_reason)
SELECT id,add_id,worker_order_id,change_type,amount,reason,addtime,is_check,remarks,amount_modify,modify_reason
FROM worker_order_incost_backup;