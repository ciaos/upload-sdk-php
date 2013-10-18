<?php

require("sdk.php");

$appid = 'appid';            # 应用APPID
$appname = 'appname';        # 应用APPNAME
$appsecret = 'appsecret';    # 应用SECRET

$dbank = new HuaweiDbankCloud($appid, $appname, $appsecret);

# upload(文件uri, 文件路径, 上传完成之后回调该URL, 期待回调该URL返回的状态);

$ret = $dbank->upload("/dl/$appname/abc/test2.dat", '/root/sdk/a.dat', 'http://file.dbank.com/lvs/', 200);
#$ret = $dbank->upload("/dl/$appname/abc/test.dat", '/root/sdk/b.dat');
echo print_r($ret, true);
