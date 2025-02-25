<?php

namespace FSPoster\App\SocialNetworks\Mastodon\Api;

use Exception;
use FSPoster\App\Providers\Helpers\Helper;
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
		$parameters = [];

		if ( ! empty( $postingData->message ) )
			$parameters[ 'status' ] = $postingData->message;

		if ( ! empty( $postingData->link ) )
		{
			$nl = empty( $parameters[ 'status' ] ) ? '' : "\n";
			$parameters[ 'status' ] .= $nl . $postingData->link . "\n";
		}

		if ( ! empty( $postingData->uploadMedia ) )
			$parameters['media_ids'] = $this->uploadMedia( $postingData->uploadMedia );

		$post = $this->apiRequest( 'post', 'api/v1/statuses', [
			'json' => $parameters
		] );

		if ( ! isset( $post[ 'id' ] ) )
			throw new $this->postException( fsp__( 'Unknown error' ) );

        return (string)$post['id'];
	}

	private function uploadMedia ( $medias ) : array
    {
		$data = [];

		foreach ( $medias as $media )
		{
			$response = $this->apiRequest( 'post', 'api/v1/media', [
				'multipart' => [
					[
						'name'     => 'file',
						'filename' => basename( $media['path'] ),
						'contents' => file_get_contents( $media['path'] ),
						'headers'  => [
							'Content-Type' => Helper::mimeContentType( $media['path'] ),
						],
					],
				],
			] );

			if ( ! isset( $response[ 'id' ] ) )
				throw new $this->postException( fsp__( 'Unknown error' ) );

			$data[] = $response[ 'id' ];
		}

		return $data;
	}

	private function apiRequest ( string $HTTPMethod, string $endpoint, array $options = [] ) : array
    {
		if ( ! empty( $this->proxy ) )
			$options[ 'proxy' ] = $this->proxy;

		if ( ! empty( $this->authData->accessToken ) )
			$options[ 'headers' ][ 'Authorization' ] = 'Bearer ' . $this->authData->accessToken;

        $HTTPMethod = strtolower( $HTTPMethod );
		$endpoint = trim( $endpoint, '/' );
		try
		{
			$c        = new Client();
			$response = $c->$HTTPMethod( $this->authData->server . '/' . $endpoint, $options );
		}
		catch ( Exception $e )
		{
			if ( method_exists( $e, 'getResponse' ) )
			{
				$response = $e->getResponse();

				if ( is_null( $response ) || ! method_exists( $response, 'getBody' ) )
					throw new $this->postException( $e->getMessage() );
			}
			else
				throw new $this->postException( $e->getMessage() );
		}

		$response = $response->getBody()->getContents();
		$response = json_decode( $response, true );

		if ( isset( $response[ 'error' ] ) )
			throw new $this->postException( $response[ 'error_description' ] ?? $response[ 'error' ] );

		if ( ! is_array( $response ) )
			throw new $this->postException( fsp__( 'Unknown error' ) );

		return $response;
	}

    public function fetchAccessToken ( $code, $callbackUrl ) : Api
    {
		$response = $this->apiRequest( 'post', 'oauth/token', [
			'query' => [
				'code'          => $code,
				'grant_type'    => 'authorization_code',
				'client_id'     => $this->authData->appClientKey,
				'client_secret' => $this->authData->appClientSecret,
				'redirect_uri'  => $callbackUrl,
				'scope'         => implode( ' ', [ 'read', 'write' ] ),
			],
		] );

		if ( empty( $response[ 'access_token' ] ) )
            throw new $this->authException(fsp__( 'Unknown error' ));

		$this->authData->accessToken = $response[ 'access_token' ];

		return $this;
	}

	public function getMyInfo ()
	{
		$response = $this->apiRequest( 'get', 'api/v1/accounts/verify_credentials' );

		if ( ! isset( $response[ 'id' ] ) )
			throw new $this->authException( 'Error' );

		return $response;
	}

	public static function getAuthURL ( $appId, $server, $callback ) : string
	{
		$permissions = implode( ' ', [ 'read', 'write' ] );

		return trim( $server, '/' ) . '/oauth/authorize?' . http_build_query( [
				'redirect_uri'  => $callback,
				'response_type' => 'code',
				'scope'         => $permissions,
				'client_id'     => $appId,
			] );
	}

}