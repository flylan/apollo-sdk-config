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
 * 判断当前运行模式是否cli模式
 * @author fengzhibin
 * @return bool
 * @date 2022-02-16
 */
function is_cli_mode() {
    return strpos(php_sapi_name(), 'cli') !== false;
}

/**
 * 判断字符串是否为json
 * @param string $string 字符串
 * @param bool $strictMode 是否使用严格模式
 * @author fengzhibin
 * @return bool
 * @date 2022-02-24
 */
function is_json($string, $strictMode = true) {
    if(
        is_numeric($string) ||//是数字
        !is_string($string)//不是字符串
    ) {
        return false;
    }
    $string = trim($string);
    $firstCharacter = substr($string, 0, 1);//读取字符串第一个字符
    $lastCharacter = substr($string, -1);//读取字符串最后一个字符
    if(
        ($firstCharacter === '{' && $lastCharacter === '}') ||
        ($firstCharacter === '[' && $lastCharacter === ']')
    ) {
        //非严格模式
        if($strictMode === false) {
            return true;
        }
        //严格模式
        json_decode($string);
        return json_last_error() === JSON_ERROR_NONE;
    }
    return false;
}

/**
 * 检查url链接是否为合法的阿波罗配置中心地址
 * @param string $url url链接
 * @author fengzhibin
 * @date 2022-03-10
 */
function check_config_server_url($url) {
    try {
        if(is_legal_url($url) === false) {
            throw new \Exception('阿波罗配置中心链接格式异常，不是合法的url');
        }
        $response = Guzzle::get($url, ['timeout' => 5, 'connect_timeout' => 5]);
        $statusCode = (int)$response->getStatusCode();
        $defaultStatusCode = 404;
        if($statusCode !== $defaultStatusCode) {
            throw new \Exception(
                "配置中心根接口的状态码应该为{$defaultStatusCode}而不是当前{$statusCode}，请检查阿波罗配置中心链接"
            );
        }
        $jsonDecodeBody = [];
        $body = (string)$response->getBody()->getContents();
        if(!empty($body)) {
            $jsonDecodeBody = json_decode($body, true);
        }
        if(!isset($jsonDecodeBody['status'])) {
            throw new \Exception("接口返回数据中没有status字段，原始内容为：{$body}");
        }
    } catch (\Exception $e) {
        throw new \Exception($e->getMessage());
    }
    return true;
}