<?php
namespace ApolloSdk\Config\HttpDrive;

use GuzzleHttp\Client;
use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Promise\PromiseInterface;

class Guzzle {
    private $client;
    private $promise;

    public function __construct() {
        $this->client = new Client();
    }

    public function get($url, $options = []) {
        return $this->responseToString($this->client->get($url, $options));
    }

    public function asyncGet($url, $options = [], $callback = null) {
        $this->promise = $this->client->getAsync($url, $options);
        $this->promise->then(
            function(ResponseInterface $response) use($callback) {
                if(is_callable($callback)) {
                    call_user_func($callback, $this->responseToString($response));
                }
            },
            function(RequestException $e) use($callback) {
                if(is_callable($callback)) {
                    call_user_func($callback);
                }
            }
        );
    }

    public function wait() {
        if($this->promise instanceof PromiseInterface) {
            return $this->promise->wait();
        }
    }

    private function responseToString(ResponseInterface $response) {
        return $response->getBody()->getContents();
    }
}