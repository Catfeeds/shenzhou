#结构改动
alter table worker_order_complaint add is_prompt_complaint_to tinyint unsigned default 0 comment '是否已提示被投诉人 0-否 1-是';

alter table worker_order_complaint add complaint_create_type tinyint unsigned default 0 comment '创建投诉用户类型 1-客服；2-厂家 3-厂家子账号 4-技工 5-用户';

alter table worker_order_complaint add complaint_create_id int unsigned default 0 comment '创建投诉用户id';

#新字段,迁移数据
update worker_order_complaint set complaint_create_type=2,complaint_create_id=complaint_from_id where complaint_from_type=2;
update worker_order_complaint set complaint_create_type=3,complaint_create_id=complaint_from_id where complaint_from_type=3;

#投诉类型修改
alter table complaint_type add is_delete tinyint unsigned default 0 comment '是否已删除 0-否 1-是';

#1.修改类型

#客服  发起投诉修改
update complaint_type set name='服务沟通态度不好',sort=1 where name='服务态度不好' and user_type=1 and type=1;

update worker_order_complaint set cp_complaint_type_name='服务沟通态度不好' where cp_complaint_type_name='服务态度不好' and complaint_type_id=9;
update worker_order_complaint set cp_complaint_type_name_modify='服务沟通态度不好' where cp_complaint_type_name_modify='服务态度不好' and complaint_modify_type_id=9;

update complaint_type set name='服务流程不专业',sort=2 where name='业务流程不专业' and user_type=1 and type=1;
update worker_order_complaint set cp_complaint_type_name='服务流程不专业' where cp_complaint_type_name='业务流程不专业' and complaint_type_id=10;
update worker_order_complaint set cp_complaint_type_name_modify='服务流程不专业' where cp_complaint_type_name_modify='业务流程不专业' and complaint_modify_type_id=10;

insert into complaint_type(id,name,user_type,type,sort,score_deductions) values(41, '工单费用不合理',1,1,3,0) on duplicate key update name=values(name),user_type=values(user_type),type=values(type),sort=values(sort),score_deductions=values(score_deductions);

update complaint_type set name='回复信息不及时',sort=4 where name='反馈进度不及时' and user_type=1 and type=1;
update worker_order_complaint set cp_complaint_type_name='回复信息不及时' where cp_complaint_type_name='反馈进度不及时' and complaint_type_id=12;
update worker_order_complaint set cp_complaint_type_name_modify='回复信息不及时' where cp_complaint_type_name_modify='反馈进度不及时' and complaint_modify_type_id=12;

update complaint_type set name='不维护厂家形象',sort=5 where name='不维护厂家形象' and user_type=1 and type=1;

#客服  核实修改
update complaint_type set name='服务沟通态度不好',sort=1 where name='服务态度不好' and user_type=1 and type=2;
update worker_order_complaint set cp_complaint_type_name='服务沟通态度不好' where cp_complaint_type_name='服务态度不好' and complaint_type_id=25;
update worker_order_complaint set cp_complaint_type_name_modify='服务沟通态度不好' where cp_complaint_type_name_modify='服务态度不好' and complaint_modify_type_id=25;

update complaint_type set name='服务流程不专业',sort=2 where name='业务流程不专业' and user_type=1 and type=2;
update worker_order_complaint set cp_complaint_type_name='服务流程不专业' where cp_complaint_type_name='业务流程不专业' and complaint_type_id=26;
update worker_order_complaint set cp_complaint_type_name_modify='服务流程不专业' where cp_complaint_type_name_modify='业务流程不专业' and complaint_modify_type_id=26;

insert into complaint_type(id,name,user_type,type,sort,score_deductions) values(42, '工单费用不合理',1,2,3,0) on duplicate key update name=values(name),user_type=values(user_type),type=values(type),sort=values(sort),score_deductions=values(score_deductions);

