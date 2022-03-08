<?php
namespace ApolloSdk\Config;

use Psr\Http\Message\ResponseInterface;

class Client {
    const EVENT_CONFIG_UPDATE = 'config_update';
    const EVENT_HTTP_RESPONE = 'http_respone';

    private $request;
    private $eventCallbackList;

    public function __construct($config = []) {
        $this->request = new Request($config);
    }

    /**
     * 读取单个应用配置
     * @param string $appId 应用的appId
     * @param string $namespace Namespace的名字
     * @param bool $useCacheApi 是否通过带缓存的Http接口从Apollo读取配置，设置为false可以使用不带缓存的Http接口从Apollo读取配置
     * @param string $releaseKey 上一次的releaseKey
     * @return ConfigsContainer
     * @author fengzhibin
     * @date 2021-02-18
     */
    public function get($appId, $namespace, $useCacheApi = true, $releaseKey = '') {
        $res = $this
            ->multiGet([$appId => [$namespace => $releaseKey]], $useCacheApi)
            ->first();
        //固定返回ConfigsContainer，防止上层直接使用->get()->getXXXX()报错
        if(!($res instanceof ConfigsContainer)) {
            return new ConfigsContainer();
        }
        return $res;
    }

    /**
     * 监听单个应用配置变化
     * @param string $appId 应用的appId
     * @param array $noticeMapping notificationId映射信息，结构为[namespace] => notificationId
     * @author fengzhibin
     * @date 2021-02-18
     */
    public function listen($appId, $noticeMapping) {
        if(empty($appId) || empty($noticeMapping)) {
            return false;
        }
        return $this->multiListen([$appId => $noticeMapping]);
    }

    /**
     * 读取多个应用配置
     * @param array $appReleaseMapping 多个应用的releaseKey映射信息，结构为[appid][namespace] => releaseKey
     * @param boolean $useCacheApi
     * @return ConfigsListContainer
     * @author fengzhibin
     * @date 2021-02-18
     */
    public function multiGet($appReleaseMapping, $useCacheApi = true) {
        $list = new ConfigsListContainer();
        if(empty($appReleaseMapping)) {
            return $list;
        }
        foreach($appReleaseMapping as $appId => &$value) {
            foreach($value as $namespace => &$releaseKey) {
                $response = $this->request->get(
                    Request::API_NAME_GET_CONFIG,
                    $appId,
                    ['namespace' => $namespace, 'use_cache_api' => $useCacheApi, 'release_key' => $releaseKey]
                );
                $list->add([
                    'app_id' => $appId,
                    'namespace' => $namespace,
                    'use_cache_api' => $useCacheApi,
                    'cluster_name' => $this->request->getClusterName(),
                    'response' => $response instanceof ResponseInterface?$response:null
                ]);
            }
        }
        return $list;
    }

    /**
     * 监听多个应用配置变化
     * @param array $appNoticeMapping 多个应用的notificationId映射信息，结构为[appid][namespace] => notificationId
     * @author fengzhibin
     * @date 2021-02-18
     */
    public function multiListen($appNoticeMapping) {
        if(empty($appNoticeMapping)) {
            return false;
        }

        //监听单个应用
        $loopForUpdate = function($appId, &$noticeMapping) use(&$loopForUpdate, $appNoticeMapping) {
            //异步请求回调
            $callback = function($appId, $response) use(&$loopForUpdate, $noticeMapping) {
                //响应数据
                $responeData = [];
                if($response instanceof ResponseInterface) {
                    $responeData = (string)$response->getBody()->getContents();
                    if(!empty($responeData)) {
                        $responeData = (array)json_decode($responeData, true);
                    }
                }
                //触发响应事件
                $this->triggerEvent(self::EVENT_HTTP_RESPONE, [$appId, $responeData]);
                if(!empty($responeData)) {
                    foreach($responeData as &$value) {
                        $newNotificationId = (int)$value['notificationId'];//新的notificationId
                        $namespace = (string)$value['namespaceName'];//namespace名字
                        //旧的notificationId
                        $oldNotificationId = null;
                        if(isset($noticeMapping[$namespace])) {
                            $oldNotificationId = (int)$noticeMapping[$namespace];
                        }
                        //判断namespace是否存在更新，如果更新了则触发对应的事件回调
                        if(
                            is_null($oldNotificationId) ||//当前namespace不存在与映射表中
                            $oldNotificationId !== $newNotificationId//新旧notificationId不同
                        ) {
                            $noticeMapping[$namespace] = $newNotificationId;
                            //触发配置更新事件
                            $this->triggerEvent(
                                self::EVENT_CONFIG_UPDATE,
                                [
                                    $appId,
                                    $namespace,
                                    $newNotificationId,
                                    $oldNotificationId,
                                    &$noticeMapping
                                ]
                            );
                        }
                    }
                }
                //继续监听当前应用
                $loopForUpdate($appId, $noticeMapping);
            };
            //生成用于请求应用感知配置更新接口的notifications数据结构
            $notifications = [];
            foreach($noticeMapping as $namespace => &$notificationId) {
                $notifications[] = [
                    'notificationId' => $notificationId,
                    'namespaceName' => $namespace
                ];
            }
            //发起异步请求
            $this->request->get(
                REQUEST::API_NAME_AWARE_CONFIG_UPDATE, $appId, ['notifications' => $notifications], $callback
            );
        };

        do {
            try {
                //监听多个应用
                foreach($appNoticeMapping as $appId => $noticeMapping) {
                    $loopForUpdate($appId, $noticeMapping);
                };
                $this->request->wait();
            } catch (\Exception $e) {}
        } while(true);
    }

    /**
     * 设置事件
     * @param string $eventName 事件名称
     * @param callable $eventCallback 事件回调
     * @return boolean
     * @author fengzhibin
     * @date 2021-02-18
     */
    public function on($eventName, $eventCallback) {
        if(!empty($eventName)) {
            $this->eventCallbackList[$eventName] = $eventCallback;
            return true;
        }
        return false;
    }

    /**
     * 触发事件
     * @param string $eventName 事件名称
     * @param callable $eventCallback 事件回调
     * @return mixed
     * @author fengzhibin
     * @date 2021-02-18
     */
    private function triggerEvent($eventName, $eventArgs = []) {
        if(
            !empty($eventName) &&
            isset($this->eventCallbackList[$eventName]) &&
            is_callable($this->eventCallbackList[$eventName])
        ) {
            return call_user_func_array($this->eventCallbackList[$eventName], $eventArgs);
        }
        return null;
    }

    /**
     * 检查url链接是否为合法的阿波罗配置中心地址
     * @param string $configServerUrl url链接
     * @author fengzhibin
     * @date 2021-03-19
     */
    public static function checkConfigServerUrl($url) {
        try {
            if(is_legal_url($url) === false) {
                throw new \Exception('阿波罗配置中心链接格式异常，不是合法的url');
            }
            $response = Guzzle::get($url, ['timeout' => 5, 'connect_timeout' => 5]);
            $statusCode = (int)$response->getStatusCode();
            $defaultStatusCode = 404;
            if($statusCode !== $defaultStatusCode) {
                throw new \Exception("配置中心根接口的状态码应该为{$defaultStatusCode}而不是当前{$statusCode}，请检查阿波罗配置中心链接");
            }
            $jsonDecodeBody = [];
            $body = (string)$response->getBody()->getContents();
            if(!empty($body)) {
                $jsonDecodeBody = json_decode($body, true);
            }
            if(!isset($jsonDecodeBody['status'])) {
                throw new \Exception("接口返回数据中没有status字段，原始内容为：{$body}");
            }
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
        return true;
    }
}