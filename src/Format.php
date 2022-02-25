<?php
namespace ApolloSdk\Config;

class Format {

    const MAPPING_TYPE_NOTICE = 'notice';
    const MAPPING_TYPE_RELEASE = 'release';

    const DEFAULT_NOTIFICATION_ID = -1;
    const DEFAULT_RELEASE_KEY = '';

    /**
     * 构建notificationId映射信息
     * @param array $namespaceList namespace列表
     * @return array
     * @author fengzhibin
     * @date 2022-02-18
     */
    public static function toNoticeMapping(&$namespaceList) {
        return self::toMapping($namespaceList);
    }

    /**
     * 构建releaseKey映射信息
     * @param array $namespaceList namespace列表
     * @return array
     * @author fengzhibin
     * @date 2022-02-14
     */
    public static function toReleaseMapping(&$namespaceList) {
        return self::toMapping($namespaceList, self::MAPPING_TYPE_RELEASE);
    }

    /**
     * 构建指定映射结构
     * @param array $namespaceList namespace列表
     * @param string $mappingType 映射类型
     * @return array
     * @author fengzhibin
     * @date 2022-02-14
     */
    private static function toMapping(&$namespaceList, $mappingType = self::MAPPING_TYPE_NOTICE) {
        $noticeMapping = [];
        if(empty($namespaceList)) {
            return $noticeMapping;
        }
        //默认值
        $defaultValue = self::DEFAULT_NOTIFICATION_ID;
        if($mappingType === self::MAPPING_TYPE_RELEASE) {
            $defaultValue = self::DEFAULT_RELEASE_KEY;
        }
        foreach($namespaceList as &$namespace) {
            $noticeMapping[$namespace] = $defaultValue;
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

    /**
     * 构建多个应用的releaseKey映射信息
     * @param array $appNamespaceMapping 多个应用与其namespace列表的关系，格式[appid] => namespaceList
     * @return array
     * @author fengzhibin
     * @date 2022-02-24
     */
    public static function toAppReleaseMapping(&$appNamespaceList) {
        $appReleaseMapping = [];
        if(empty($appNamespaceList)) {
            return $appReleaseMapping;
        }
        foreach($appNamespaceList as $appId => &$namespaceList) {
            $appReleaseMapping[$appId] = self::toReleaseMapping($namespaceList);
        }
        return $appReleaseMapping;
    }
}