ALTER TABLE worker_order_ext_info add worker_group_set_tag int(3) NOT NULL DEFAULT 0 COMMENT '技工给订单打的标签, 1=已与群成员技工结算';


ALTER TABLE worker_order_apply_accessory add cs_apply_imgs text not null comment '客服申请配件厂家授权图片,json' AFTER `accessory_imgs`;