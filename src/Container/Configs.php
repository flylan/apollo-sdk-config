<?php
namespace ApolloSdk\Config\Container;
use Psr\Http\Message\ResponseInterface;

/**
 * @method string getAppId()
 * @method string getNamespaceName()
 * @method string getCluster()
 * @method ResponseInterface getResponse()
 */
class Configs {
    private $appId = '';
    private $cluster = '';
    private $namespaceName = '';
    private $useCacheApi = false;
    private $response;

    //比较常见的状态码
    const HTTP_STATUS_OK = 'ok';
    const HTTP_STATUS_BAD_REQUEST = 'bad_request';
    const HTTP_STATUS_UNAUTHORIZED = 'unauthorized';
    const HTTP_STATUS_FORBIDDEN = 'forbidden';
    const HTTP_STATUS_NOT_FOUND = 'not_found';
    const HTTP_STATUS_METHOD_NOT_ALLOWED = 'method_not_allowed';
    const HTTP_STATUS_INTERNAL_SERVER_ERROR = 'internal_server_error';
    const HTTP_STATUS_BAD_GATEWAY = 'bad_gateway';
    const HTTP_STATUS_SERVICE_UNAVAILABLE = 'service_unavailable';
    const HTTP_STATUS_GATEWAY_TIME_OUT = 'gateway_time_out';
    const HTTP_STATUS_UNKNOW = 'unknow';

    private static $httpStatusMapping = [
        200 => self::HTTP_STATUS_OK,//请求成功
        400 => self::HTTP_STATUS_BAD_REQUEST,//客户端请求的语法错误，服务器无法理解
        401 => self::HTTP_STATUS_UNAUTHORIZED,//请求要求用户的身份认证
        403 => self::HTTP_STATUS_FORBIDDEN,//服务器理解请求客户端的请求，但是拒绝执行此请求
        404 => self::HTTP_STATUS_NOT_FOUND,//服务器无法根据客户端的请求找到资源（网页）
        405 => self::HTTP_STATUS_METHOD_NOT_ALLOWED,//客户端请求中的方法被禁止
        500 => self::HTTP_STATUS_INTERNAL_SERVER_ERROR,//服务器内部错误，无法完成请求
        502 => self::HTTP_STATUS_BAD_GATEWAY,//作为网关或者代理工作的服务器尝试执行请求时，从远程服务器接收到了一个无效的响应
        503 => self::HTTP_STATUS_SERVICE_UNAVAILABLE,//由于超载或系统维护，服务器暂时的无法处理客户端的请求
        504 => self::HTTP_STATUS_GATEWAY_TIME_OUT//充当网关或代理的服务器，未及时从远端服务器获取请求
    ];

    public function __construct($item = []) {
        if(isset($item['app_id'])) {
            $this->appId = (string)$item['app_id'];
        }
        if(isset($item['cluster'])) {
            $this->cluster = (string)$item['cluster'];
        }
        if(isset($item['namespace_name'])) {
            $this->namespaceName = (string)$item['namespace_name'];
        }
        if(isset($item['use_cache_api'])) {
            $this->useCacheApi = (bool)$item['use_cache_api'];
        }
        if(
            isset($item['response']) &&
            $item['response'] instanceof ResponseInterface
        ) {
            $this->response = $item['response'];
        } else {
            //初始化一个Response
            $this->response = new \GuzzleHttp\Psr7\Response(0);
        }
    }

    /**
     * 通过魔术方法调用getXXX
     * @param string $name 名称
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
     * 获取http状态码
     * @return int
     * @author fengzhibin
     * @date 2022-03-10
     */
    public function getHttpStatusCode() {
        return (int)$this->response->getStatusCode();
    }

    /**
     * 获取http状态信息
     * @return string
     * @author fengzhibin
     * @date 2022-03-10
     */
    public function getHttpStatus() {
        $httpStatusCode = $this->getHttpStatusCode();
        if(isset(self::$httpStatusMapping[$httpStatusCode])) {
            return self::$httpStatusMapping[$httpStatusCode];
        }
        return self::HTTP_STATUS_UNKNOW;
    }

    /**
     * 获取原始数据
     * @return string
     * @author fengzhibin
     * @date 2022-02-25
     */
    private function getBodyContents() {
        return (string)$this->response->getBody()->getContents();
    }

    /**
     * 读取releaseKey
     * @return string
     * @author fengzhibin
     * @date 2022-02-25
     */
    public function getReleaseKey() {
        return (string)$this->getValueFromBodyContents('releaseKey');
    }

    /**
     * 读取configurations
     * @param bool $toArray 是否转换为数组格式
     * @return string|array
     * @author fengzhibin
     * @date 2022-02-25
     */
    public function getConfigurations($toArray = true) {
        $res = '';
        if($this->getHttpStatus() === self::HTTP_STATUS_OK) {
            if($this->useCacheApi === false) {
                $res = $this->getValueFromBodyContents('configurations');
            } else {
                $res = $this->getBody();
            }
        }
        if($toArray === true) {
            return $this->toArray($res);
        }
        return $this->toString($res);
    }

    /**
     * 将结果转换为数组格式
     * @param null|string|array $contents 待转换的数据，默认自行读取$this->getBodyContents()
     * @return array
     * @author fengzhibin
     * @date 2022-02-25
     */
    private function toArray($contents = null) {
        if(is_null($contents)) {
            $contents = $this->getBodyContents();
        }
        if(empty($contents)) {
            return [];
        }
        if(is_array($contents)) {
            return $contents;
        }
        return (array)json_decode($contents, true);
    }

    /**
     * 将结果转换为字符串格式
     * @param null|string|array $contents 待转换的数据，默认自行读取$this->getBodyContents()
     * @return string
     * @author fengzhibin
     * @date 2022-02-25
     */
    private function toString($contents = null) {
        if(is_null($contents)) {
            $contents = $this->getBodyContents();
        }
        if(empty($contents)) {
            return '';
        }
        if(is_string($contents)) {
            return $contents;
        }
        return (string)json_encode($contents);
    }

    /**
     * 从原始数据中读取键的值
     * @param string $key 键名
     * @param mixed $default 当键不存在时默认返回的值
     * @return mixed
     * @author fengzhibin
     * @date 2022-02-25
     */
    private function getValueFromBodyContents($key, $default = '') {
        if(empty($key)) {
            return $default;
        }
        $rawData = $this->toArray();
        return isset($rawData[$key])?$rawData[$key]:$default;
    }
}