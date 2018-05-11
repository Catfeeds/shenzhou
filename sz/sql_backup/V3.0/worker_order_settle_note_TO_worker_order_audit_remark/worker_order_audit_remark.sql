CREATE TABLE worker_order_settle_note_backup SELECT * FROM worker_order_settle_note;
drop table if exists worker_order_audit_remark;
/*==============================================================*/
/* Table: worker_order_audit_remark   1                           */
/*==============================================================*/
create table worker_order_audit_remark
(
   id                   int(11) not null auto_increment,
   worker_order_id      int(11) not null comment 'order_id 维修工单id',
   admin_id             int(11) not null,
   content              text not null comment '注意事项（内容）',
   create_time          int not null comment 'add_time 添加时间',
   primary key (id)
)ENGINE=INNODB DEFAULT CHARSET=utf8 comment '工单结算备注 V1.0 worker_order_settle_note';

INSERT into worker_order_audit_remark(
id, worker_order_id, content, adName, create_time) SELECT
id, worker_order_id, content, adName, add_time FROM worker_order_settle_note_backup;