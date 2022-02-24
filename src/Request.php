<?php
namespace ApolloSdk\Config;

use GuzzleHttp\Client as GuzzleHttpClient;
use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Promise\PromiseInterface;

class Request {
    const API_NAME_GET_CONFIG = 'get_config';//通过（不）带缓存的Http接口从Apollo读取配置
    const API_NAME_AWARE_CONFIG_UPDATE = 'aware_config_update';//应用感知配置更新

    const CALLBACK_TYPE_FULFILLED = 'fulfilled';
    const CALLBACK_TYPE_REJECTED = 'rejected';

    private $configServerUrl = '';
    private $clientIp = '';
    private $clusterName = 'default';
    private $secret = '';

    private $guzzleHttpClient;
    private $promise;

    public function __construct($config) {
        //配置中心地址
        if(empty($config['config_server_url'])) {
            throw new \Exception("必须传入config_server_url", -1);
        }
        if(!is_legal_url($config['config_server_url'])) {
            throw new \Exception("请检查config_server_url格式", -2);
        }
        $this->configServerUrl = $config['config_server_url'];
        //客户端ip
        if(
            !empty($config['client_ip']) &&
            is_legal_ip($config['client_ip'])
        ) {
            $this->clientIp = $config['client_ip'];
        }
        //集群名称
        if(!empty($config['cluster_name'])) {
            $this->clusterName = $config['cluster_name'];
        }
        //密钥
        if(!empty($config['secret'])) {
            $this->secret = $config['secret'];
        }
        $this->guzzleHttpClient = new GuzzleHttpClient();
    }

    /**
     * 获取集群名称
     * @return string
     * @author fengzhibin
     * @date 2022-02-24
     */
    public function getClusterName() {
        return $this->clusterName;
    }

    /**
     * 发起http同步get请求
     * @param string $appId 应用的appId
     * @param string $url 请求链接
     * @return null|ResponseInterface
     * @author fengzhibin
     * @date 2022-02-24
     */
    public function get($appId, $url) {
        $options = ['timeout' => 10, 'http_errors' => false];
        //生成请求头
        $headers = $this->buildRequestHeaders($appId, $url);
        if(!empty($headers)) {
            $options['headers'] = $headers;
        }
        try {
            return $this->guzzleHttpClient->get($url, $options);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * 发起http异步get请求
     * @param string $appId 应用的appId
     * @param string $url 请求链接
     * @param mixed $callback 异步回调函数
     * @return PromiseInterface
     * @author fengzhibin
     * @date 2022-02-24
     */
    public function asyncGet($appId, $url, $callback = null) {
        $options = ['timeout' => 63, 'http_errors' => false];
        //生成请求头
        $headers = $this->buildRequestHeaders($appId, $url);
        if(!empty($headers)) {
            $options['headers'] = $headers;
        }
        return $this->promise = $this
            ->guzzleHttpClient
            ->getAsync($url, $options)
            ->then(
                function(ResponseInterface $response) use($callback) {
                    if(is_callable($callback)) {
                        call_user_func($callback, $response, null, self::CALLBACK_TYPE_FULFILLED);
                    }
                },
                function(RequestException $error) use($callback) {
                    if(is_callable($callback)) {
                        call_user_func($callback, null, $error, self::CALLBACK_TYPE_REJECTED);
                    }
                }
            );
    }

    /**
     * 等待异步请求完成
     * @author fengzhibin
     * @return mixed
     * @date 2021-02-22
     */
    public function wait() {
        if($this->promise instanceof PromiseInterface) {
            return $this->promise->wait();
        }
        return false;
    }

    /**
     * 构建用于请求的阿波罗接口链接
     * @param string $appId 应用的appId
     * @return string
     * @author fengzhibin
     * @date 2021-02-22
     */
    public function buildUrl($appId, $requestData = [], $apiName = self::API_NAME_GET_CONFIG) {
        $url = '';
        if(empty($appId)) {
            return $url;
        }
        switch ($apiName)
        {
            case self::API_NAME_GET_CONFIG:
                if(empty($requestData['namespace'])) {
                    return $url;
                }
                $namespace = $requestData['namespace'];
                $useCacheApi = isset($requestData['use_cache_api'])?(bool)$requestData['use_cache_api']:true;
                $releaseKey = isset($requestData['release_key'])?(string)$requestData['release_key']:true;
                if($useCacheApi === true) {
                    $url = "{$this->configServerUrl}/configfiles/json/$appId/{$this->clusterName}/{$namespace}";
                } else {
                    $url = "{$this->configServerUrl}/configs/$appId/{$this->clusterName}/{$namespace}";
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
                break;
            case self::API_NAME_AWARE_CONFIG_UPDATE:
                if(empty($requestData['notifications']) || !is_array($requestData['notifications'])) {
                    return $url;
                }
                $notifications = urlencode(json_encode($requestData['notifications']));
                $url = "{$this->configServerUrl}/notifications/v2?appId={$appId}&cluster={$this->clusterName}&notifications={$notifications}";
                break;
        }
        return $url;
    }

    /**
     * 生成请求头
     * @param string $appId 应用id
     * @param string $url 请求连接
     * @return array
     * @author fengzhibin
     * @date 2022-02-16
     */
    public function buildRequestHeaders($appId, $url) {
        $res = [];
        if(empty($appId) || empty($this->secret)) {
            return $res;
        }
        $timestamp = time() * 1000;
        $urlInfo = parse_url($url);
        if(!empty($urlInfo['path'])) {
            $pathWithQuery = $urlInfo['path'];
            if(!empty($urlInfo['query'])) {
                $pathWithQuery .= '?'.$urlInfo['query'];
            }
            $res[Signature::HTTP_HEADER_AUTHORIZATION] = Signature::getAuthorizationString(
                $appId, $timestamp, $pathWithQuery, $this->secret
            );
            $res[Signature::HTTP_HEADER_TIMESTAMP] = $timestamp;
        }
        return $res;
    }
}