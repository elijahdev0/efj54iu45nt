<?php

namespace FSPoster\App\Providers\Helpers;

use Exception;
use FSPoster\GuzzleHttp\Client;
use FSPoster\Psr\Http\Message\ResponseInterface;

class GuzzleClient
{
    private Client $client;

    public function __construct(array $config = [])
    {
        $this->client = new Client($config);
    }

    /**
     * @throws Exception
     */
    public function request(string $method, string $url, array $options = []) : ResponseInterface
    {
        $method = strtolower($method);

        try
        {
            $response = $this->client->$method($url, $options);
        }
        catch (Exception $e)
        {
            if(!method_exists($e, 'getResponse') || empty($e->getResponse()))
            {
                throw $e;
            }

            $response = $e->getResponse();
        }

        return $response;
    }

    /**
     * @throws Exception
     */
    public function get(string $url, array $options = []): ResponseInterface
    {
        return $this->request('get', $url, $options);
    }

    /**
     * @throws Exception
     */
    public function post(string $url, array $options = []): ResponseInterface
    {
        return $this->request('post', $url, $options);
    }

    /**
     * @throws Exception
     */
    public function put(string $url, array $options = []): ResponseInterface
    {
        return $this->request('put', $url, $options);
    }
}