update complaint_type set name='回复信息不及时',sort=4 where name='反馈进度不及时' and user_type=1 and type=2;
update worker_order_complaint set cp_complaint_type_name='回复信息不及时' where cp_complaint_type_name='反馈进度不及时' and complaint_type_id=28;
update worker_order_complaint set cp_complaint_type_name_modify='回复信息不及时' where cp_complaint_type_name_modify='反馈进度不及时' and complaint_modify_type_id=28;

update complaint_type set name='不维护厂家形象',sort=5 where name='不维护厂家形象' and user_type=1 and type=2;


#维修商发起投诉
#随意退单毁约 <= 单方取消工单
insert into complaint_type(id,name,user_type,type,sort,score_deductions) values(54, '随意退单毁约',4,1,1,0) on duplicate key update name=values(name),user_type=values(user_type),type=values(type),sort=values(sort),score_deductions=values(score_deductions);
update complaint_type set name='随意退单毁约',sort=1,score_deductions=30 where name='单方取消工单' and user_type=4 and type=2;
update worker_order_complaint set cp_complaint_type_name='随意退单毁约' where cp_complaint_type_name='单方取消工单' and complaint_type_id=13;
update worker_order_complaint set cp_complaint_type_name_modify='随意退单毁约' where cp_complaint_type_name_modify='单方取消工单' and complaint_modify_type_id=13;

#未按时上门时效慢 <= 二次催单/未按时上门
insert into complaint_type(id,name,user_type,type,sort,score_deductions) values(55, '未按时上门时效慢',4,2,2,20) on duplicate key update name=values(name),user_type=values(user_type),type=values(type),sort=values(sort),score_deductions=values(score_deductions);
update complaint_type set name='未按时上门时效慢',sort=2 where name='未按时上门' and type =1 and user_type=4;
update complaint_type set is_delete=1 where name='二次催单' and type =2 and user_type=4;
update complaint_type set is_delete=2 where name='未按时上门' and type =2 and user_type=4;
update worker_order_complaint set cp_complaint_type_name='未按时上门时效慢' where cp_complaint_type_name='未按时上门' and complaint_type_id=2;
update worker_order_complaint set cp_complaint_type_name='未按时上门时效慢' where cp_complaint_type_name='二次催单' and complaint_type_id=14;
update worker_order_complaint set cp_complaint_type_name='未按时上门时效慢' where cp_complaint_type_name='未按时上门' and complaint_type_id=15;
update worker_order_complaint set cp_complaint_type_name_modify='未按时上门时效慢' where cp_complaint_type_name_modify='未按时上门' and complaint_modify_type_id=2;
update worker_order_complaint set cp_complaint_type_name_modify='未按时上门时效慢' where cp_complaint_type_name_modify='二次催单' and complaint_modify_type_id=14;
update worker_order_complaint set cp_complaint_type_name_modify='未按时上门时效慢' where cp_complaint_type_name_modify='未按时上门' and complaint_modify_type_id=15;

#安装维修流程不规范	 <= 安装维修不规范/未清理施工场地
update complaint_type set name='安装维修流程不规范',sort=3 where name='未清理施工场地' and type =1 and user_type=4;
update complaint_type set name='安装维修流程不规范',sort=3,score_deductions=30 where name='安装维修不规范' and type =2 and user_type=4;
update worker_order_complaint set cp_complaint_type_name='安装维修流程不规范' where cp_complaint_type_name='未清理施工场地' and complaint_type_id=3;
update worker_order_complaint set cp_complaint_type_name='安装维修流程不规范' where cp_complaint_type_name='安装维修不规范' and complaint_type_id=16;
update worker_order_complaint set cp_complaint_type_name_modify='安装维修流程不规范' where cp_complaint_type_name_modify='未清理施工场地' and complaint_modify_type_id=3;
update worker_order_complaint set cp_complaint_type_name_modify='安装维修流程不规范' where cp_complaint_type_name_modify='安装维修不规范' and complaint_modify_type_id=16;

