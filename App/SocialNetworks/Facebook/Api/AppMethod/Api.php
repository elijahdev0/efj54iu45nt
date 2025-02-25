<?php

namespace FSPoster\App\SocialNetworks\Facebook\Api\AppMethod;

use FSPoster\App\Providers\Helpers\Curl;
use FSPoster\App\Providers\Helpers\Date;
use FSPoster\App\SocialNetworks\Facebook\Api\PostingData;

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

    private function getAppSecretProof ( string $accessToken )
    {
        if ( empty( $this->authData->appClientSecret ) )
        {
            return null;
        }

        return hash_hmac( 'sha256', $accessToken, $this->authData->appClientSecret );
    }

    public function sendPost ( PostingData $postingData ): string
    {
        $postData = [ 'message' => $postingData->message ];
        $endPoint = 'feed';

	    if ( ! empty( $postingData->link ) )
	    {
		    $postData[ 'link' ] = $postingData->link;
	    }
		else if( ! empty( $postingData->uploadMedia ) )
		{
			if ( $postingData->uploadMedia[0]['type'] === 'image' )
			{
				$images = $postingData->uploadMedia;
				$postData[ 'attached_media' ] = [];

				foreach ( $images as $imageMedia )
				{
					$uploadedImage = $this->uploadPhoto( $postingData->ownerId, $imageMedia['url'] );

					if ( $uploadedImage !== false )
						$postData[ 'attached_media' ][] = json_encode( [ 'media_fbid' => $uploadedImage ] );
				}
			}
			else if ( $postingData->uploadMedia[0]['type'] === 'video' )
			{
				$endPoint = 'videos';
				$postData = [
					'file_url'    => $postingData->uploadMedia[0]['url'],
					'description' => $postingData->message,
				];
			}
		}

        $result = $this->apiRequest( '/' . $postingData->ownerId . '/' . $endPoint, 'POST', $postData );

        if ( isset( $result[ 'error' ] ) )
            throw new $this->postException( $result[ 'error' ][ 'message' ] ?? fsp__( 'Error' ) );

	    $stsId = '0';
        if ( isset( $result[ 'id' ] ) )
        {
            $stsId = explode( '_', $result[ 'id' ] );
            $stsId = end( $stsId );

			if( ! empty( $postingData->firstComment ) )
				$this->writeComment( (string)$result[ 'id' ], $postingData->firstComment );
        }

        return (string)$stsId;
    }

    private function uploadPhoto ( $channelRemoteId, $imageURL )
    {
        if ( 0 === substr_compare( $imageURL, '.gif', -strlen( '.gif' ), strlen( '.gif' ) ) )
            throw new $this->postException( fsp__( 'Uploading in GIF format is not supported.' ) );

        $imageUpload = $this->apiRequest( '/' . $channelRemoteId . '/photos', 'POST', [
            'url'       => $imageURL . '?_r=' . uniqid(),
            'published' => 'false',
            'caption'   => '',
        ] );

        if ( isset( $imageUpload[ 'error' ] ) || !isset( $imageUpload[ 'id' ] ) )
        {
            $error_msg = $imageUpload[ 'error' ][ 'message' ] ?? $imageUpload[ 'error' ][ 'error_user_msg' ] ?? fsp__( 'The post can\'t be shared' );

            throw new $this->postException( $error_msg );
        }

        return $imageUpload[ 'id' ];
    }

	public function writeComment ( string $postId, string $message )
	{
		$postData = [
			'message' => $message
		];

		$this->apiRequest( sprintf( '/%s/comments', $postId ), 'POST', $postData );
	}

    public function apiRequest ( $endpoint, $HTTPMethod, array $data = [] )
    {
        $data[ 'access_token' ] = $this->authData->accessToken;

        if ( !empty( $this->getAppSecretProof( $data[ 'access_token' ] ) ) )
            $data[ 'appsecret_proof' ] = $this->getAppSecretProof( $data[ 'access_token' ] );

        $url        = 'https://graph.facebook.com' . $endpoint; //. '?' . http_build_query( $data );
        $HTTPMethod = $HTTPMethod === 'POST' ? 'POST' : ( $HTTPMethod === 'DELETE' ? 'DELETE' : 'GET' );
        $data1      = Curl::getContents( $url, $HTTPMethod, $data, [], $this->proxy ?? null, true, false );
        $data       = json_decode( $data1, true );

        if ( ! is_array( $data ) )
	        throw new $this->authException( 'Error data! (' . $data1 . ')' );
        else if ( isset( $data[ 'error' ][ 'message' ] ) && strpos( $data[ 'error' ][ 'message' ], '(#200)' ) !== false )
            throw new $this->authException( 'Insufficient permission. <a href=\'https://www.fs-poster.com/documentation/commonly-encountered-issues#issue5\' target=\'_blank\'>Learn more!</a>' );
		else if ( isset( $data[ 'error' ][ 'code' ] ) && $data[ 'error' ][ 'code' ] === 190 )
            throw new $this->authException( 'The channel has been disconnected' );

        return $data;
    }

    public function fetchAccessToken ( $code, $callbackUrl ): Api
    {
        $appSecret = $this->authData->appClientSecret;
        $appId     = $this->authData->appClientId;

        $token_url = "https://graph.facebook.com/oauth/access_token?" . "client_id=" . urlencode( $appId ) . "&redirect_uri=" . urlencode( $callbackUrl ) . "&client_secret=" . urlencode( $appSecret ) . "&code=" . urlencode( $code );

        $response = Curl::getURL( $token_url, $this->proxy );

        $params = json_decode( $response, true );

        if ( isset( $params[ 'error' ][ 'message' ] ) )
            throw new $this->authException( $params[ 'error' ][ 'message' ] );

		$this->authData->accessToken = $params[ 'access_token' ];
		$this->authData->accessTokenExpiresOn = $this->getAccessTokenExpiresDate();

        return $this;
    }

	public function getMe ()
	{
		$me = $this->apiRequest( '/me', 'GET', [ 'fields' => 'id,name' ] );

		if( ! isset( $me[ 'id' ] ) )
			throw new $this->authException( $me[ 'error' ][ 'message' ] ?? 'Unknown error!' );

		return $me;
	}

    public function getAccessTokenExpiresDate (): ?string
    {
        $url = sprintf( 'https://graph.facebook.com/v13.0/debug_token?input_token=%s&access_token=%s|%s', urlencode( $this->authData->accessToken ), urlencode( $this->authData->appClientId ), urlencode( $this->authData->appClientSecret ) );
        $exp = Curl::getContents( $url, 'GET', [], [], $this->proxy );

        $data = json_decode( $exp, true );

        return is_array( $data ) && isset( $data[ 'data' ][ 'data_access_expires_at' ] ) ? Date::dateTimeSQL( $data[ 'data' ][ 'data_access_expires_at' ] ) : null;
    }

	/**
	 * @return array
	 */
    public function fetchPages (): array
    {
        $pages = [];

        $accounts_list = $this->apiRequest( '/me/accounts', 'GET', [
            'fields' => 'access_token,category,name,id',
            'limit'  => 100,
        ] );

        // If Facebook Developer APP doesn't approved for Business use... ( set limit 3 )
        if ( isset( $accounts_list[ 'error' ][ 'code' ] ) && $accounts_list[ 'error' ][ 'code' ] === '4' && isset( $accounts_list[ 'error' ][ 'error_subcode' ] ) && $accounts_list[ 'error' ][ 'error_subcode' ] === '1349193' )
        {
            $accounts_list = $this->apiRequest( '/me/accounts', 'GET', [
                'fields' => 'access_token,category,name,id',
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
            $accounts_list = $this->apiRequest( '/me/accounts', 'GET', [
                'fields' => 'access_token,category,name,id',
                'limit'  => 100,
                'after'  => $accounts_list[ 'paging' ][ 'cursors' ][ 'after' ],
            ] );

            if ( isset( $accounts_list[ 'data' ] ) )
            {
                $pages = array_merge( $pages, $accounts_list[ 'data' ] );
            }
        }

        return $pages;
    }

	public function fetchGroups(): array
	{
		return [];
	}

    /**
     * Get post statistics (e.g. likes, comments, shares, etc.)
     */
    public function getStats ( string $ownerId, string $postId ): array
    {
        $insights = $this->apiRequest( '/' . $ownerId . '_' . $postId, 'GET', [
            'fields' => 'reactions.type(LIKE).limit(0).summary(total_count).as(like),reactions.type(LOVE).summary(total_count).limit(0).as(love),reactions.type(WOW).summary(total_count).limit(0).as(wow),reactions.type(HAHA).summary(total_count).limit(0).as(haha),reactions.type(SAD).summary(total_count).limit(0).as(sad),reactions.type(ANGRY).summary(total_count).limit(0).as(angry),comments.limit(0).summary(true),sharedposts.limit(5000).summary(true)',
        ] );

        $reactions = [
            'like'  => $insights[ 'like' ][ 'summary' ][ 'total_count' ] ?? 0,
            'love'  => $insights[ 'love' ][ 'summary' ][ 'total_count' ] ?? 0,
            'wow'   => $insights[ 'wow' ][ 'summary' ][ 'total_count' ] ?? 0,
            'haha'  => $insights[ 'haha' ][ 'summary' ][ 'total_count' ] ?? 0,
            'sad'   => $insights[ 'sad' ][ 'summary' ][ 'total_count' ] ?? 0,
            'angry' => $insights[ 'angry' ][ 'summary' ][ 'total_count' ] ?? 0,
        ];

        $details = fsp__( 'Like: ' ) . $reactions[ 'like' ] . "\n";
        $details .= fsp__( 'Love: ' ) . $reactions[ 'love' ] . "\n";
        $details .= fsp__( 'Wow: ' ) . $reactions[ 'wow' ] . "\n";
        $details .= fsp__( 'Haha: ' ) . $reactions[ 'haha' ] . "\n";
        $details .= fsp__( 'Sad: ' ) . $reactions[ 'sad' ] . "\n";
        $details .= fsp__( 'Angry: ' ) . $reactions[ 'angry' ];

        $likesSum = $reactions[ 'like' ] + $reactions[ 'love' ] + $reactions[ 'wow' ] + $reactions[ 'haha' ] + $reactions[ 'sad' ] + $reactions[ 'angry' ];

        return [
            [
                'label' => fsp__( 'Likes' ),
                'value' => $likesSum,
            ],
            [
                'label' => fsp__( 'Details' ),
                'value' => $details,
            ],
            [
                'label' => fsp__( 'Comments' ),
                'value' => $insights[ 'comments' ][ 'summary' ][ 'total_count' ] ?? 0,
            ],
            [
                'label' => fsp__( 'Shares' ),
                'value' => isset( $insights[ 'sharedposts' ][ 'data' ] ) ? count( $insights[ 'sharedposts' ][ 'data' ] ) : 0,
            ],
        ];
    }

    public function fetchComments ( $ownerId, $postID, $since = '' ): array
    {
        $url      = sprintf( '/%s_%s/comments', $ownerId, $postID );
        $sendData = [
            'since'  => $since,
            'filter' => 'stream',
            'limit'  => 100,
            'fields' => 'parent{id},created_time,message,id,from{name},attachment{media{image{src},source},type,target{url}}',
        ];

        $comments = [];

        do
        {
            $response = $this->apiRequest( $url, 'GET', $sendData );

            if ( empty( $response[ 'data' ] ) )
            {
                break;
            }

            $comments = array_merge( $comments, $response[ 'data' ] );

            if ( !empty( $response[ 'paging' ][ 'cursors' ][ 'after' ] ) )
            {
                $sendData[ 'after' ] = $response[ 'paging' ][ 'cursors' ][ 'after' ];
            }
        } while ( !empty( $response[ 'paging' ][ 'cursors' ][ 'after' ] ) );

        return $comments;
    }

	public static function checkApp ( $appId, $appSecret )
	{
		$getInfo = json_decode( Curl::getContents( 'https://graph.facebook.com/' . urlencode( $appId ) . '?fields=permissions{permission},roles,name,link,category&access_token=' . urlencode( $appId ) . '|' . urlencode( $appSecret ) ), true );

		if ( empty( $getInfo ) || !is_array( $getInfo ) || !empty( $getInfo[ 'error' ] ) )
			return false;

		return true;
	}

	public static function getAuthURL ( $appClientId, $callbackUrl ): string
	{
		$permissions = [
			'public_profile',
			'pages_manage_posts',
			'business_management',
			'pages_manage_engagement'
		];

		$permissions = implode( ',', array_map( 'urlencode', $permissions ) );

		$callbackUrl = urlencode( $callbackUrl );

		return "https://www.facebook.com/dialog/oauth?redirect_uri=$callbackUrl&scope=$permissions&response_type=code&client_id=" . urlencode( $appClientId );
	}

}
