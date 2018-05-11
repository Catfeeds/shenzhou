
#抽奖记录表用户收件信息字段类型更新
alter table `draw_record` modify column `address_json` text DEFAULT NULL COMMENT '用户收件信息';