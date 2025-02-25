<?php

namespace FSPoster\App\SocialNetworks\Instagram\Api\AppMethod;

use Exception;
use FSPoster\App\Providers\Helpers\Curl;
use FSPoster\App\Providers\Helpers\Date;
use FSPoster\App\Providers\Schedules\ScheduleResponseObject;
use FSPoster\App\SocialNetworks\Instagram\Api\PostingData;
use FSPoster\GuzzleHttp\Client;
use FSPoster\GuzzleHttp\Exception\GuzzleException;

class Api
{
	public AuthData $authData;
	public ?string  $proxy = null;

	public string $authException = \Exception::class;
	public string $postException = \Exception::class;

	public function setProxy ( ?string $proxy ): self
	{
		$this->proxy = $proxy;

		return $this;
	}

	public function setAuthData ( AuthData $authData ): self
	{
		$this->authData = $authData;

		return $this;
	}

	public function setAuthException ( string $exceptionClass ): self
	{
		$this->authException = $exceptionClass;

		return $this;
	}

	public function setPostException ( string $exceptionClass ): self
	{
		$this->postException = $exceptionClass;

		return $this;
	}

    public function sendPost( PostingData $postingData ) : ScheduleResponseObject
    {
        if( $postingData->edge === 'story' )
        {
	        if( $postingData->uploadMedia[0]['type'] === 'video' )
		        $response = self::sendStoryVideo( $postingData->ownerId, $postingData->uploadMedia[0] );
			else
				$response = self::sendStoryImages( $postingData->ownerId, $postingData->uploadMedia[0] );
        }
        else
        {
            if( $postingData->uploadMedia[0]['type'] === 'video' )
	            $response = $this->uploadVideo( $postingData->ownerId, $postingData->uploadMedia[0], $postingData->message );
            else if( count( $postingData->uploadMedia ) === 1 )
	            $response = $this->uploadPhoto( $postingData->ownerId, $postingData->uploadMedia[0], $postingData->message );
            else
	            $response = $this->generateAlbum( $postingData->ownerId, $postingData->uploadMedia, $postingData->message );
        }

	    $snPostResponse = new ScheduleResponseObject();
        $snPostResponse->status = 'success';
        $snPostResponse->remote_post_id = $response['id2'];
        $snPostResponse->data = [
            'url' => 'https://instagram.com/p/' . $response['id']
        ];

        $ids     = explode( '_', $response['id2'] );
        $mediaId = count( $ids ) > 1 ? $ids[0] : $response['id2'];

        if ( ! empty( $mediaId ) && $postingData->edge !== 'story' && ! empty( $postingData->firstComment ) )
            $this->writeComment( $postingData->firstComment, $mediaId );

        return $snPostResponse;
    }

	public function uploadPhoto ( $ownerId, $photo, $message ) : array
    {
		return $this->singleUpload( $ownerId, [
            'image_url' => $photo['url'],
            'caption'   => $message
        ]);
	}

	private function checkUploadStatus ( $uploadID ) : bool
    {
		set_time_limit( 0 );
		$retries = 0;

		while ( $retries < 30 )
		{
			$status = $this->apiRequest( $uploadID, 'GET', [ 'fields' => 'status_code' ] );

			if ( ! isset( $status[ 'status_code' ] ) || in_array( $status[ 'status_code' ], [ 'EXPIRED', 'ERROR' ] ) )
			{
				return false;
			}

			if ( $status[ 'status_code' ] == 'IN_PROGRESS' )
			{
				sleep( 3 );
				$retries++;
			}
			else
			{
				break;
			}
		}

		return true;
	}

	public function uploadCarouselItem ( $ownerId, $url )
	{
		return self::apiRequest( $ownerId . '/media', 'POST', [
			'image_url'        => $url,
			'is_carousel_item' => 'true',
		] );
	}

