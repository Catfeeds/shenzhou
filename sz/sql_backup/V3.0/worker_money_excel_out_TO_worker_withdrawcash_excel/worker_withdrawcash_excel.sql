CREATE TABLE worker_money_excel_out_backup SELECT * FROM worker_money_excel_out;
drop table if exists worker_withdrawcash_excel;

/*==============================================================*/
/* Table: worker_withdrawcash_excel   1                          */
/*==============================================================*/
create table worker_withdrawcash_excel
(
   id                   int not null,
   ids                  text not null COMMENT '提现工单的IDS',
   create_time          int not null comment 'add_time 导出excel记录创建时间',
   remark               varchar(255) not null comment 'desc',
   primary key (id)
)ENGINE=INNODB DEFAULT CHARSET=utf8 COMMENT '财务导出提现单的excel记录 worker_money_excel_out';

INSERT into worker_withdrawcash_excel(id,ids,create_time,remark)
SELECT id,ids,add_time,`desc` FROM worker_money_excel_out_backup;
