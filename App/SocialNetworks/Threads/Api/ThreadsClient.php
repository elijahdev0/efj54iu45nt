<?php

namespace FSPoster\App\SocialNetworks\Threads\Api;

use FSPoster\GuzzleHttp\Client;
use FSPoster\Psr\Http\Message\ResponseInterface;

class ThreadsClient
{

    private Client $httpClient;

    public ThreadsClientAuthData $authData;

    public ?string  $proxy = null;

    public string $authException = \Exception::class;
    public string $postException = \Exception::class;

    public function __construct($options)
    {
        $this->proxy = empty( $options['proxy'] ) ? null : $options['proxy'];
        $this->httpClient = new Client([
            'verify'        => false,
            'proxy'         => $this->proxy,
            'http_errors'   => false
        ]);

        $this->authData = new ThreadsClientAuthData();
    }

    /**
     * @throws \Exception
     */
    public function sendPost (PostingData $postingData ): array
    {
        $textContent = $postingData->message;
        $containers = [];

        // Other
        foreach ($postingData->uploadMedia as $media)
        {
            $data = [];

            if ( $media['type'] == 'image' )
            {
                $data['media_type'] = 'IMAGE';
                $data['image_url'] = $media['url'];
            }
            else if ( $media['type'] == 'video' )
            {
                $data['media_type'] = 'VIDEO';
                $data['video_url'] = $media['url'];
            }

            if ( count( $postingData->uploadMedia ) > 1 )
                $data['is_carousel_item'] = true;
            else
                $data['text'] = $textContent;

            $containers[] = [
                'id' => $this->createMediaContainer( $data ),
                'content' => $data
            ];
        }

        foreach ($containers as $container)
        {
            if ($container['content']['media_type'] != 'VIDEO')
                break;

            for ($i = 0; $i < 30; $i++)
            {
                $container = $this->getMediaContainer( $container['id'] );

                if ($container['status'] == 'IN_PROGRESS')
                {
                    sleep(10);
                    continue;
                }
                elseif ($container['status'] == 'FINISHED')
                {
                    break; // this is success branch
                }

                if (isset($container['error_message']))
                {
                    throw new \Exception($container['error_message']);
                }

                throw new \Exception('Unknown error occurred while checking status of Threads video media container.');
            }
        }

        if ( empty( $containers ) )
        {
            $content = [
                'media_type' => 'TEXT',
                'text' => $textContent
            ];

			if( ! empty( $postingData->link ) )
				$content['link_attachment'] = $postingData->link;

            $containers[] = [
                'id' => $this->createMediaContainer( $content ),
                'content' => $content
            ];
        }

        if ( count( $containers ) > 1 )
        {
            $parentContainerId = $this->createMediaContainer([
                'media_type' => 'CAROUSEL',
                'children' => implode(',', array_column($containers, 'id')),
                'text' => $textContent
            ]);
        }
        else
        {
            $parentContainerId = $containers[0]['id'];
        }

        $postId = $this->publishMediaContainer( $parentContainerId );
        $post = $this->getThreadsById( $postId );

        return $post;
    }

    public function exchangeCodeForShortLivedAccessToken($code, $redirectUri)
    {
        $res = $this->httpClient->post('https://graph.threads.net/oauth/access_token', [
            'json' => [
                'client_id' => $this->authData->clientId,
                'client_secret' => $this->authData->clientSecret,
                'grant_type' => 'authorization_code',
                'redirect_uri' => $redirectUri,
                'code' => $code
            ]
        ]);

        $body = json_decode((string) $res->getBody(), true);

        if (isset($body['access_token']) && isset($body['user_id']))
        {
            $this->authData->userId = $body['user_id'];
            $this->authData->userAccessToken = $body['access_token'];
            $this->authData->userAccessTokenExpiresAt = time() + 1 * 60 * 60; // +1 hour
        }

    }