	public function createCarouselContainer ( $ownerId, $caption, $children )
	{
		return self::apiRequest( $ownerId . '/media', 'POST', [
			'media_type' => 'CAROUSEL',
			'caption'    => $caption,
			'children'   => implode( ",", $children ),
		] );
	}

    /**
     * @throws Exception
     */
    private function sendStoryImages( $ownerId, $image ): array
    {
        $res = $this->singleUpload( $ownerId, [
            'media_type' => 'STORIES',
            'image_url'  => $image['url']
        ]);

        return $res;
    }

    /**
     * @throws Exception
     */
    private function sendStoryVideo( $ownerId, $video ): array
    {
        return $this->singleUpload( $ownerId, [
            'media_type' => 'STORIES',
            'video_url'  => $video['url']
        ]);
    }

    private function singleUpload( $ownerId, $options ): array
    {
        $upload = $this->apiRequest( $ownerId . '/media', 'POST', $options );

        if ( isset( $upload[ 'error' ] ) )
            throw new $this->postException( ($upload['error']['message'] ?? 'Error!') . ($upload['error']['error_user_msg'] ?? '') );

        if ( empty( $upload[ 'id' ] ) )
	        throw new $this->postException( 'Error' );

		if( ! $this->checkUploadStatus( $upload['id'] ) )
			throw new $this->postException( 'Instagram did not accept the media you wanted to upload. It is possible that this media does not comply with its Content Publishing Guidelines.' . sprintf('[Upload ID: %s]', (string)$upload['id']) );

        $creation = $this->apiRequest( $ownerId . '/media_publish', 'POST', [ 'creation_id' => $upload['id'] ] );

        if ( isset( $creation[ 'error' ] ) )
	        throw new $this->postException( ($creation['error']['message'] ?? 'Error!') . ($creation['error']['error_user_msg'] ?? '') );

        if ( empty( $creation[ 'id' ] ) )
	        throw new $this->postException( 'Error' );

        $shortcode = $this->apiRequest( $creation[ 'id' ], 'GET', [ 'fields' => 'shortcode' ] );

        if ( isset( $shortcode[ 'error' ] ) )
	        throw new $this->postException( ($shortcode['error']['message'] ?? 'Error!') . ($shortcode['error']['error_user_msg'] ?? '') );

        return [
            'id'     => $shortcode[ 'shortcode' ],
            'id2'    => $creation[ 'id' ],
        ];
    }

	public function generateAlbum ( $ownerId, $photos, $caption ) : array
    {
		$children = [];

		foreach ( $photos as $photo )
		{
			$response = $this->uploadCarouselItem( $ownerId, $photo['url'] );

			if ( isset( $response[ 'error' ] ) )
				throw new $this->postException( ($response['error']['message'] ?? 'Error!') . ($response['error']['error_user_msg'] ?? '') );

			if ( empty( $response[ 'id' ] ) )
				throw new $this->postException( 'Error' );

			$children[] = $response[ "id" ];
		}

		foreach ( $children as $child )
		{
			if ( ! $this->checkUploadStatus( $child ) )
				throw new $this->postException( 'Error' );
		}

		$carouselContainerResponse = $this->createCarouselContainer( $ownerId, $caption, $children );

		if ( empty( $carouselContainerResponse[ 'id' ] ) )
			throw new $this->postException( 'Error' );

		$publishResponse = $this->apiRequest( $ownerId . '/media_publish', 'POST', [
			'creation_id' => $carouselContainerResponse[ 'id' ],
		] );

		if ( isset( $publishResponse[ 'error' ] ) )
			throw new $this->postException( $publishResponse[ 'error' ][ 'message' ] ?? 'Error!' );

		if ( empty( $publishResponse[ 'id' ] ) )
			throw new $this->postException( 'Error' );

		$shortcode = $this->apiRequest( $publishResponse[ 'id' ], 'GET', [ 'fields' => 'shortcode' ] );

		if ( isset( $shortcode[ 'error' ] ) )
			throw new $this->postException( $shortcode[ 'error' ][ 'message' ] ?? 'Error!' );

		return [
			'id'     => $shortcode[ 'shortcode' ],
			'id2'    => $publishResponse[ 'id' ],
		];
	}

