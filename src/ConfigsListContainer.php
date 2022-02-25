<?php
namespace ApolloSdk\Config;

class ConfigsListContainer {

    private $list = [];

    /**
     * 添加一个元素到list
     * @return null
     * @author fengzhibin
     * @date 2022-02-25
     */
    public function add($item = []) {
        $this->list[] = new ConfigsContainer($item);
    }

    /**
     * 返回整个list
     * @return array
     * @author fengzhibin
     * @date 2022-02-25
     */
    public function all() {
        return (array)$this->list;
    }

    /**
     * 将list转换为映射结构，结构格式为[appId][namespace] => ConfigsContainer
     * @return array
     * @author fengzhibin
     * @date 2022-02-25
     */
    public function toMappingStruct() {
        $res = [];
        $this->each(
            function(ConfigsContainer $configsObj) use(&$res) {
                $appId = $configsObj->getAppId();
                $namespace = $configsObj->getNamespaceName();
                $res[$appId][$namespace] = $configsObj;
            }
        );
        return $res;
    }

    /**
     * 从list中读取第一个元素
     * @return ConfigsContainer
     * @author fengzhibin
     * @date 2022-02-25
     */
    public function first() {
        if(!empty($this->list)) {
            $res = reset($this->list);
            if($res instanceof ConfigsContainer) {
                return $res;
            }
        }
        return null;
    }

    /**
     * 遍历list
     * @param null|callable $callback 回调函数
     * @return null
     * @author fengzhibin
     * @date 2022-02-25
     */
    public function each($callback = null) {
        if(!empty($this->list) && is_callable($callback)) {
            foreach($this->list as $configsObj) {
                if($configsObj instanceof ConfigsContainer) {
                    call_user_func($callback, $configsObj);
                }
            }
        }
    }
}