    public function exchangeShortLivedForLongLivedAccessToken()
    {
        $res = $this->httpClient->get("https://graph.threads.net/access_token?grant_type=th_exchange_token&client_secret=" . $this->authData->clientSecret . "&access_token=" . $this->authData->userAccessToken);

        $body = json_decode((string) $res->getBody(), true);

        if (isset($body['access_token']) && isset($body['expires_in']))
        {
            $this->authData->userAccessToken = $body['access_token'];
            $this->authData->userAccessTokenExpiresAt = time() + $body['expires_in'];
        }
    }

    public function getMe()
    {
        $res = $this->requestWithAuth('GET', "https://graph.threads.net/v1.0/me?fields=id,username,name,threads_profile_picture_url,threads_biography");
        $body = json_decode((string) $res->getBody(), true);
        return $body;
    }

    public function prepare()
    {
        if ( $this->authData->userAccessTokenExpiresAt - time() < 86400 * 7 ) // 7 day in seconds
        {
            $this->refreshAccessToken();
        }
    }

    public function refreshAccessToken()
    {
        $res = $this->requestWithAuth('GET', "https://graph.threads.net/refresh_access_token?grant_type=th_refresh_token&access_token=" . $this->authData->userAccessToken);

        $body = json_decode((string) $res->getBody(), true);

        if (isset($body['access_token']) && isset($body['expires_in']))
        {
            $this->authData->userAccessToken = $body['access_token'];
            $this->authData->userAccessTokenExpiresAt = time() + $body['expires_in'];
        }
    }

    /**
     * Create media container and return its ID.
     *
     * Available parameters are below:
     * is_carousel_item => boolean
     * media_type => string, can be TEXT, IMAGE, VIDEO or CAROUSEL
     * image_url => string
     * video_url => string
     * text => string The text associated with the post.
     * children => string A comma-separated list of up to 10 container IDs
     * @throws \Exception
     */
    public function createMediaContainer($content): string
    {
        $res = $this->requestWithAuth('POST', "https://graph.threads.net/v1.0/{$this->authData->userId}/threads", [
            'json' => $content
        ]);

        $body = $this->decodeBodyFromResponse($res);

        if (isset($body['id']))
        {
            return $body['id'];
        }

        throw new \Exception('Unknown error occurred while creating media container for Threads');
    }

    public function getMediaContainer(string $containerId)
    {
        $res = $this->requestWithAuth('GET', "https://graph.threads.net/v1.0/{$containerId}?fields=status,error_message");
        $body = json_decode((string) $res->getBody(), true);

        return $body;
    }

    /**
     * @throws \Exception
     */
    public function publishMediaContainer(string $containerId): string
    {
        $res = $this->requestWithAuth('POST', "https://graph.threads.net/v1.0/{$this->authData->userId}/threads_publish?creation_id=" . $containerId);

        $body = $this->decodeBodyFromResponse($res);

        return $body['id'];
    }

    public function getThreadsById(string $threadsId)
    {
        $res = $this->requestWithAuth('GET', "https://graph.threads.net/v1.0/{$threadsId}?fields=id,shortcode,permalink");
        $body = json_decode((string) $res->getBody(), true);

        return $body;
    }

    private function requestWithAuth(string $method, $uri = '', array $options = [])
    {
        $options['headers']['Authorization'] = 'Bearer ' . $this->authData->userAccessToken;
        $res = $this->httpClient->request($method, $uri, $options);

        if ($res->getStatusCode() == 401) {
            throw new $this->authException();
        }

        return $res;
    }

    /**
     * @throws \Exception
     */
    private function decodeBodyFromResponse(ResponseInterface $res): array
    {
        $body = json_decode((string) $res->getBody(), true);

        if (isset($body['error']['error_user_msg']) || isset($body['error']['error_user_title']))
        {
            throw new \Exception($body['error']['error_user_title'] . ' ' . $body['error']['error_user_msg']);
        }
        else if (isset($body['error']['message']))
        {
            throw new \Exception($body['error']['message']);
        }
        else if (isset($body['error']))
        {
            throw new \Exception(json_encode($body));
        }

        return $body;
    }
}