# 使用伪劣旧配件 <= 使用伪劣旧配件
update complaint_type set name='使用伪劣旧配件',sort=4 where name='使用劣质配件' and user_type=4 and type=1;
update complaint_type set sort=4,score_deductions=80 where name='使用伪劣旧配件' and user_type=4 and type=2;
update worker_order_complaint set cp_complaint_type_name='使用伪劣旧配件' where cp_complaint_type_name='使用劣质配件' and complaint_type_id=13;
update worker_order_complaint set cp_complaint_type_name_modify='使用伪劣旧配件' where cp_complaint_type_name_modify='使用劣质配件' and complaint_modify_type_id=13;

#服务乱收费 <= 乱收费用/乱收用户费用
update complaint_type set name='服务乱收费',sort=5 where name='乱收用户费用' and type =1 and user_type=4;
update complaint_type set name='服务乱收费',sort=5,score_deductions=80 where name='乱收费用' and type =2 and user_type=4;
update worker_order_complaint set cp_complaint_type_name='服务乱收费' where cp_complaint_type_name='乱收用户费用' and complaint_type_id=4;
update worker_order_complaint set cp_complaint_type_name='服务乱收费' where cp_complaint_type_name='乱收费用' and complaint_type_id=18;
update worker_order_complaint set cp_complaint_type_name_modify='服务乱收费' where cp_complaint_type_name_modify='乱收用户费用' and complaint_modify_type_id=4;
update worker_order_complaint set cp_complaint_type_name_modify='服务乱收费' where cp_complaint_type_name_modify='乱收费用' and complaint_modify_type_id=18;

# 服务态度恶劣	 <= 服务语言不规范/服务态度恶劣/服务态度不好
insert into complaint_type(id,name,user_type,type,sort,score_deductions) values(49, '服务态度恶劣',4,2,6,100) on duplicate key update name=values(name),user_type=values(user_type),type=values(type),sort=values(sort),score_deductions=values(score_deductions);
update complaint_type set name='服务态度恶劣',sort=6 where name='服务态度不好' and type =1 and user_type=4;
update complaint_type set is_delete=1 where name='服务语言不规范' and type =2 and user_type=4;
update complaint_type set is_delete=1 where id=20 and type =2 and user_type=4;
update worker_order_complaint set cp_complaint_type_name='服务态度恶劣' where cp_complaint_type_name='服务态度不好' and complaint_type_id=1;
update worker_order_complaint set cp_complaint_type_name='服务态度恶劣',complaint_type_id=49 where cp_complaint_type_name='服务语言不规范' and complaint_type_id=19;
update worker_order_complaint set cp_complaint_type_name='服务态度恶劣',complaint_type_id=49 where cp_complaint_type_name='服务态度恶劣' and complaint_type_id=20;
update worker_order_complaint set cp_complaint_type_name='服务态度恶劣' where cp_complaint_type_name='服务态度不好' and complaint_type_id=1;
update worker_order_complaint set cp_complaint_type_name_modify='服务态度恶劣' where cp_complaint_type_name_modify='服务态度不好' and complaint_modify_type_id=1;
update worker_order_complaint set cp_complaint_type_name_modify='服务态度恶劣',complaint_modify_type_id=49 where cp_complaint_type_name_modify='服务语言不规范' and complaint_modify_type_id=19;
update worker_order_complaint set cp_complaint_type_name_modify='服务态度恶劣',complaint_modify_type_id=49 where cp_complaint_type_name_modify='服务态度恶劣' and complaint_modify_type_id=20;

#维修技术差
insert into complaint_type(id,name,user_type,type,sort,score_deductions) values(50, '维修技术差',4,1,7,0) on duplicate key update name=values(name),user_type=values(user_type),type=values(type),sort=values(sort),score_deductions=values(score_deductions);
insert into complaint_type(id,name,user_type,type,sort,score_deductions) values(51, '维修技术差',4,2,7,50) on duplicate key update name=values(name),user_type=values(user_type),type=values(type),sort=values(sort),score_deductions=values(score_deductions);

