<?php
namespace ApolloSdk\Config;

class Signature {
    /**
     * Authorization=Apollo {appId}:{sign}
     */

    const AUTHORIZATION_FORMAT = "Apollo %s:%s";
    const DELIMITER = "\n";
    const HTTP_HEADER_AUTHORIZATION = "Authorization";
    const HTTP_HEADER_TIMESTAMP = "Timestamp";

    /**
     * 生成签名字符串
     * @param int $timestamp 13位时间戳
     * @param string $pathWithQuery 请求uri
     * @param string $secret 密钥
     * @return string
     * @author fengzhibin
     * @date 2021-02-23
     */
    public static function generateSignature($timestamp, $pathWithQuery, $secret) {
        if(
            empty($timestamp) ||
            empty($pathWithQuery) ||
            empty($secret)
        ) {
            return '';
        }
        return base64_encode(
            hash_hmac(
                'sha1',
                mb_convert_encoding($timestamp.self::DELIMITER.$pathWithQuery, "UTF-8"),
                $secret,
                true
            )
        );
    }

    /**
     * 生成用于http请求的headers的认证字符串
     * @param string $appId 应用id
     * @param int $timestamp 13位时间戳
     * @param string $pathWithQuery 请求uri
     * @param string $secret 密钥
     * @return string
     * @author fengzhibin
     * @date 2021-02-23
     */
    public static function getAuthorizationString($appId, $timestamp, $pathWithQuery, $secret) {
        $sign = self::generateSignature($timestamp, $pathWithQuery, $secret);
        return sprintf(self::AUTHORIZATION_FORMAT, $appId, $sign);
    }
}