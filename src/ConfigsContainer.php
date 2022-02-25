<?php
namespace ApolloSdk\Config;
use Psr\Http\Message\ResponseInterface;

/**
 * @method string getAppId()
 * @method string getNamespaceName()
 * @method string getCluster()
 * @method string getHttpStatusCode()
 * @method string getRawData()
 */
class ConfigsContainer {
    private $appId = '';
    private $cluster = '';
    private $namespaceName = '';
    private $useCacheApi = false;
    private $rawData = '';
    private $httpStatusCode = 0;

    public function __construct($item = []) {
        if(isset($item['app_id'])) {
            $this->appId = (string)$item['app_id'];
        }
        if(isset($item['cluster_name'])) {
            $this->cluster = (string)$item['cluster_name'];
        }
        if(isset($item['namespace'])) {
            $this->namespaceName = (string)$item['namespace'];
        }
        if(isset($item['use_cache_api'])) {
            $this->useCacheApi = (bool)$item['use_cache_api'];
        }
        if(
            isset($item['response']) &&
            $item['response'] instanceof ResponseInterface
        ) {
            $response = &$item['response'];
            $this->rawData = (string)$response->getBody()->getContents();
            $this->httpStatusCode = (int)$response->getStatusCode();
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
     * 读取configurations
     * @param bool $toArray 是否转换为数组格式
     * @return string|array
     * @author fengzhibin
     * @date 2022-02-25
     */
    public function getConfigurations($toArray = true) {
        if($this->useCacheApi === false) {
            $res = $this->getRawDataKeyValue('configurations');
        } else {
            $res = $this->getRawData();
        }
        if($toArray === true) {
            return $this->toArray($res);
        }
        return $this->toString($res);
    }

    /**
     * 读取releaseKey
     * @return string
     * @author fengzhibin
     * @date 2022-02-25
     */
    public function getReleaseKey() {
        return (string)$this->getRawDataKeyValue('releaseKey');
    }

    /**
     * 将结果转换为数组格式
     * @param null|string|array $rawData 待转换的数据，默认自行读取$this->rawData
     * @return array
     * @author fengzhibin
     * @date 2022-02-25
     */
    public function toArray($rawData = null) {
        if(is_null($rawData)) {
            $rawData = $this->getRawData();
        }
        if(empty($rawData)) {
            return [];
        }
        if(is_array($rawData)) {
            return $rawData;
        }
        return (array)json_decode($rawData, true);
    }

    /**
     * 将结果转换为字符串格式
     * @param null|string|array $rawData 待转换的数据，默认自行读取$this->rawData
     * @return string
     * @author fengzhibin
     * @date 2022-02-25
     */
    public function toString($rawData = null) {
        if(is_null($rawData)) {
            $rawData = $this->getRawData();
        }
        if(empty($rawData)) {
            return '';
        }
        if(is_string($rawData)) {
            return $rawData;
        }
        return (string)json_encode($rawData);
    }

    /**
     * 从原始数据中读取键的值
     * @param string $key 键名
     * @param mixed $default 当键不存在时默认返回的值
     * @return mixed
     * @author fengzhibin
     * @date 2022-02-25
     */
    private function getRawDataKeyValue($key, $default = '') {
        if(empty($key)) {
            return $default;
        }
        $rawData = $this->toArray();
        return isset($rawData[$key])?$rawData[$key]:$default;
    }
}