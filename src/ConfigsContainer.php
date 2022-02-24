<?php
namespace ApolloSdk\Config;
use Psr\Http\Message\ResponseInterface;

class ConfigsContainer {
    private $appId = '';
    private $namespace = '';
    private $clusterName = '';
    private $useCacheApi = true;
    private $rawData = '';
    private $httpStatusCode = 0;

    public function __construct($item = []) {
        if(isset($item['app_id'])) {
            $this->appId = (string)$item['app_id'];
        }
        if(isset($item['namespace'])) {
            $this->namespace = (string)$item['namespace'];
        }
        if(isset($item['cluster_name'])) {
            $this->clusterName = (string)$item['cluster_name'];
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

    public function getAppId() {
        return $this->appId;
        //return $this->getValueFromRawData('appId');
    }

    public function getNamespaceName() {
        return $this->namespace;
        //return $this->getValueFromRawData('namespaceName');
    }

    public function getCluster() {
        return $this->clusterName;
        //return $this->getValueFromRawData('cluster');
    }

    public function getConfigurations($toArray = true) {
        if($this->useCacheApi === false) {
            $res = $this->getValueFromRawData('configurations');
        } else {
            $res = $this->getRawData();
        }
        if($toArray === true) {
            return $this->toArray($res);
        }
        return $this->toString($res);
    }

    public function getReleaseKey() {
        return $this->getValueFromRawData('releaseKey');
    }

    public function getHttpStatusCode() {
        return (int)$this->httpStatusCode;
    }

    public function getRawData() {
        return (string)$this->rawData;
    }

    public function toArray($rawData = null) {
        if(is_null($rawData)) {
            $rawData = $this->rawData;
        }
        if(empty($rawData)) {
            return [];
        }
        if(is_array($rawData)) {
            return $rawData;
        }
        return (array)json_decode($rawData, true);
    }

    public function toString($rawData = null) {
        if(is_null($rawData)) {
            $rawData = $this->rawData;
        }
        if(empty($rawData)) {
            return '';
        }
        if(is_string($rawData)) {
            return $rawData;
        }
        return (string)json_encode($rawData);
    }

    private function getValueFromRawData($key, $default = '') {
        if(empty($key)) {
            return $default;
        }
        $rawData = $this->toArray();
        return isset($rawData[$key])?$rawData[$key]:$default;
    }
}