	public function uploadVideo ( $ownerId, $video, $message ) : array
    {
		return $this->singleUpload( $ownerId, [
            'media_type' => 'REELS',
            'video_url'  => $video['url'],
            'caption'    => $message,
        ]);
	}

    /**
     * @throws Exception
     */
    public static function checkApp ( $appId, $appSecret )
	{
		$getInfo = json_decode( Curl::getContents( 'https://graph.facebook.com/' . urlencode( $appId ) . '?fields=permissions{permission},roles,name,link,category&access_token=' . urlencode( $appId ) . '|' . urlencode( $appSecret ) ), true );

        if ( empty( $getInfo ) || ! is_array( $getInfo ) || ! empty( $getInfo[ 'error' ] ) )
            return false;

		return true;
	}

	/**
	 * Fetch login URL...
	 */
	public static function getAuthURL ( $appClientId, $callbackUrl ) : string
    {
		$permissions = [
			'instagram_basic',
			'business_management',
			'instagram_content_publish',
			'instagram_manage_comments',
			'instagram_manage_insights',
			'pages_show_list',
		];

		$permissions = implode( ',', array_map( 'urlencode', $permissions ) );

        return sprintf('https://www.facebook.com/dialog/oauth?redirect_uri=%s&scope=%s&response_type=code&client_id=%s', urlencode( $callbackUrl ), $permissions, urlencode( $appClientId ));
	}

	public function apiRequest ( $endpoint, $method, array $data = [] )
	{
		$data[ 'access_token' ] = $this->authData->accessToken;
        $url = 'https://graph.facebook.com/' . $endpoint;
		$method = $method === 'POST' ? 'POST' : ( $method === 'DELETE' ? 'DELETE' : 'GET' );
		$client = new Client(['verify' => false]);

		try
		{
			$response = $client->request( $method, $url, [
                'query' => $data,
                'proxy' => empty( $this->proxy ) ? null : $this->proxy,
            ] )->getBody();
			$response = json_decode( $response, true );
		}
		catch ( GuzzleException $e )
		{
			$body = json_decode( $e->getResponse()->getBody()->getContents(), true );
			throw new $this->postException( ($body['error']['message'] ?? $e->getMessage()) . ($body['error']['error_user_msg'] ?? '') );
		}

		if ( ! is_array( $response ) )
			throw new $this->postException( 'Error!' );

		return $response;
	}

	public function fetchInstagramAccounts () : array
    {
		$pages = [];

		$accounts_list = $this->apiRequest( 'me/accounts', 'GET', [
			'fields' => 'id,instagram_business_account{id,name,username,profile_picture_url}',
			'limit'  => 100,
		] );

		// If Facebook Developer APP doesn't approved for Business use... ( set limit 3 )
		if ( isset( $accounts_list[ 'error' ][ 'code' ] ) && $accounts_list[ 'error' ][ 'code' ] === '4' && isset( $accounts_list[ 'error' ][ 'error_subcode' ] ) && $accounts_list[ 'error' ][ 'error_subcode' ] === '1349193' )
		{
			$accounts_list = $this->apiRequest( 'me/accounts', 'GET', [
				'fields' => 'id,instagram_business_account{id,name,username,profile_picture_url}',
				'limit'  => '3',
			] );

			if ( isset( $accounts_list[ 'data' ] ) && is_array( $accounts_list[ 'data' ] ) )
			{
				$pages = $accounts_list[ 'data' ];
			}

			return $pages;
		}

		if ( isset( $accounts_list[ 'data' ] ) )
		{
			$pages = array_merge( $pages, $accounts_list[ 'data' ] );
		}

		// paginaeting...
		while ( isset( $accounts_list[ 'paging' ][ 'cursors' ][ 'after' ] ) )
		{
			$accounts_list = $this->apiRequest( 'me/accounts', 'GET', [
				'fields' => 'id,instagram_business_account{id,name,username,profile_picture_url}',
				'limit'  => 100,
				'after'  => $accounts_list[ 'paging' ][ 'cursors' ][ 'after' ],
			] );

			if ( isset( $accounts_list[ 'data' ] ) )
			{
				$pages = array_merge( $pages, $accounts_list[ 'data' ] );
			}
		}

		$instagramAccounts = array_filter( $pages, fn ($account) => !empty( $account[ 'instagram_business_account' ] ) );

		return array_column( $instagramAccounts, 'instagram_business_account' );
	}

