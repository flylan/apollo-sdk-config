<?php
namespace ApolloSdk\Config;
use GuzzleHttp\Client as GuzzleHttpClient;
use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\Exception\RequestException;

class Client {
    protected $configServerUrl = '';
    protected $clientIp = '';
    protected $clusterName = 'default';
    protected $secret = '';

    protected $httpInfo;//http请求信息
    protected $errorInfo;//错误信息

    protected $guzzleHttpClient;
    protected $promise = null;

    public function __construct($config = []) {
        if(empty($config['config_server_url'])) {
            throw new \Exception("必须传入config_server_url", -1);
        }
        $this->configServerUrl = $config['config_server_url'];
        if(!empty($config['client_ip'])) {
            $this->setClientIp($config['client_ip']);
        }
        if(!empty($config['cluster_name'])) {
            $this->setClusterName($config['cluster_name']);
        }
        if(!empty($config['secret'])) {
            $this->setSecret($config['secret']);
        }
        $this->guzzleHttpClient = new GuzzleHttpClient(['http_errors' => false]);
    }

    public function setClientIp($clientIp) {
        $this->clientIp = $clientIp;
    }

    public function setClusterName($clusterName) {
        $this->clusterName = $clusterName;
    }

    public function setSecret($secret) {
        $this->secret = $secret;
    }

    public function setRequestTimeout($timeout) {
        $this->requestTimeout = $timeout;
    }

    /**
     * 从阿波罗服务器读取配置
     * @param string $appId 应用的appId
     * @param string $namespaceName Namespace的名字
     * @param bool $useCacheApi 是否通过带缓存的Http接口从Apollo读取配置，设置为false可以使用不带缓存的Http接口从Apollo读取配置
     * @param string $releaseKey 上一次的releaseKey
     * @return mixed
     * @author fengzhibin
     * @date 2021-02-09
     */
    public function getConfig($appId, $namespaceName, $useCacheApi = true, $releaseKey = '') {
        $this->isMultiGet = false;
        $res = $this->multiGetConfig([$appId => [$namespaceName => $releaseKey]], $useCacheApi);
        //当前应用指定namespace的http请求信息
        if(isset($this->httpInfo[$appId][$namespaceName])) {
            $this->httpInfo = $this->httpInfo[$appId][$namespaceName];
        } else {
            $this->httpInfo = [];
        }
        //当前应用指定namespace的错误信息
        if(isset($this->errorInfo[$appId][$namespaceName])) {
            $this->errorInfo = $this->errorInfo[$appId][$namespaceName];
        } else {
            $this->errorInfo = [];
        }
        if(!empty($this->errorInfo)) {//存在错误信息则设置返回结果为false
            return false;
        }
        return isset($res[$appId][$namespaceName])?$res[$appId][$namespaceName]:[];
    }

