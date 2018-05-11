alter table `worker_order_user_info` add street_id int(11) not null DEFAULT 0 COMMENT '街道id';
alter table `worker_order_apply_accessory` add COLUMN receive_address_type tinyint(4) NOT NULL DEFAULT 0 COMMENT '0未知 1代表技工地址 2代表用户地址';
