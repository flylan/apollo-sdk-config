<?php
namespace ApolloSdk\Config;

use GuzzleHttp\Client as GuzzleHttpClient;
use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Promise\PromiseInterface;

/**
 * @method string getConfigServerUrl()
 * @method string getClientIp()
 * @method string getClusterName()
 * @method string getSecret()
 */
class Request {
    const API_NAME_GET_CONFIG = 'get_config';//通过（不）带缓存的Http接口从Apollo读取配置
    const API_NAME_AWARE_CONFIG_UPDATE = 'aware_config_update';//应用感知配置更新

    private $configServerUrl = '';
    private $clientIp = '';
    private $clusterName = 'default';
    private $secret;

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
        if(
            !empty($config['secret']) &&
            (
                is_string($config['secret']) ||
                is_array($config['secret'])
            )
        ) {
            $this->secret = $config['secret'];
        }
    }

    /**
     * 通过魔术方法调用getXXX
     * @param string $name 方面名称
     * @param array $arguments 参数
     * @return mixed
     * @author fengzhibin
     * @date 2022-02-25
     */
    public function __call($name, $arguments) {
        if(substr($name, 0, 3) === 'get') {
            $key = lcfirst(substr($name, 3));
            if(property_exists($this, $key)) {
                return $this->$key;
            }
        }
        throw new \Exception("No such method exists: {$name}");
    }

    /**
     * 发起http同步（异步）get请求
     * @param string $apiName api名字，参数API_NAME_XXX常量
     * @param string $appId 应用的appId
     * @param array $requestData 请求数据
     * @param callable $callback promise被实现或者被拒绝时调用
     * @return ResponseInterface|PromiseInterface
     * @throws \Exception
     * @author fengzhibin
     * @date 2022-02-24
     */
    public function get($apiName, $appId, array $requestData = [], callable $callback = null) {
        $timeout = 10;//默认为10秒请求时间
        if($apiName === self::API_NAME_AWARE_CONFIG_UPDATE) {
            $timeout = 63;
        }
        $options = ['timeout' => $timeout];
        //请求链接
        $url = $this->buildUrl($appId, $requestData);
        //生成请求头
        $headers = $this->buildRequestHeaders($appId, $url);
        if(!empty($headers)) {
            $options['headers'] = $headers;
        }
        if(is_null($callback)) {
            return Guzzle::get($url, $options);
        }
        return Guzzle::getAsync($url, $options, $callback);
    }

    /**
     * 等待异步请求完成
     * @author fengzhibin
     * @return mixed
     * @date 2021-02-22
     */
    public function wait() {
        return Guzzle::wait();
    }

    /**
     * 构建用于请求的阿波罗接口链接
     * @param string $appId 应用的appId
     * @return string
     * @author fengzhibin
     * @date 2021-02-22
     */
    private function buildUrl($appId, $requestData = [], $apiName = self::API_NAME_GET_CONFIG) {
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
    private function buildRequestHeaders($appId, $url) {
        //获取密钥
        $secret = '';
        if(!empty($this->secret)) {
            if(is_string($this->secret)) {//如果secret为字符串格式
                $secret = $this->secret;
            } elseif(//多应用下配置secret格式 [$appId] => secret
                is_array($this->secret) &&
                !empty($this->secret[$appId])
            ) {
                $secret = $this->secret[$appId];
            }
        }
        $res = [];
        if(empty($appId) || empty($secret)) {
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
                $appId, $timestamp, $pathWithQuery, $secret
            );
            $res[Signature::HTTP_HEADER_TIMESTAMP] = $timestamp;
        }
        return $res;
    }
}