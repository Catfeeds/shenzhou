CREATE TABLE `worker_group` (
  `id`                  int(11) NOT NULL AUTO_INCREMENT,
  `group_no`            int(11) DEFAULT NULL COMMENT '网点群号',
  `group_name`          varchar(32) DEFAULT NULL COMMENT '群名称',
  `owner_worker_id`     int(11) NOT NULL COMMENT '群主id',
  `worker_number`       int(11) DEFAULT 1 COMMENT '群总人数（包括群主）',
  `reputation_total`   int(11) DEFAULT 0 COMMENT '群信誉总分',
  `worker_order_number`        int(11) DEFAULT 0 COMMENT '群接单总数',
  `wait_appoint_order_number`  int(11) DEFAULT 0 COMMENT '待预约工单数',
  `wait_service_order_number`  int(11) DEFAULT 0 COMMENT '待服务工单数',
  `servicing_order_number`     int(11) DEFAULT 0 COMMENT '服务中工单数',
  `finish_order_number`        int(11) DEFAULT 0 COMMENT '已完结工单数',
  `status`              tinyint(1) DEFAULT 1 COMMENT '群状态，1-审核中，2-审核通过，3-审核不通过',
  `create_time`         int(11) NOT NULL COMMENT '创建时间',
  `is_delete`           int(11) NOT NULL DEFAULT 0 COMMENT '删除时间，0为正常，大于0为被删除',
  `audit_time`          int(11) DEFAULT NULL COMMENT '审核时间',
  `audit_remark`        varchar(128) NOT NULL COMMENT '审核备注',
  `create_reason`       varchar(128) DEFAULT NULL COMMENT '创建原因',
  `business_license`    varchar(128) DEFAULT NULL COMMENT '营业执照',
  `store_images`        text DEFAULT NULL COMMENT '门店照片',
  `cp_owner_nickname`   varchar(16) DEFAULT NULL COMMENT '群主昵称',
  `cp_owner_telephone`  varchar(16) DEFAULT NULL COMMENT '群主手机',
  PRIMARY KEY (`id`),
  UNIQUE KEY `group_no` (`group_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='技工群表';


CREATE TABLE `worker_group_relation` (
  `id`                  int(11) NOT NULL AUTO_INCREMENT,
  `worker_id`           int(11) NOT NULL,
  `worker_group_id`     int(11) NOT NULL,
  `user_type`           tinyint(1) NOT NULL COMMENT '技工身份，1-群主，2-群成员, 3-非群内成员',
  `status`              tinyint(1) NOT NULL COMMENT '审核状态，1-审核中，2-审核通过，3-审核不通过，4-7天未审核退回，5-被剔出群',
  `create_time`         int(11) NOT NULL COMMENT '创建时间',
  `is_delete`           int(11) NOT NULL DEFAULT 0 COMMENT '剔出群时间，0为正常，大于0为剔出群',
  `apply_time`          int(11) DEFAULT NULL COMMENT '申请加入群时间',
  `audit_time`          int(11) DEFAULT NULL COMMENT '审核时间',
  `audit_remark`        varchar(128) DEFAULT NULL COMMENT '审核备注',
  `wait_appoint_order_number`  int(11) DEFAULT 0 COMMENT '待预约工单数',
  `wait_service_order_number`  int(11) DEFAULT 0 COMMENT '待服务工单数',
  `servicing_order_number`     int(11) DEFAULT 0 COMMENT '服务中工单数',
  `finish_order_number`        int(11) DEFAULT 0 COMMENT '已完结工单数',
  `worker_proportion`          int(11) DEFAULT 0 COMMENT '技工分成比例',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='技工与群的关联表';

CREATE TABLE `worker_group_record` (
  `id`                   int(11) NOT NULL AUTO_INCREMENT,
  `worker_group_id`      int(11) NOT NULL,
  `record_operator_type` tinyint(1) NOT NULL COMMENT '操作人员类型，1-平台管理员，2-群主，3-普通技工，4=系统',
  `record_operator_id`   int(11) NOT NULL COMMENT '操作人员id',
  `operated_worker_id`   int(11) DEFAULT NULL COMMENT '被操作的技工id',
  `type`                 tinyint(2) NOT NULL COMMENT '记录类型，1-创建群，2-审核通过，3-审核不通过，4-申请加入群，5-允许加入群，6-不允许加入群，7-剔出群，8-7天未审核退回，9-修改群名称',
  `create_time`          int(11) NOT NULL COMMENT '创建时间',
  `content`              varchar(128) DEFAULT NULL COMMENT '具体操作',
  `remark`               varchar(128) DEFAULT NULL COMMENT '备注',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='技工群变动记录';

#技工表
alter table worker add COLUMN type tinyint(1) DEFAULT 1 COMMENT '技工类型，1-普通技工，2-群主，3-群成员';
alter table worker add COLUMN group_apply_status tinyint(1) DEFAULT 0 COMMENT '群关联操作类型，0-无操作，1-入群审核中，2-建群审核中';

#工单表
alter table worker_order add COLUMN worker_group_id int(11) DEFAULT NULL COMMENT '群id';
alter table worker_order add COLUMN children_worker_id int(11) DEFAULT NULL COMMENT '接单的子账号技工id';

#技工工单信誉信息表
alter table worker_order_reputation add COLUMN cp_worker_type tinyint(1) DEFAULT 1 COMMENT '技工类型，1-普通技工，2-群主，3-群成员';

#工单费用统计表
alter table worker_order_fee add COLUMN cp_worker_proportion int(11) DEFAULT 0 COMMENT '技工分成比例';

