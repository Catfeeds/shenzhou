
ALTER TABLE `wx_user` ADD  `bind_time` int DEFAULT NULL  COMMENT '绑定时间（即为用户在C端验证手机号码的时间）';

ALTER TABLE `wx_user` ADD  `province_id` int DEFAULT NULL  COMMENT '省份id';
ALTER TABLE `wx_user` ADD  `city_id` int DEFAULT NULL  COMMENT '城市id';
ALTER TABLE `wx_user` ADD  `area_id` int DEFAULT NULL  COMMENT '地区id';

#将已注册的用户记录中的创建时间同步给绑定时间
update wx_user set bind_time = add_time WHERE telephone != '' and  add_time != 0 and bind_time is not null ;

#更新dealer_info 表地区字段
ALTER TABLE `dealer_info` ADD  `province_id` int DEFAULT NULL  COMMENT '省份id';
ALTER TABLE `dealer_info` ADD  `city_id` int DEFAULT NULL  COMMENT '城市id';
ALTER TABLE `dealer_info` ADD  `area_id` int DEFAULT NULL  COMMENT '地区id';


#用户新增删除字段
alter table wx_user add is_delete int(11) not null default '0' comment "是否删除：0-未删除，时间戳-删除时间";