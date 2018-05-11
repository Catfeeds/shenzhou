==================================================================================================================================================================
#  mysql  服务器 配置
## group_concat_max_len = 99999999999

#  数据表结构及数据更新   
## old_new_2017-14.sql

# 1, admin.php/factoryexcel?co=cocococo

# 2, admin.php/factoryima?co=cocococo&model_name
## all.sql
## all2.sql

#  3, admin.php/factoryexcelyima
## factory_excel_update_all_2.sql
## factory_excel_update_all.sql
## factory_excel_delete.sql

## 迁移数据统计  count.sql

#  4, admin.php/yimaareaids
## yimaareaids_4.sql
## dealer.sql
## factory.sql
## data_last_cache.sql

## 修复易码激活日期问题 admin.php/yima/factory/repair

#  Commom/Conf/config.php
### 'WORKER_CODE_KEY' => 'SHENZHOU@3NCTO',
### 'WORKER_CODE_MIN_LENGTH' => 10,
### 'YIMA_CRYPT_KEY' => 'shenZhou@3NcTo',
### 'YIMA_CRYPT_MIN_LENGTH' => 11,

==================================================================================================================================================================
#  B端

## main,js
### var C_APP_PATH = NEW_DOMAIN + 'api/index.php/';				// C端
### var B_DOMAIN = 'http://ntest.szlb.cc/';
### var B_PATH = 'admin.php/';
### left.html,left.js加版本号 (清缓存)

==================================================================================================================================================================
#  Shenzhou-New-Admin-Web
## admin-fc,admin-cs 软链设置
## 

==================================================================================================================================================================
#  Shenzhou-Sever

##  Commom/Conf/config.php
### 'WORKER_CODE_KEY' => 'SHENZHOU@3NCTO',
### 'WORKER_CODE_MIN_LENGTH' => 10,
### 'YIMA_CRYPT_KEY' => 'shenZhou@3NcTo',
### 'YIMA_CRYPT_MIN_LENGTH' => 11,
