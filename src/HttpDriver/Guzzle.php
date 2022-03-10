<?php
namespace ApolloSdk\Config\HttpDriver;

use GuzzleHttp\Client;
use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Promise\PromiseInterface;

class Guzzle {

    const CALLBACK_TYPE_FULFILLED = 'fulfilled';
    const CALLBACK_TYPE_REJECTED = 'rejected';

    private static $client;
    private static $promise;

    /**
     * 全局共用一个client实例
     * @return Client
     * @throws \Exception
     * @author fengzhibin
     * @date 2022-03-08
     */
    private static function singleton() {
        if(!(self::$client instanceof Client)) {
            self::$client = new Client(['http_errors' => false]);
        }
        return self::$client;
    }

    /**
     * 发起http同步get请求
     * @param string $appId 应用的appId
     * @param string $url 请求链接
     * @return ResponseInterface
     * @throws \Exception
     * @author fengzhibin
     * @date 2022-03-08
     */
    public static function get($url, array $options = []) {
        return self::singleton()->get($url, $options);
    }

    /**
     * 发起http异步get请求
     * @param string $appId 应用的appId
     * @param string $url 请求链接
     * @param mixed $callback 异步回调函数
     * @return PromiseInterface
     * @throws \Exception
     * @author fengzhibin
     * @date 2022-03-08
     */
    public static function getAsync($url, array $options = [], callable $callback = null) {
        self::$promise = self::singleton()
            ->getAsync($url, $options)
            ->then(
                function(ResponseInterface $response) use($callback) {
                    if(is_callable($callback)) {
                        call_user_func($callback, $response, null, self::CALLBACK_TYPE_FULFILLED);
                    }
                },
                function(RequestException $error) use($callback) {
                    if(is_callable($callback)) {
                        call_user_func($callback, null, $error, self::CALLBACK_TYPE_REJECTED);
                    }
                }
            );
        return self::$promise;
    }

    /**
     * 等待promise完成
     * @return mixed
     * @throws \Exception
     * @author fengzhibin
     * @date 2022-03-08
     */
    public static function wait() {
        if(self::$promise instanceof PromiseInterface) {
            return self::$promise->wait();
        }
        return false;
    }
}