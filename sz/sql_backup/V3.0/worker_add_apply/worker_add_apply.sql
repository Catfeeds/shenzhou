CREATE TABLE worker_add_apply_backup SELECT * FROM worker_add_apply;
drop table if exists worker_add_apply;

/*==============================================================*/
/* Table: worker_add_apply                                      */
/*==============================================================*/
create table worker_add_apply
(
   id                   int(11) not null auto_increment,
   worker_order_id      int(11) comment 'order_id 维修工单id',
   auditor_id           int(11) COMMENT '受理人id',
   apply_admin_id       int(11) not null COMMENT '申请人id',
   orno                 varchar(150) not null comment '工单号',
   area_ids             varchar(500) not null comment '开点地区IDs, 多个逗号分隔'''',''''',
   remark               text not null comment 'desc  申请点备注',
   create_time          int not null default 0 comment 'add_time 添加时间',
   `status`               smallint not null default 0 comment '0待处理 ，1已开点，2不能开点，3已取消 4正在处理',
   result_remark        varchar(500) not null comment 'result_desc  开点结果备注',
   worker_info          text not null comment '技工信息',
   is_valid             smallint not null default 0 comment 'comment_status 1:有效 2：无效 (开点结果是否有效)',
   result_evaluate      varchar(500) not null comment 'comment 开点评价 (客服评价 开点客服 结果的评估)',
   primary key (id),
	 KEY `worker_order_id` (`worker_order_id`),
	 KEY `auditor_id` (`auditor_id`),
	 KEY `apply_admin_id` (`apply_admin_id`)
)ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='维修商开点申请';

INSERT into worker_add_apply
(id,worker_order_id,auditor_id,apply_admin_id,orno,area_ids,remark,create_time,`status`,result_remark,worker_info,is_valid,result_evaluate) SELECT
 id,order_id,admin_id,apply_member_id,orno,area_ids,`desc`,add_time,`status`,result_desc,worker_info,comment_status,`comment` FROM worker_add_apply_backup;

#删除 worker_add_apply 等于 0
DELETE FROM worker_add_apply WHERE worker_order_id = 0;