    public function fetchAccessToken ( $code, $callbackUrl ) : Api
    {
	    $appSecret = $this->authData->appClientSecret;
	    $appId     = $this->authData->appClientId;

		$token_url = "https://graph.facebook.com/oauth/access_token?" . "client_id=" . urlencode( $appId ) . "&redirect_uri=" . urlencode( $callbackUrl ) . "&client_secret=" . urlencode( $appSecret ) . "&code=" . urlencode( $code );

		$response = Curl::getURL( $token_url, $this->proxy );

		$params = json_decode( $response, true );

		if ( isset( $params['error']['message'] ) )
            throw new $this->authException($params['error']['message'] . ($params['error']['error_user_msg'] ?? ''));

		$this->authData->accessToken = $params[ 'access_token' ];
		$this->authData->accessTokenExpiresOn = $this->getAccessTokenExpiresDate();

        return $this;
	}

	public function getMe ()
	{
		$me = $this->apiRequest( '/me', 'GET', [ 'fields' => 'id,name' ] );

		if( ! isset( $me[ 'id' ] ) )
			throw new $this->authException( ($me['error']['message'] ?? 'Unknown error!') . ($me['error']['error_user_msg'] ?? '') );

		return $me;
	}

	public function getAccessTokenExpiresDate () : ?string
    {
		$url = sprintf('https://graph.facebook.com/v13.0/debug_token?input_token=%s&access_token=%s|%s', urlencode( $this->authData->accessToken ), urlencode( $this->authData->appClientId ), urlencode( $this->authData->appClientSecret ));
        $exp = Curl::getContents( $url, 'GET', [], [], $this->proxy );

		$data = json_decode( $exp, true );

		return is_array( $data ) && isset( $data['data'][ 'data_access_expires_at' ] ) ? Date::dateTimeSQL( $data['data'][ 'data_access_expires_at' ] ) : null;
	}

	public function writeComment ( string $comment, string $mediaId ) : string
    {
		$endpoint = $mediaId . '/comments';

		try
		{
			$response = $this->apiRequest( $endpoint, 'POST', [ 'message' => $comment ] );
		}
		catch ( \Exception $e )
		{
			throw new $this->postException( 'First comment error: ' . $e->getMessage() );
		}

		if ( isset( $response[ 'error' ] ) || ! isset( $response[ 'id' ] ) )
			throw new $this->postException( 'First comment error: ' . (($response['error']['message'] ?? 'Error!') . ($response['error']['error_user_msg'] ?? '')) );

		return (string)$response[ 'id' ];
	}

    public function getStats( $postId ): array
    {
        $res = $this->apiRequest( $postId, 'GET', [
            'fields' => 'comments_count,like_count,is_comment_enabled',
        ]);

        $resp = [];

        if( isset( $res['like_count'] ) )
        {
            $resp[] = [
                'label' => fsp__('Likes'),
                'value' => $res['like_count'],
            ];
        }

        if( isset( $res['comments_count'] ) )
        {
            $resp[] = [
                'label' => fsp__('Comments'),
                'value' => $res['comments_count'],
            ];
        }

        return $resp;
    }

}