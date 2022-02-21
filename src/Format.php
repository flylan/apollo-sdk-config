<?php
namespace ApolloSdk\Config;

class Format {

    /**
     * 构建notificationId映射信息
     * @param array $namespaceList namespace列表
     * @return array
     * @author fengzhibin
     * @date 2022-02-18
     */
    public static function toNoticeMapping(&$namespaceList) {
        $noticeMapping = [];
        if(empty($namespaceList)) {
            return $noticeMapping;
        }
        foreach($namespaceList as &$namespace) {
            $noticeMapping[$namespace] = -1;
        }
        return $noticeMapping;
    }

    /**
     * 构建多个应用的notificationId映射信息
     * @param array $appNamespaceMapping 多个应用与其namespace列表的关系，格式[appid] => namespaceList
     * @return array
     * @author fengzhibin
     * @date 2022-02-18
     */
    public static function toAppNoticeMapping(&$appNamespaceList) {
        $appNoticeMapping = [];
        if(empty($appNamespaceList)) {
            return $appNoticeMapping;
        }
        foreach($appNamespaceList as $appId => &$namespaceList) {
            $appNoticeMapping[$appId] = self::toNoticeMapping($namespaceList);
        }
        return $appNoticeMapping;
    }
}