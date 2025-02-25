<?php

namespace FSPoster\App\SocialNetworks\Medium\Api;


use FSPoster\App\Providers\Helpers\Curl;
use FSPoster\App\Providers\Helpers\Date;
use FSPoster\GuzzleHttp\Client;

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

    public function sendPost ( PostingData $postingData ): string
    {
		$postData = [
			'title'         => $postingData->title,
			'contentFormat' => 'html',
			'content'       => '<h1>' . $postingData->title . '</h1>' . $postingData->message,
		];

		if ( ! empty( $postingData->tags ) )
			$postData['tags'] = $postingData->tags;

		if ( $postingData->channelType === 'account' )
            $endpoint = 'https://api.medium.com/v1/users/' . $postingData->channelId . '/posts';
		else
            $endpoint = 'https://api.medium.com/v1/publications/' . $postingData->channelId . '/posts';

		$result = $this->apiRequest( $endpoint, 'POST', $postData );

		if ( isset( $result[ 'errors' ][ 0 ][ 'message' ] ) )
			throw new $this->postException( $result[ 'errors' ][ 0 ][ 'message' ] );

		return (string)$result[ 'data' ][ 'id' ] ?? '0';
	}

	public function apiRequest ( string $apiURL, string $HTTPMethod, array $data = [] ) : array
	{
        $HTTPMethod = $HTTPMethod === 'POST' ? 'POST' : ( $HTTPMethod === 'DELETE' ? 'DELETE' : 'GET' );

		$data1 = Curl::getContents( $apiURL, $HTTPMethod, json_encode( $data ), [
			'Authorization'  => 'Bearer ' . $this->authData->accessToken,
			'Content-Type'   => 'application/json',
			'Accept'         => 'application/json',
			'Accept-Charset' => 'utf-8',
		], $this->proxy, false, false );

		$data = json_decode( $data1, true );

		if ( ! is_array( $data ) )
			throw new $this->postException('Unexpected response from the API. Please try again using a proxy or check your network settings.');

		return $data;
	}

	public function getMyInfo()
	{
		$me = $this->apiRequest( 'https://api.medium.com/v1/me', 'GET' );

		if( ! isset( $me['data']['id'] ) )
			throw new $this->authException( $me[ 'errors' ][ 0 ][ 'message' ] ?? 'Error' );

		return $me[ 'data' ];
	}

	public function getPublications( $channelId )
	{
		$publications = $this->apiRequest( 'https://api.medium.com/v1/users/' . $channelId . '/publications', 'GET' );

		if ( ! isset( $publications[ 'data' ] ) || ! is_array( $publications[ 'data' ] ) )
			throw new $this->authException( $me[ 'errors' ][ 0 ][ 'message' ] ?? 'Error' );

		return $publications[ 'data' ];
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
				'redirect_uri'  => $callbackUrl
			]
		];

		if ( ! empty( $this->proxy ) ) {
			$options[ 'proxy' ] = $this->proxy;
		}

		try {
			$tokenInfo = $client->post( 'https://api.medium.com/v1/tokens', $options )->getBody()->getContents();
			$tokenInfo = json_decode( $tokenInfo, true );
		} catch ( \Exception $e ) {
			throw new $this->authException( 'Failed to get access token' );
		}

		if ( !( isset( $tokenInfo['access_token'] ) && isset( $tokenInfo['refresh_token'] ) ) )
			throw new $this->authException( 'Failed to get access token' );

		$this->authData->accessToken = $tokenInfo['access_token'];
		$this->authData->refreshToken = $tokenInfo['refresh_token'];
		$this->authData->accessTokenExpiresOn = Date::dateTimeSQL( intval( $tokenInfo['expires_at'] / 1000 ) );

		return $this;
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
			]
		];

		if ( !empty( $this->proxy ) ) {
			$options[ 'proxy' ] = $this->proxy;
		}

		$refreshed_token = $client->post( 'https://api.medium.com/v1/tokens', $options )->getBody()->getContents();
		$refreshed_token = json_decode( $refreshed_token, true );

		if ( empty( $refreshed_token[ 'access_token' ] ) )
			throw new $this->authException();

		$this->authData->accessToken = $refreshed_token['access_token'];
		$this->authData->accessTokenExpiresOn = Date::dateTimeSQL( intval( $refreshed_token['expires_at'] / 1000 ) );

		return $this;
	}

	public static function getAuthURL ( $clientId, $callbackUrl ): string
	{
		$authURL = 'https://medium.com/m/oauth/authorize';

		$scopes = [
			'basicProfile',
			'publishPost',
			'listPublications',
		];

		$params = [
			'response_type' => 'code',
			'client_id'     => $clientId,
			'redirect_uri'  => $callbackUrl,
			'state'         => uniqid(),
			'scope'         => implode( ',', $scopes )
		];

		return $authURL . '?' . http_build_query( $params, '', '&', PHP_QUERY_RFC3986 );
	}

}