    /**
     * 批量读取配置
     * @param array $appNamespaceData 应用id及其Namespace列表信息，格式例子：
     *      Array(
     *          'app_id_1' => [
     *              'application' => '',
     *              'FX.apollo' => ''
     *          ],
     *          'app_id_2' => [
     *              'application' => ''
     *          ]
     *      )
     * @param array $useCacheApi 是否通过带缓存的Http接口从Apollo读取配置，设置为false可以使用不带缓存的Http接口从Apollo读取配置
     * @return array
     * @author fengzhibin
     * @date 2021-02-09
     */
    public function multiGetConfig(array $appNamespaceData, $useCacheApi = true) {
        $this->httpInfo = [];

        $res = [];
        if(empty($appNamespaceData)) {
            return $res;
        }

        $isMultiGet = true;//是否批量获取配置
        $promiseWait = true;//是否启用promise wait
        $asyncGetResult = null;//通过回调函数异步获取返回结果
        $setHttpInfo = true;//附带http信息
        $setErrorInfo = true;//附带错误信息
        if(isset($this->isMultiGet)) {
            $isMultiGet = (bool)$this->isMultiGet;
            unset($this->isMultiGet);
        }
        if(isset($this->promiseWait)) {
            $promiseWait = (bool)$this->promiseWait;
            unset($this->promiseWait);
        }
        if(isset($this->asyncGetResult)) {
            $asyncGetResult = $this->asyncGetResult;
            unset($this->asyncGetResult);
        }
        if(isset($this->setHttpInfo)) {
            $setHttpInfo = $this->setHttpInfo;
            unset($this->setHttpInfo);
        }
        if(isset($this->setErrorInfo)) {
            $setErrorInfo = $this->setErrorInfo;
            unset($this->setErrorInfo);
        }

        foreach($appNamespaceData as $appId => &$namespaceData) {
            foreach($namespaceData as $namespaceName => &$releaseKey) {
                //带缓存接口，置空releaseKey
                $useCacheApi === true && $releaseKey = '';
                //初始化返回结果
                !isset($res[$appId][$namespaceName]) && $res[$appId][$namespaceName] = [];
                $this->promise = $this->requestAsync(
                    $this->buildGetConfigRequestUrl($appId, $namespaceName, $useCacheApi, $releaseKey),
                    $appId,
                    ['timeout' => 10]//默认10秒超时
                );
                $this->promise->then(
                    function(ResponseInterface $response) use(
                        &$res,
                        $appId,
                        $namespaceName,
                        $useCacheApi,
                        $asyncGetResult,
                        $isMultiGet,
                        $setHttpInfo
                    ) {
                        $responseCode = (int)$response->getStatusCode();
                        $responseBody = (string)$response->getBody();
                        if($setHttpInfo === true) {
                            $this->httpInfo[$appId][$namespaceName] = [
                                'response_code' => $responseCode,
                                'response_body' => $responseBody
                            ];
                        }
                        switch ($responseCode)
                        {
                            case 200:
                                $responseBody = json_decode($responseBody, true);
                                empty($responseBody) && $responseBody = [];
                                break;
                            case 304:
                                $responseBody = [];
                                break;
                            default:
                                $responseBody = false;
                        }
                        if($useCacheApi === false) {//不带缓存的接口，配置项在configurations里面
                            $res[$appId][$namespaceName] = $responseBody['configurations'];
                        } else {//带缓存的接口，responseBody就是配置项
                            $res[$appId][$namespaceName] = $responseBody;
                        }
                        //把结果通过$asyncGetResult回调函数交给上层
                        if(is_callable($asyncGetResult)) {
                            call_user_func($asyncGetResult, $isMultiGet === true?$res:$res[$appId][$namespaceName]);
                        }
                    },
                    function (RequestException $exception) use(&$res, $appId, $namespaceName, $setErrorInfo) {
                        if($setErrorInfo === true) {
                            $this->errorInfo[$appId][$namespaceName] = [
                                'code' => $exception->getCode(),
                                'message' => $exception->getMessage()
                            ];
                        }
                        $res[$appId][$namespaceName] = false;//存在异常则设置结果为false
                    }
                );
            }
        }

        $promiseWait === true && $this->promiseWait();

        return $res;
    }

