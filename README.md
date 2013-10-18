Upload SDK PHP
=====================
华为云加速上传PHP SDK使用指南
* * *

A . 概述
-----------
该 SDK 适用于 PHP 5.2. 0 及其以上版本（且已安装CURL扩展），便于开发者快速上传文件。

B . 使用示例
----------
### B1 . 配置APPID，APPNAME，APPSECRET信息 ###

```php
<?php

require("sdk.php");

$appid = '123456test'; 						# 应用APPID
$appname = 'my-appname';					    # 应用APPNAME
$appsecret = '123456789abcdeftest';    		# 应用SECRET
```

### B2 . 上传文件 ###

```php
<?php

require("sdk.php");

$appid = '123456test'; 						# 应用APPID
$appname = 'my-appname';					    # 应用APPNAME
$appsecret = '123456789abcdeftest';    		# 应用SECRET

$uri = "/dl/$appname/path/myfile.dat";  #http://upload.server.com/dl/appname/path/path2/a.dat  
#upload(uri, 文件路径, 上传完成之后回调该URL, 期待回调该URL返回的状态);
$ret = $dbank->upload($uri, '/root/sdk/c.dat', 'http://my.website.com/rest？a=b', 200);

#也可以不设置回调URL
$ret = $dbank->upload($uri, '/root/sdk/c.dat');
```

交互流程如下

1. 客户端(服务器)直接上传至 距自己最近速度最快的云存储服务器
2. 上传成功后客户端进行后续业务操作
