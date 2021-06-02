Ctrip Apollo PHP SDK
=======================
## 提示
这个仓库是php库，封装阿波罗接口的具体实现，提供了批量读取接口的能力。
如果你需要具体的阿波罗client的实现，可以参考这个仓库：https://github.com/fengzhibin/apollo-sdk-clientd
apollo-sdk-clientd基于apollo-sdk-config，完整实现了常驻版的阿波罗客户端和业务系统接入阿波罗配置的方法

如果你觉得这个项目不错，帮忙点个星星，你的支持对我来说是莫大的鼓励

## 版本提示
1.x版本支持php>=5.5

2.x及之后的版本支持php版本>=7.2.5

## 特性

- 支持批量读取多个应用配置
- 无额外扩展依赖
- 高性能实现单进程感知多个应用配置更新
- 支持配置访问密钥

## 安装

建议通过[Composer](http://getcomposer.org)安装

```bash
# 安装composer
curl -sS https://getcomposer.org/installer | php
```


```bash
composer require apollo-sdk/config
```

安装完成后，你需要在代码中引入Composer的自动加载器：

```php
require 'vendor/autoload.php';
```

后续，如果你想更新apollo sdk，可以执行以下命令

 ```bash
composer update
 ```


## 简单例子

读取配置

```php
<?php
require 'vendor/autoload.php';
use ApolloSdk\Config\Client;

$client = new Client([
    'config_server_url' => 'http://apollo-config.demo.com',//Apollo配置服务的地址，必须传入这个参数
    //'secret' => 'xxxxxxxxxx',//密钥，如果配置了密钥可以通过
]);

$appId = 'demo';
$namespaceName = 'application';
$useCacheApi = true;//设置为false可以通过不带缓存的Http接口从Apollo读取配置

echo '<pre>';
$config = $client->getConfig($appId, $namespaceName, $useCacheApi);
if($config === false) {//获取配置失败
    print_r($client->getErrorInfo());//当产生curl错误时此处有值
    print_r($client->getHttpInfo());//可能不是产生curl错误，而是阿波罗接口返回的http状态码不是200或304
} else {//获取配置成功
    print_r($config);//返回数组结构
}
```

批量读取配置
```php
<?php
require 'vendor/autoload.php';
use ApolloSdk\Config\Client;

$client = new Client([
    'config_server_url' => 'http://apollo-config.demo.com',//Apollo配置服务的地址，必须传入这个参数
    //'secret' => 'xxxxxxxxxx',//密钥，如果配置了密钥可以通过
]);

//应用id及其Namespace列表信息
$appNamespaceData = [
    'app_id_1' => [//应用id
        'application' => '',//格式为：namespace => releaseKey，如果没有releaseKey，设置为空字符串即可
        'FX.apollo' => ''
    ],
    'app_id_2' => [
        'application' => ''
    ]
]
$useCacheApi = true;//设置为false可以通过不带缓存的Http接口从Apollo读取配置

$config = $client->multiGetConfig($appNamespaceData, $useCacheApi);
//固定返回数组，返回结果如下
//Array(
//    'app_id_1' => [
//        'application' => [xxxxx],
//        'FX.apollo' => [xxxxx]
//    ],
//    'app_id_2' => [
//        'application' => false,//返回false代表获取结果失败
//    ]
//)
print_r($config);
```
多个应用感知配置更新
```php
<?php
require 'vendor/autoload.php';
use Apollo\Config\Client;

$client = new Client([
    'config_server_url' => 'http://apollo-config.demo.com',//Apollo配置服务的地址，必须传入这个参数
    //'secret' => 'xxxxxxxxxx',//密钥，如果配置了密钥可以通过
]);

//构建应用Notifications数据结构（支持监听多个应用多个namespace）
$appNotificationsData = [
    'app_id_1' => [//应用id
        'application' => -1,//格式为：namespaceName => notificationId，如果不知道notificationId，默认为-1即可
        'FX.apollo' => -1
    ],
    'app_id_2' => [
        'application' => -1
    ],
];

//以下这个方法会进入event loop（一直处于阻塞状态），建议在cli模式下运行
$client->listenMultiAppConfigUpdate(
    $appNotificationsData,
    //当某个应用的namespace更新了会触发下面这个回调函数
    //如果默认初始化应用的notificationId为-1，则每个应用在都会立即触发一次回调函数
    function ($appId, $namespaceName, $newConfig, $notificationId, $namespaceNotificationMapping) {
        echo date('Y-m-d H:i:s').'___'.$appId.'___'.$namespaceName.'___'.$notificationId.PHP_EOL;
        print_r($newConfig);//这个是被更新之后的配置
        print_r($namespaceNotificationMapping);//这个是应用的namespace的notification映射列表，1.0.2版本及之后的版本提供了这个参数
        echo PHP_EOL;
    },
    //监听配置变化时会进入http长连接轮询，每个接口响应的时候会触发下面这个方法
    function ($appId, \GuzzleHttp\Psr7\Response $response) {//1.0.4版本及之后的版本新增这个回调方法
        echo '应用：'$appId.'完成一次http请求'.PHP_EOL;
        //想了解更多关于guzzle http的respone信息
        //参考这个文档https://guzzle-cn.readthedocs.io/zh_CN/latest/psr7.html#responses
        echo $response->getStatusCode().PHP_EOL;
    }
);
```
