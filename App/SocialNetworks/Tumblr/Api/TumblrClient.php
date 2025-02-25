<?php

namespace FSPoster\App\SocialNetworks\Tumblr\Api;

use Exception;
use FSPoster\GuzzleHttp\Client;

class TumblrClient
{
    private string $accessToken;
    private ?string $proxy;
    const API_BASE = 'https://api.tumblr.com/v2/';

    public function __construct ( string $accessToken, ?string $proxy )
    {
        $this->accessToken = $accessToken;
        $this->proxy = $proxy;
    }

    /**
     * @throws Exception
     */
    private function request( string $method, $uri, array $options) : array
    {
        $c = new Client(['verify' => false]);

        try
        {
            $method = strtolower($method);

            $options['proxy'] = ($this->proxy ?? null) ?: null;
            $options['headers'] = [
                'Authorization' => 'Bearer ' . $this->accessToken
            ];

            $response = $c->$method(self::API_BASE . $uri, $options)->getBody()->getContents();
        }
        catch ( Exception $e )
        {
            if(method_exists($e, 'getResponse') && !empty($e->getResponse()))
            {
                $response = $e->getResponse()->getBody()->getContents();
            }
            else
            {
                throw new Exception($e->getMessage());
            }
        }

        $response = json_decode($response, true);

        if(empty($response))
        {
            throw new Exception(fsp__('Unknown error'));
        }

        if(!empty($response['meta']['status']) && $response['meta']['status'] == 429)
        {
            throw new Exception(fsp__('The Standard APP has reached the hourly limit for adding accounts. The limit is assigned by Tumblr and you either need to <a href="https://www.fs-poster.com/documentation/fs-poster-schedule-share-wordpress-posts-to-tumblr-automatically" target="_blank">create a Tumblr App</a> for your own use or use the <a href="https://www.fs-poster.com/documentation/fs-poster-schedule-share-wordpress-posts-to-tumblr-automatically" target="_blank">email & pass method</a> to add your account to the plugin.', [], false));
        }

        if(isset($response['errors']))
        {
            $error = reset($response['errors']);

            if(empty($error) || empty($error['title']))
            {
                throw new Exception(fsp__('Unknown error'));
            }

            throw new Exception($error['title'] . ' ' . ($error['detail'] ?? ''));
        }


        if(!isset($response['response']))
        {
            throw new Exception(fsp__('Unknown error'));
        }

        return $response['response'];
    }

    /**
     * @throws Exception
     */
    public function get( string $uri, array $options = []) : array
    {
        return self::request('get', $uri, $options);
    }

    /**
     * @throws Exception
     */
    public function post( string $uri, array $options = array ()) : array
    {
        return self::request('post', $uri, $options);
    }

    /**
     * @throws Exception
     */
    public function getUserInfo () : TumblrUser
    {
        $user = new TumblrUser();

        $userInfo = $this->get('user/info');

        $user->name = $userInfo['user']['name'];

        foreach ($userInfo['user']['blogs'] as $blog)
        {
            $tBlog = new TumblrBlog();
            $tBlog->name = $blog['name'];
            $tBlog->title = $blog['title'] ?: $blog['name'];
            $user->blogs[] = $tBlog;
        }

        return $user;
    }
}