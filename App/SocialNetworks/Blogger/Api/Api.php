<?php

namespace FSPoster\App\SocialNetworks\Blogger\Api;

use Exception;
use FSPoster\App\Providers\Helpers\Date;
use FSPoster\GuzzleHttp\Client;
use FSPoster\GuzzleHttp\Exception\BadResponseException;
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

        $this->refreshAccessTokenIfNeed();

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

    public function sendPost ( PostingData $postingData ): array
    {
        $post[ 'kind' ]           = 'blogger#' . $postingData->kind;
        $post[ 'blog' ][ 'id' ]   = $postingData->blogId;
        $post[ 'title' ]          = $postingData->title;
        $post[ 'content' ]        = $postingData->content;
        $post[ 'author' ][ 'id' ] = $postingData->authorId;

        if ( !empty( $postingData->labels ) )
            $post[ 'labels' ] = implode( ',', $postingData->labels );

        $params = [ 'isDraft' => $postingData->isDraft ];

        $response = $this->apiRequest( "blogs/{$postingData->blogId}/{$postingData->kind}s", 'POST', $post, $params );

        if ( isset( $response[ 'status' ] ) && $response[ 'status' ] === 'error' )
			throw new $this->postException( $response[ 'error_msg' ] );

        if ( empty( $response[ 'id' ] ) )
	        throw new $this->postException( 'Unknown error' );

        if ( $postingData->isDraft || ! isset( $response[ 'url' ] ) )
        {
            $url = sprintf( 'https://www.blogger.com/blog/%s/edit/%s/%s', $postingData->kind, $postingData->blogId, $response[ 'id' ] );
        } else
        {
            $url = $response[ 'url' ];
        }

        return [
	        'id'        => $response[ 'id' ],
	        'url'       => $url
        ];
    }

    public function apiRequest ( $endpoint, $HTTPMethod = 'GET', $body = '', $params = [] )
    {
        $api = $endpoint === 'userinfo' ? 'oauth2' : 'blogger';
        $url = 'https://www.googleapis.com/' . $api . '/v3/' . $endpoint;

        $options = [];

        if ( !empty( $body ) )
            $options[ 'body' ] = is_array( $body ) ? json_encode( $body ) : $body;

        if ( ! empty( $params ) )
            $options[ 'query' ] = $params;

        $options[ 'headers' ] = [
            'Connection'                => 'Keep-Alive',
            'X-li-format'               => 'json',
            'Content-Type'              => 'application/json',
            'X-RestLi-Protocol-Version' => '2.0.0',
            'Authorization'             => 'Bearer ' . $this->authData->accessToken,
        ];

        if ( !empty( $this->proxy ) )
            $options[ 'proxy' ] = $this->proxy;

        $client = new Client();

        try
        {
            $response = $client->request( $HTTPMethod, $url, $options )->getBody();
        } catch ( BadResponseException $e )
        {
            $response = $e->getResponse()->getBody();
        } catch ( GuzzleException $e )
        {
            $response = $e->getMessage();
        }

        $response = json_decode( $response, true );

        if ( ! is_array( $response ) || empty( $response ) )
            throw new $this->postException( 'Request error!' );

        $this->handleResponseError( $response );

        return $response;
    }

	private function handleResponseError ( $response )
	{
		if ( isset( $response[ 'error' ] ) )
		{
			if ( isset( $response[ 'error' ][ 'status' ] ) && $response[ 'error' ][ 'status' ] === 'PERMISSION_DENIED' )
				throw new $this->postException( 'You need to check all the required checkboxes to add your account to the plugin.' );
			else if ( isset( $response[ 'error' ][ 'message' ] ) )
				throw new $this->postException( $response[ 'error' ][ 'message' ] );
			else if ( isset( $response[ 'error_description' ] ) )
				throw new $this->postException( $response[ 'error_description' ] );
			else
				throw new $this->postException( 'Error' );
		}
	}

    public function fetchAccessToken ( $code, $callbackUrl ): Api
    {
        if( empty( $code ) )
	        throw new $this->authException('Code is empty');

	    $client = new Client();

	    $options = [
		    'query' => [
			    'client_id'     => $this->authData->appClientId,
			    'client_secret' => $this->authData->appClientSecret,
			    'code'          => $code,
			    'grant_type'    => 'authorization_code',
			    'redirect_uri'  => $callbackUrl,
		    ],
	    ];

	    if ( ! empty( $this->proxy ) )
		    $options[ 'proxy' ] = $this->proxy;

        try
        {
            $tokenInfo = $client->post( 'https://oauth2.googleapis.com/token', $options )->getBody()->getContents();
            $tokenInfo = json_decode( $tokenInfo, true );
        } catch ( Exception $e )
        {
            throw new $this->authException( 'Failed to get access token' );
        }

	    if ( !( isset( $tokenInfo[ 'access_token' ] ) && isset( $tokenInfo[ 'refresh_token' ] ) ) )
		    throw new $this->authException( 'Failed to get access token' );

		$this->authData->accessToken = $tokenInfo[ 'access_token' ];
		$this->authData->refreshToken = $tokenInfo[ 'refresh_token' ];
		$this->authData->accessTokenExpiresOn = Date::dateTimeSQL( 'now', '+55 minutes' );

	    return $this;
    }

	public function getMyInfo()
	{
		$bloggerInfo = $this->apiRequest( 'users/self' );

		if ( isset( $bloggerInfo[ 'status' ] ) && $bloggerInfo[ 'status' ] === 'error' )
			throw new $this->authException( $bloggerInfo[ 'error_msg' ] );

		$googleInfo = $this->apiRequest( 'userinfo' );

		if ( isset( $googleInfo[ 'status' ] ) && $googleInfo[ 'status' ] === 'error' )
			throw new $this->authException( $googleInfo[ 'error_msg' ] );

		return $bloggerInfo;
	}

	public function getBlogsList (): array
	{
		$blogs = self::apiRequest( 'users/self/blogs' );

		if ( empty( $blogs[ 'items' ] ) )
			throw new $this->authException( 'No blogs found!' );

		return $blogs[ 'items' ];
	}

	public function refreshAccessTokenIfNeed(): void
	{
		if ( ! empty( $this->authData->accessTokenExpiresOn ) && ( Date::epoch() + 30 ) > Date::epoch( $this->authData->accessTokenExpiresOn ) )
			$this->refreshAccessToken();
	}

    public function refreshAccessToken (): Api
    {
        $client = new Client();

        $options = [
            'query' => [
                'client_id'     => $this->authData->appClientId,
                'client_secret' => $this->authData->appClientSecret,
                'grant_type'    => 'refresh_token',
                'refresh_token' => $this->authData->refreshToken,
            ],
        ];

        if ( !empty( $this->proxy ) )
        {
            $options[ 'proxy' ] = $this->proxy;
        }

        $refreshed_token = $client->post( 'https://oauth2.googleapis.com/token', $options )->getBody()->getContents();
        $refreshed_token = json_decode( $refreshed_token, true );

        if ( empty( $refreshed_token[ 'access_token' ] ) )
            throw new $this->authException();

        $expiresOn    = Date::dateTimeSQL( 'now', '+55 minutes' );
        $access_token = $refreshed_token[ 'access_token' ];

		$this->authData->accessToken = $access_token;
		$this->authData->accessTokenExpiresOn = $expiresOn;

        return $this;
    }

	public static function getAuthURL ( $clientId, $callbackUrl ): string
	{
		$authURL = 'https://accounts.google.com/o/oauth2/auth';

		$scopes = [
			'https://www.googleapis.com/auth/blogger',
			'email',
			'profile',
		];

		$params = [
			'response_type' => 'code',
			'access_type'   => 'offline',
			'client_id'     => $clientId,
			'redirect_uri'  => $callbackUrl,
			'state'         => null,
			'scope'         => implode( ' ', $scopes ),
			'prompt'        => 'consent',
		];

		return $authURL . '?' . http_build_query( $params, '', '&', PHP_QUERY_RFC3986 );
	}

}