alter table worker_order_apply_accessory add admin_check_time int unsigned not null default 0 comment '客服审核时间,时间戳,单位:秒';
alter table worker_order_apply_accessory add factory_check_time int  unsigned not null default 0 comment '厂家审核时间,时间戳,单位:秒';
alter table worker_order_apply_accessory add worker_receive_time int unsigned not null default 0 comment '技工已收件时间,时间戳,单位:秒';
alter table worker_order_apply_accessory add factory_confirm_receive_time int unsigned not null default 0 comment '厂家确认收件时间,时间戳,单位:秒';
alter table worker_order_apply_accessory modify factory_send_time int unsigned not null default 0 comment '厂家发件时间,时间戳,单位:秒';
alter table worker_order_apply_accessory add stop_time int unsigned not null default 0 comment '配件单终止时间,时间戳,单位:秒';
alter table worker_order_apply_accessory add complete_time int unsigned not null default 0 comment '配件单完成时间,时间戳,单位:秒';

alter table worker_order_apply_cost add admin_check_time int unsigned not null default 0 comment '客服审核时间,时间戳,单位:秒';

alter table worker_order_apply_cost add factory_check_time int unsigned not null default 0 comment '厂家审核时间,时间戳,单位:秒';