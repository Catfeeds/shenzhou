

create table short_url
(
   id                   int(11) not null auto_increment,
   code                 varchar(12) comment '短链接编码',
   link                 varchar(255) not null comment '地址',
   create_time          int(11) not null comment '创建时间',
   primary key (id)
);
alter table prize_item comment '短链接表';