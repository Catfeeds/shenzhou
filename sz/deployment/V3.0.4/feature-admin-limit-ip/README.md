# 配置相关更改

# 语句注意事项

# 部署顺序

1.添加sql_backup中的语句
2.删除redis中旧admin数据(#redis-cli -p 35793 -a shenZhou3Ntest --scan --pattern 'sz:admin:*' | xargs  redis-cli -p 35793 -a shenZhou3Ntest del)

