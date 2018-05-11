CREATE TABLE worker_order_mess_backup SELECT * FROM worker_order_mess;
drop table if exists worker_order_message;

/*==============================================================*/
/* Table: worker_order_message                                  */
/*==============================================================*/
create table worker_order_message
(
   id                   int(11) not null auto_increment,
   worker_order_id      int(11) not null comment 'order_id 维修工单id',
   add_id               int DEFAULT 0 not null,
   add_type             tinyint(2) not null comment 'type 发起留言角色，#A客服，B厂家，C维修商，D客户 1客服，2厂家，3维修商，4客户',
   content              text not null comment '留言内容',
   create_time          int not null comment 'addtime 留言时间',
   receive_type         tinyint(2) not null comment 'mess_role # 接收角色:A客服，B厂家，C维修商，D客户 1 客服，2 厂家，3厂家子账号， 4 维修商， 5客户，6 经销商 ',
   primary key (id),
	 KEY `worker_order_id` (`worker_order_id`),
	 KEY `add_id` (`add_id`)
)ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='工单留言记录 worker_order_mess';
