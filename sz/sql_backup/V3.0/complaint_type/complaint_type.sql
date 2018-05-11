drop table if exists complaint_type;
/*==============================================================*/
/* Table: complaint_type                                        */
/*==============================================================*/
create table complaint_type
(
   id                   int not null auto_increment,
   `name`                 varchar(16) not null,
   type                 tinyint comment '1 厂家、2 客服、 3 普通用户、4 经销商',
   primary key (id)
)ENGINE=INNODB DEFAULT CHARSET=utf8 COMMENT '投诉单类型字典 from 工单投诉';

INSERT into complaint_type (`name`,type) VALUES ('服务态度不好',1);
INSERT into complaint_type (`name`,type) VALUES ('未按时上门',1);
INSERT into complaint_type (`name`,type) VALUES ('未清理施工场地',1);
INSERT into complaint_type (`name`,type) VALUES ('乱收用户费用',1);
INSERT into complaint_type (`name`,type) VALUES ('使用劣质配件',1);
INSERT into complaint_type (`name`,type) VALUES ('抵毁厂家产品',1);
INSERT into complaint_type (`name`,type) VALUES ('和用户发生冲突',1);
INSERT into complaint_type (`name`,type) VALUES ('单方取消工单',1);
INSERT into complaint_type (`name`,type) VALUES ('二次催单',1);
INSERT into complaint_type (`name`,type) VALUES ('安装维修不规范',1);
INSERT into complaint_type (`name`,type) VALUES ('服务语言不规范',1);
INSERT into complaint_type (`name`,type) VALUES ('抵毁企业形象',1);
INSERT into complaint_type (`name`,type) VALUES ('服务不佳导致退换机',1);
INSERT into complaint_type (`name`,type) VALUES ('乱报媒体网络',1);
INSERT into complaint_type (`name`,type) VALUES ('其它',1);

INSERT into complaint_type (`name`,type) VALUES ('服务态度不好',2);
INSERT into complaint_type (`name`,type) VALUES ('业务流程不专业',2);
INSERT into complaint_type (`name`,type) VALUES ('不维护厂家形象',2);
INSERT into complaint_type (`name`,type) VALUES ('反馈进度不及时',2);