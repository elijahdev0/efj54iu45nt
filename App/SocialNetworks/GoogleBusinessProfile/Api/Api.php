<?php

namespace FSPoster\App\SocialNetworks\GoogleBusinessProfile\Api;

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

    public function sendPost ( PostingData $postingData ) : string
	{
        $post = [];
        $post['summary'] = $postingData->message;
        $post['topicType'] = 'STANDARD';

        if ( ! empty( $postingData->uploadMedia ) )
        {
	        $post[ 'media' ][] = [
                'mediaFormat' => $postingData->uploadMedia[0]['type'] === 'video' ? 'VIDEO' : 'PHOTO',
                'sourceUrl'   => $postingData->uploadMedia[0]['url'],
            ];
        }

        if( ! empty( $postingData->link ) )
        {
            $post['callToAction'] = [
                'actionType' => $postingData->linkType
            ];

			if( $postingData->linkType !== 'CALL' ) {
				$post['callToAction']['url'] = $postingData->link;
			}
        }

        $posted = $this->apiRequest( 'POST', 'https://mybusiness.googleapis.com/v4/' . $postingData->accountId . '/' . $postingData->locationId . '/localPosts',  '', json_encode( $post ) );

        if ( isset( $posted[ 'status' ] ) && $posted[ 'status' ] === 'error' )
	        throw new $this->postException( $posted['error_msg'] );

        if ( isset( $posted[ 'state' ] ) && $posted[ 'state' ] === 'REJECTED' )
	        throw new $this->postException( fsp__( 'Error! The post rejected by Google Business Profile' ) );

        if( empty($posted[ 'searchUrl' ]) )
			throw new $this->postException( fsp__( 'You need to verify your Google Business location to share posts.' ) );

        $parsed_link = parse_url( $posted[ 'searchUrl' ] );
        parse_str( $parsed_link[ 'query' ], $params );

        return $params[ 'lpsid' ] . '&id=' . $params[ 'id' ];
	}

    public function apiRequest ( $HTTPMethod, $url, $data = [], $body = '' )
    {
        $options  = [];

        $HTTPMethod = strtoupper( $HTTPMethod ) === 'GET' ? 'GET' : 'POST';

        if ( ! empty( $this->proxy ) )
            $options[ 'proxy' ] = $this->proxy;

        if ( ! empty( $body ) )
            $options[ 'body' ] = is_array( $body ) ? json_encode( $body ) : $body;

        if ( ! empty( $data ) )
            $options[ 'query' ] = $data;

        if ( ! empty( $this->authData->accessToken ) )
        {
            $options[ 'headers' ] = [
                'Connection'                => 'Keep-Alive',
                'X-li-format'               => 'json',
                'Content-Type'              => 'application/json',
                'X-RestLi-Protocol-Version' => '2.0.0',
                'Authorization'             => 'Bearer ' . $this->authData->accessToken,
            ];
        }

        try
        {
            $client = new Client(['verify'=>false]);
            $response = $client->request( $HTTPMethod, $url, $options )->getBody();
        }
        catch ( BadResponseException $e )
        {
            $response = $e->getResponse()->getBody();
        }
        catch ( GuzzleException $e )
        {
            $response = $e->getMessage();
        }

        $response1 = json_decode( $response, true );

        if ( ! is_array( $response1 ) )
	        throw new $this->postException( (string)$response );
        else
            $response = $response1;

        if ( isset( $response[ 'error' ] ) )
        {
            $error_msg = 'Error';

            if ( isset( $response[ 'error' ][ 'status' ] ) && $response[ 'error' ][ 'status' ] === 'PERMISSION_DENIED' )
            {
                $error_msg = fsp__( 'You need to verify your locations to share posts on it' );
            }
            else if ( isset( $response[ 'error' ][ 'message' ] ) )
            {
                $error_msg = $response[ 'error' ][ 'message' ];
            }
            else if ( $response[ 'error_description' ] )
            {
                $error_msg = $response[ 'error_description' ];
            }

			throw new $this->postException( $error_msg );
        }

        return $response;
    }

    public function fetchAccessToken ( $code, $callbackUrl ) : Api
    {
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
			$client = new Client(['verify'=>false]);
            $tokenInfo = $client->post( 'https://oauth2.googleapis.com/token', $options )->getBody()->getContents();
		}
		catch ( Exception $e )
		{
            throw new $this->authException( $e->getMessage() );
		}

	    $tokenInfo = json_decode( $tokenInfo, true );

	    if ( ! ( isset( $tokenInfo[ 'access_token' ] ) && isset( $tokenInfo[ 'refresh_token' ] ) ) )
		    throw new $this->authException( fsp__( 'Failed to get access token' ) );

		$this->authData->accessToken = $tokenInfo[ 'access_token' ];
		$this->authData->refreshToken = $tokenInfo[ 'refresh_token' ];
		$this->authData->accessTokenExpiresOn = Date::dateTimeSQL( 'now', '+55 minutes' );

        return $this;
	}

	public function getMyAccounts()
	{
		$accounts = self::apiRequest( 'GET', 'https://mybusinessaccountmanagement.googleapis.com/v1/accounts' );

		return $accounts[ 'accounts' ] ?? [];
	}

    public function getLocations( $accountId )
    {
		$allLocations = [];

        do
        {
            $queryData = [
                'readMask' => 'title,name',
                'pageSize' => 100,
            ];

            if ( ! empty( $nextPages ) )
                $queryData[ 'pageToken' ] = $nextPages;

            $response = $this->apiRequest( 'GET', 'https://mybusinessbusinessinformation.googleapis.com/v1/' . $accountId . '/locations', $queryData );

            $locations = $response[ 'locations' ] ?? [];
            $nextPages = $response[ 'nextPageToken' ] ?? false;

			$allLocations = array_merge( $allLocations, $locations );
        } while ( ! empty( $nextPages ) );

        return $allLocations;
    }

    private function refreshAccessTokenIfNeed()
    {
	    if (  ! empty( $this->authData->accessTokenExpiresOn ) && ( Date::epoch() + 30 ) > Date::epoch( $this->authData->accessTokenExpiresOn ) )
	    {
		    $this->refreshAccessToken();
	    }
    }

    private function refreshAccessToken () : void
	{
        $options = [
            'query' => [
                'client_id'     => $this->authData->appClientId,
                'client_secret' => $this->authData->appClientSecret,
                'grant_type'    => 'refresh_token',
                'refresh_token' => $this->authData->refreshToken,
            ],
        ];

        $options[ 'proxy' ] = empty($this->proxy) ? null : $this->proxy;

        try
        {
	        $client = new Client(['verify'=>false]);
            $refreshed_token = $client->post( 'https://oauth2.googleapis.com/token', $options )->getBody()->getContents();
        }
        catch ( Exception $e )
        {
            throw new $this->authException( $e->getMessage() );
        }

		$refreshed_token = json_decode( $refreshed_token, true );

        if( empty( $refreshed_token[ 'access_token' ] ) )
            throw new $this->authException();

		$this->authData->accessToken = $refreshed_token[ 'access_token' ];
		$this->authData->accessTokenExpiresOn = Date::dateTimeSQL( 'now', '+55 minutes' );
	}

	public static function getAuthURL ( $appClientId, $callbackUrl ) : string
	{
		$authURL = 'https://accounts.google.com/o/oauth2/auth';

		$scopes = [
			'https://www.googleapis.com/auth/business.manage',
			'https://www.googleapis.com/auth/userinfo.profile',
			'email',
			'profile',
		];

		$params = [
			'response_type' => 'code',
			'access_type'   => 'offline',
			'client_id'     => $appClientId,
			'redirect_uri'  => $callbackUrl,
			'state'         => null,
			'scope'         => implode( ' ', $scopes ),
			'prompt'        => 'consent',
		];

		return $authURL . '?' . http_build_query( $params, '', '&', PHP_QUERY_RFC3986 );
	}

}
