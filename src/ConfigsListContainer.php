<?php
namespace ApolloSdk\Config;

class ConfigsListContainer {

    private $list = [];

    public function add($item = []) {
        $this->list[] = new ConfigsContainer($item);
    }

    public function all() {
        return (array)$this->list;
    }

    public function first() {
        if(!empty($this->list)) {
            return reset($this->list);
        }
        return new ConfigsContainer();
    }
}