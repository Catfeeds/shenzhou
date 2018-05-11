create table worker_order_webcall (

`id` int unsigned not null auto_increment,
`worker_order_id` int unsigned not null default 0 comment '工单id 取自worker_order的id',
`call_user_type` tinyint unsigned not null default 0 comment '呼出用户类型 1-客服',
`call_user_id` int unsigned not null default 0 comment '呼出用户id',
`called_user_type` tinyint unsigned not null default 0 comment '呼入用户类型 1-技工 2-用户 3-技术支持人',
`called_user_id` int unsigned not null default 0 comment '呼入用户id',
`create_time` int unsigned not null default 0 comment '创建时间',
`hangup_time` int unsigned not null default 0 comment '结束时间',
`begin` int unsigned not null default 0 comment '接通开始时间',
`end` int unsigned not null default 0 comment '结束对话时间',
`status` tinyint unsigned not null default 0 comment '状态 0-待连接 1-呼叫响铃 2-被呼叫响铃 3-接通 4-结束通话',
`record_file` varchar(200) not null default '' comment '通话录音文件名',
`agent` int unsigned not null default 0 comment '坐席号',

primary key (id)

) engine=innodb default charset=utf8 comment='电信云通话记录表';

create table worker_order_webcall_fail(
`id` int unsigned not null auto_increment,
`webcall_id` int unsigned not null default 0 comment '电信云通话记录表',
`state` varchar(32) not null default '' comment '电信云返回接听状态 dealing（已接）,notDeal（振铃未接听）,leak（ivr放弃）,queueLeak（排队放弃）,blackList（黑名单）,voicemail（留言）',
`raw_data` text not null default '' comment '元数据',
`create_time` int not null default 0 comment '创建时间',

primary key (id)

) engine=innodb default charset=utf8 comment='电信云通话失败记录表';

alter table admin add agent int unsigned not null default 0 comment '电信云坐席号';