    /**
     * 多个应用感知配置更新
     * @param array $appNotificationsData 应用id及notifications信息，格式例子：
     *      Array(
     *          'app_id_1' => [
     *              'application' => 100,
     *              'FX.apollo' => 200
     *          ],
     *          'app_id_2' => [
     *              'application' => 100
     *          ]
     *      )
     * @param mixed $onConfigUpdate 当存在配置更新时触发的回调函数
     * @return mixed
     * @author fengzhibin
     * @date 2021-02-09
     */
    public function listenMultiAppConfigUpdate(
        array $appNotificationsData,
        $onConfigUpdate = null,
        $onResponse = null
    ) {
        if(empty($appNotificationsData)) {
            return false;
        }
        $this->httpInfo = [];

        //以下是执行流程
        //发起http长轮询监听指定应用的配置更新（请求会被服务器hold住）
        //如果被监听namespace发生配置变更（服务器会立刻响应当前请求，返回新的notificationId）
        //本地拿到新的notificationId，更新本地的映射表，然后再次发起http长轮询监听指定应用的配置更新
        $loopForConfigUpdate = function($appId, $namespaceNotificationMapping) use(
            &$onConfigUpdate, &$loopForConfigUpdate, &$onResponse
        ) {
            //生成notifications
            $notifications = [];
            foreach($namespaceNotificationMapping as $namespaceName => &$notificationId) {
                $notifications[] = ['namespaceName' => $namespaceName, 'notificationId' => $notificationId];
            }
            $this->promise = $this->requestAsync(
                $this->buildAwareConfigUpdateUrl($appId, $notifications), $appId, ['timeout' => 63]
            );
            unset($notifications);

            $this->promise->then(
                function(ResponseInterface $response) use(
                    $appId, &$loopForConfigUpdate, &$namespaceNotificationMapping, &$onConfigUpdate, &$onResponse
                ) {
                    //触发响应函数
                    if(is_callable($onResponse)) {
                        call_user_func_array($onResponse, [$appId, $response]);
                    }
                    $responseCode = (int)$response->getStatusCode();
                    if((int)$responseCode === 200) {
                        $body = $response->getBody();
                        $body = json_decode($body, true);
                        if(!empty($body) && is_array($body)) {
                            foreach($body as &$value) {
                                if(
                                    !isset($value['namespaceName']) ||
                                    !isset($value['notificationId'])
                                ) {
                                    continue;
                                }
                                $namespaceName = &$value['namespaceName'];
                                $notificationId = &$value['notificationId'];
                                if(
                                    isset($namespaceNotificationMapping[$namespaceName]) &&
                                    $namespaceNotificationMapping[$namespaceName] != $notificationId
                                ) {//配置发生变更了
                                    //更新映射表
                                    $namespaceNotificationMapping[$namespaceName] = $notificationId;
                                    //触发配置变更回调函数
                                    if(is_callable($onConfigUpdate)) {
                                        $this->promiseWait = false;//关闭getConfig方法内的promise wait
                                        $this->setHttpInfo = false;//关闭getConfig方法内的保存http信息的逻辑
                                        $this->setErrorInfo = false;//关闭getConfig方法内的保存错误信息的逻辑
                                        //由于接管了getConfig方法的promise wait，通过回调函数获取返回结果
                                        $this->asyncGetResult = function($newConfig) use(
                                            $appId,
                                            $namespaceName,
                                            &$onConfigUpdate,
                                            $notificationId,
                                            &$namespaceNotificationMapping
                                        ) {
                                            if($newConfig !== false) {
                                                call_user_func_array(
                                                    $onConfigUpdate,
                                                    [
                                                        $appId,
                                                        $namespaceName,
                                                        $newConfig,
                                                        $notificationId,
                                                        &$namespaceNotificationMapping
                                                    ]
                                                );
                                            }
                                        };
                                        //以下方法返回结果为空数组
                                        $this->getConfig($appId, $namespaceName, false);
                                    }
                                }
                            }
                        }
                    }
                    //再次发起http长轮询监听指定应用的配置更新
                    $loopForConfigUpdate($appId, $namespaceNotificationMapping);
                },
                function(RequestException $exception) use($appId, &$loopForConfigUpdate, &$namespaceNotificationMapping) {//偶尔有些超时请求会从此处产生
                    //防止因为阿波罗服务器异常而导致进入无限死循环
                    $nowTime = time();
                    $errorTimeLimit = 5;
                    if(!empty($this->onRejectedTimeList[$appId])) {
                        if(
                            count($this->onRejectedTimeList[$appId]) === $errorTimeLimit &&
                            count(array_unique($this->onRejectedTimeList[$appId])) <= 2
                        ) {//瞬间产生过多错误，退出event loop
                            die('错误码：'.$exception->getCode().'，错误信息：'.$exception->getMessage().PHP_EOL);
                        }
                    }
                    $this->onRejectedTimeList[$appId][] = $nowTime;
                    if(count($this->onRejectedTimeList[$appId]) > $errorTimeLimit) {
                        array_shift($this->onRejectedTimeList[$appId]);
                    }
                    //再次发起http长轮询监听指定应用的配置更新
                    $loopForConfigUpdate($appId, $namespaceNotificationMapping);
                }
            );
        };

        foreach($appNotificationsData as $appId => &$namespaceNotificationMapping) {
            if(empty($namespaceNotificationMapping)) {
                continue;
            }
            $loopForConfigUpdate($appId, $namespaceNotificationMapping);
        }

        $this->promiseWait();
    }

