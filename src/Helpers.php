<?php
namespace ApolloSdk\Config;

/**
 * 判断是否为合法的url链接
 * @param string $url url链接
 * @author fengzhibin
 * @return bool
 * @date 2022-02-16
 */
function is_legal_url($url) {
    if(
        !empty($url) &&
        preg_match('/http[s]?:\/\/[\w.]+[\w\/]*[\w.]*\??[\w=&\+\%]*/is', $url)
    ) {
        return true;
    }
    return false;
}

/**
 * 判断是否为合法的ip地址
 * @param string $ip ip地址
 * @author fengzhibin
 * @return bool
 * @date 2022-02-16
 */
function is_legal_ip($ip) {
    if(empty($ip)) {
        return false;
    }
    return filter_var($ip, FILTER_VALIDATE_IP);
}

/**
 * 格式化json字符串（这个函数固定返回数组）
 * @author fengzhibin
 * @return array
 * @date 2022-02-17
 */
function _json_decode($string = '') {
    $res = [];
    if(!empty($string)) {
        $res = json_decode($string, true);
    }
    return empty($res)?[]:(array)$res;
}