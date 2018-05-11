CREATE TABLE express_track_backup SELECT * FROM express_track;
drop table if exists express_tracking;

/*==============================================================*/
/* Table: express_tracking                                      */
/*==============================================================*/
create table express_tracking
(
   id                   int(11) not null auto_increment,
   express_number       varchar(50) not null comment 'number  快递单号',
   express_code         varchar(128) not null comment 'comcode 快递公司代号',
   data_id              int not null default 0 comment '物流信息所属的目的数据id,结合 type使用 ,配件单id (发件和反件),预发件id：工单id',
   state                tinyint(2) not null default -1 comment '运单状态：默认：-1，0在途中、1已揽收、2疑难、3已签收',
   content              text not null comment '运单详细描述',
   is_book              tinyint(2) not null default 0 comment '是否订阅成功，0否，1是  （快递平台是否主动返回运单信息）',
   type                 tinyint(2) not null comment '同一运单关联单号内标识，配件单：#发件SO，返件：SB;工单预发件安装单发件:WSO  1发件、2返件、3预发件' ,
   create_time          int not null comment 'addtime 添加时间',
   last_update_time     int not null default 0 comment 'last_uptime 最后更新时间',
   primary key (id),
   KEY `data_id` (`data_id`)
)ENGINE=INNODB DEFAULT CHARSET=utf8  comment ' 物流跟踪 express_track';

INSERT into express_tracking
(id,express_number,express_code,data_id,state,content,is_book,create_time,last_update_time) SELECT
id,number,comcode,acor_id,state,content,is_book,addtime,last_uptime FROM express_track_backup;