    /**
     * 获取http请求信息
     * @return array
     * @author fengzhibin
     * @date 2021-03-01
     */
    public function getHttpInfo() {
        return $this->httpInfo;
    }

    /**
     * 获取错误信息
     * @return array
     * @author fengzhibin
     * @date 2021-03-01
     */
    public function getErrorInfo() {
        return $this->errorInfo;
    }

    /**
     * 构建用于请求的阿波罗接口链接
     * @param string $appId 应用的appId
     * @param string $namespaceName Namespace的名字
     * @param bool $useCacheApi 是否通过带缓存的Http接口从Apollo读取配置
     * @param string $releaseKey 上一次的releaseKey
     * @return string
     * @author fengzhibin
     * @date 2021-02-23
     */
    private function buildGetConfigRequestUrl($appId, $namespaceName, $useCacheApi = true, $releaseKey = '') {
        if(empty($appId) || empty($namespaceName)) {
            return '';
        }
        if($useCacheApi === true) {
            $url = "{$this->configServerUrl}/configfiles/json/$appId/{$this->clusterName}/{$namespaceName}";
        } else {
            $url = "{$this->configServerUrl}/configs/$appId/{$this->clusterName}/{$namespaceName}";
        }
        $params = [];
        if(!empty($this->clientIp)) {
            $params['ip'] = $this->clientIp;
        }
        if(!empty($releaseKey)) {
            $params['releaseKey'] = $releaseKey;
        }
        if(!empty($params)) {
            $url .= '?'.http_build_query($params);
        }
        return $url;
    }

    /**
     * 构建用于请求的阿波罗接口链接
     * @param string $appId 应用的appId
     * @param array $notifications notifications信息，格式为二维数组，格式例子：
     *      Array(
     *          ['namespaceName' => 'application', 'notificationId' => 100],
     *          ['namespaceName' => 'FX.apollo', 'notificationId' => 200]
     *      )
     * @return string
     * @author fengzhibin
     * @date 2021-02-22
     */
    private function buildAwareConfigUpdateUrl($appId, array $notifications = []) {
        if(empty($appId) || empty($notifications)) {
            return '';
        }
        $notifications = urlencode(json_encode($notifications));
        return "{$this->configServerUrl}/notifications/v2?appId={$appId}&cluster=default&notifications={$notifications}";
    }

    /**
     * 发起异步请求
     * @param string $url 请求链接
     * @param array $options 请求配置，参考guzzlehttp文档
     * @param string $method 请求方法
     * @return \GuzzleHttp\Promise\PromiseInterface
     * @author fengzhibin
     * @date 2021-02-23
     */
    private function requestAsync($url, $appId = '', array $options = [], $method = 'GET') {
        if(
            !empty($this->secret) &&
            !empty($appId)
        ) {//追加访问密钥
            $timestamp = time() * 1000;
            $urlInfo = parse_url($url);
            if(!empty($urlInfo['path'])) {
                $pathWithQuery = $urlInfo['path'];
                if(!empty($urlInfo['query'])) {
                    $pathWithQuery .= '?'.$urlInfo['query'];
                }
                $options['headers'][Signature::HTTP_HEADER_AUTHORIZATION] = Signature::getAuthorizationString(
                    $appId, $timestamp, $pathWithQuery, $this->secret
                );
                $options['headers'][Signature::HTTP_HEADER_TIMESTAMP] = $timestamp;
            }
            unset($urlInfo);
        }
        return $this->guzzleHttpClient->requestAsync($method, $url, $options);
    }

    /**
     * 发起异步请求
     * @param string $url 请求链接
     * @param array $options 请求配置，参考guzzlehttp文档
     * @param string $method 请求方法
     * @return \GuzzleHttp\Promise\PromiseInterface
     * @author fengzhibin
     * @date 2021-02-23
     */
    private function promiseWait() {
        if(!is_null($this->promise)) {
            try {
                $this->promise->wait();
            } catch (\Exception $exception) {//屏蔽promise wait的错误，因为错误信息已经在promise then的onRejected回调函数中返回

            }
        }
    }
}