#负面评价产品或品牌	 <= 抵毁企业形象/诋毁厂家产品
update complaint_type set name='负面评价产品或品牌',sort=8 where name='抵毁厂家产品' and type =1 and user_type=4;
update complaint_type set name='负面评价产品或品牌',sort=8,score_deductions=100 where name='抵毁企业形象' and type =2 and user_type=4;
update worker_order_complaint set cp_complaint_type_name='负面评价产品或品牌' where cp_complaint_type_name='抵毁厂家产品' and complaint_type_id=6;
update worker_order_complaint set cp_complaint_type_name='负面评价产品或品牌' where cp_complaint_type_name='抵毁企业形象' and complaint_type_id=21;
update worker_order_complaint set cp_complaint_type_name_modify='负面评价产品或品牌' where cp_complaint_type_name_modify='抵毁厂家产品' and complaint_modify_type_id=6;
update worker_order_complaint set cp_complaint_type_name_modify='负面评价产品或品牌' where cp_complaint_type_name_modify='抵毁企业形象' and complaint_modify_type_id=21;

#与用户发生严重冲突 <= 	与用户发生严重冲突/和用户发生冲突
update complaint_type set name='与用户发生严重冲突',sort=9 where name='和用户发生冲突' and type =1 and user_type=4;
update complaint_type set sort=9,score_deductions=100 where name='与用户发生严重冲突' and type =2 and user_type=4;
update worker_order_complaint set cp_complaint_type_name='与用户发生严重冲突' where cp_complaint_type_name='和用户发生冲突' and complaint_type_id=7;
update worker_order_complaint set cp_complaint_type_name_modify='与用户发生严重冲突' where cp_complaint_type_name_modify='和用户发生冲突' and complaint_modify_type_id=7;

#弄坏产品或用户财物
insert into complaint_type(id,name,user_type,type,sort,score_deductions) values(52, '弄坏产品或用户财物',4,1,10,0) on duplicate key update name=values(name),user_type=values(user_type),type=values(type),sort=values(sort),score_deductions=values(score_deductions);
insert into complaint_type(id,name,user_type,type,sort,score_deductions) values(53, '弄坏产品或用户财物',4,2,10,50) on duplicate key update name=values(name),user_type=values(user_type),type=values(type),sort=values(sort),score_deductions=values(score_deductions);

#服务不佳导致退换机 <= 服务不佳导致退换机
insert into complaint_type(id,name,user_type,type,sort,score_deductions) values(58, '服务不佳导致退换机',4,1,11,0) on duplicate key update name=values(name),user_type=values(user_type),type=values(type),sort=values(sort),score_deductions=values(score_deductions);
update complaint_type set sort=11,score_deductions=100 where name='服务不佳导致退换机' and user_type=4 and type=2;

#媒体网络恶意炒作 <= 乱报媒体网络
insert into complaint_type(id,name,user_type,type,sort,score_deductions) values(56, '媒体网络恶意炒作',4,1,12,0) on duplicate key update name=values(name),user_type=values(user_type),type=values(type),sort=values(sort),score_deductions=values(score_deductions);
update complaint_type set name='媒体网络恶意炒作',sort=12,score_deductions=1000 where name='乱报媒体网络' and user_type=4 and type=2;
update worker_order_complaint set cp_complaint_type_name='媒体网络恶意炒作' where cp_complaint_type_name='乱报媒体网络' and complaint_type_id=24;
update worker_order_complaint set cp_complaint_type_name_modify='媒体网络恶意炒作' where cp_complaint_type_name_modify='乱报媒体网络' and complaint_modify_type_id=24;

insert into complaint_type(id,name,user_type,type,sort,score_deductions) values(57, '其他',4,2,13,30) on duplicate key update name=values(name),user_type=values(user_type),type=values(type),sort=values(sort),score_deductions=values(score_deductions);
update complaint_type set sort=13 where name='其他' and user_type=4 and type=1;