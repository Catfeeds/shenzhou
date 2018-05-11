CREATE TABLE factory_help_person_backup SELECT * FROM factory_help_person;
drop table if exists factory_helper;
/*==============================================================*/
/* Table: factory_helper         1                               */
/*==============================================================*/
create table factory_helper
(
   id                   int(11) not null auto_increment,
   factory_id           int(11) not null COMMENT '所属工厂id',
   name                 varchar(50) not null comment '技术支持人姓名',
   telephone            varchar(50) not null comment '技术支持电话',
   is_default           tinyint not null DEFAULT '0' COMMENT '0:不是,1:是',
   primary key (id),
	 KEY `factory_id` (`factory_id`)
)ENGINE=InnoDB DEFAULT CHARSET=utf8;
alter table factory_helper comment '厂家技术支持 (factory_help_person)';

INSERT INTO factory_helper (id,factory_id,`name`,telephone)
SELECT id,factory_id,`name`,telephone FROM factory_help_person_backup;