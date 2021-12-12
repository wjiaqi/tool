## 熔断&&重试

### 说明

- 当对端服务出现异常，如服务崩了，查询数据超时等底层异常，为保护服务稳定，并且不会因为查询超时而浪费数据库资源，底层会重试maxAttempt(默认2次)该rpc请求， 总的失败数达到failCount(默认4次)后会在本服务开启熔断，后面的请求直接返回上一次的处理结果 ，熔断结束时间由duration(默认10s) 决定。注意对端服务返回标准的数据格式就是服务正常，不会开启熔断

### 使用方法

1. 把目录下的 Fuse  文件夹放到app\Kernel\目录下
2. RetryAspect.php 放到 app\Aspect 目录下
3. RpcRetryAnnotation.php 放到app\Annotation 目录下
4. 在调用别人服务的方法上面使用注解即可

### 可配置参数

|    参数名    | 是否必须 |                         示例                          |                  说明                  |  类型  |
| :----------: | :------: | :---------------------------------------------------: | :------------------------------------: | :----: |
| useFallback  |    否    |                         false                         |  是否使用失败回调方法,不使用抛出错误   |  bool  |
|   fallback   |    否    | App\JsonRpc\Caller\RolePowerPlusCaller::defaultMethod | 失败执行的回调方法，系统默认返回空数组 | string |
|  maxAttempt  |    否    |                           2                           |                重试次数                |  int   |
|   interval   |    否    |                           1                           |              重试间隔(s)               |  int   |
|   duration   |    否    |                          10                           |          熔断重新开放时间(s)           |  int   |
|  failCount   |    否    |                           4                           |                失败次数                |  int   |
| successCount |    否    |                           5                           |                成功次数                |  int   |



### 示例

```php
@RpcRetryAnnotation(useFallback=false, failCount=4, successCount=6, maxAttempt=4, useFullback=true, fallback="App\JsonRpc\Caller\RolePowerPlusCaller::defaultMethod")
```

