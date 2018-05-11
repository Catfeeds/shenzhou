CREATE TABLE worker_order_revisit_backup SELECT * FROM worker_order_revisit;
drop table if exists worker_order_revisit_record;

/*==============================================================*/
/* Table: worker_order_revisit_record                           */
/*==============================================================*/
create table worker_order_revisit_record
(
   id                   int(11) not null auto_increment,
   worker_order_id      int(11) not null comment 'order_id 维修工单id',
   admin_id             int(11) not null DEFAULT 0,
   is_visit_ontime      tinyint not null comment 'is_work_apptime 是否按时上门（技工）',
   irregularities       varchar(255) not null comment 'behavior 技工违规行为',
   is_user_satisfy      tinyint not null default 0 comment 'is_work_satisfy 用户对技工是否满意',
   repair_quality_score smallint not null default 10 comment 'quality_fraction 质量分',
   not_visit_reason     text not null comment 'work_hs_reason 厂家或普通用户不需师傅上门维修原因 (客服操作)',
   return_remark        text not null comment 'work_remarks 回访内容（描述）',
   create_time          int not null default 0 comment 'add_time 添加时间',
   primary key (id),
	 KEY `worker_order_id` (`worker_order_id`),
	 KEY `admin_id` (`admin_id`)
)ENGINE=INNODB DEFAULT CHARSET=utf8 comment '工单回访记录 worker_order_revisit';

-- INSERT into worker_order_revisit_record
-- (id,worker_order_id,is_visit_ontime,irregularities,is_user_satisfy,repair_quality_score,not_visit_reason,return_remark,create_time) SELECT
-- id,worker_order_id,is_work_apptime,behavior,is_work_satisfy,quality_fraction,work_hs_reason,work_remarks,add_time
--  FROM worker_order_revisit_backup;


