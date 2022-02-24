<?php
namespace ApolloSdk\Config;

class ConfigsListContainer {

    private $list = [];

    public function __construct($item = []) {
        $this->add($item);
    }

    public function add($item = []) {
        if(
            !empty($item['app_id']) &&
            !empty($item['namespace'])
        ) {
            $appId = &$item['app_id'];
            $namespace = &$item['namespace'];
            $this->list[$appId][$namespace] = new ConfigsContainer($item);
        }
    }

    public function all() {
        return (array)$this->list;
    }

    public function one($appId, $namespace) {
        if(isset($this->list[$appId][$namespace])) {
            return $this->list[$appId][$namespace];
        }
        return new ConfigsContainer();
    }
}