<?php

namespace FSPoster\App\SocialNetworks\Discord\Api;

use Exception;
use FSPoster\GuzzleHttp\Client;

class Api
{

	public AuthData $authData;
	public ?string  $proxy = null;

	public string $authException = \Exception::class;
	public string $postException = \Exception::class;

	public function setProxy ( ?string $proxy ) : self
	{
		$this->proxy = $proxy;

		return $this;
	}

	public function setAuthData ( AuthData $authData ) : self
	{
		$this->authData = $authData;

		return $this;
	}

	public function setAuthException( string $exceptionClass ) : self
	{
		$this->authException = $exceptionClass;

		return $this;
	}

	public function setPostException( string $exceptionClass ) : self
	{
		$this->postException = $exceptionClass;

		return $this;
	}

	public function sendPost ( PostingData $postingData ) : string
    {
	    $options = [
		    'json' => [
			    'content' => $postingData->message,
		    ]
	    ];

		if ( ! empty( $postingData->uploadMedia ) )
		{
			$options = [];

			if ( ! empty( $postingData->message ) )
			{
				$options['multipart'][] = [
					'name'     => 'payload_json',
					'contents' => json_encode( [ 'content' => $postingData->message ] ),
				];
			}

			foreach ( $postingData->uploadMedia as $media )
			{
				$name                     = md5( mt_rand( 1000, 9999 ) . microtime() );
				$explode                  = explode( '/', $media['path'] );
				$options[ 'multipart' ][] = [
					'name'     => sprintf( "files[%s]", $name ),
					'contents' => file_get_contents( $media['path'] ),
					'filename' => end( $explode ),
				];
			}
		}

		$endpoint = sprintf( "channels/%s/messages", $postingData->channelId );

		$result = $this->apiRequest( 'POST', $endpoint, $options );

		if ( ! isset( $result['id'] ) )
		{
			$errorMsg = '';

			if ( isset( $result[ 'error_msg' ] ) )
			{
				$errorMsg = $result[ 'error_msg' ];
			}
			else if ( isset( $result[ 'message' ] ) )
			{
				$errorMsg = $result[ 'message' ];
			}
			else if ( isset( $result[ 'content' ] ) && is_array( $result[ 'content' ] ) )
			{
				$errorMsg = reset( $result[ 'content' ] );
			}
			else if ( isset( $result[ '_misc' ] ) && is_array( $result[ '_misc' ] ) )
			{
				$errorMsg = reset( $result[ '_misc' ] );
			}

			if( $errorMsg == '401: Unauthorized' )
				throw new $this->authException( $errorMsg );

			throw new $this->postException( $errorMsg );
		}

        return (string)$result[ 'id' ];
	}

	public function apiRequest ( $HTTPMethod, $endpoint, $options = [] )
	{
		$url = sprintf( 'https://discord.com/api/%s', $endpoint );

		if ( ! empty( $this->proxy ) )
			$options[ 'proxy' ] = $this->proxy;

		$options[ 'headers' ][ 'Authorization' ] = sprintf( 'Bot %s', $this->authData->botToken );

		try
		{
			$response = ( new Client() )->request( $HTTPMethod, $url, $options )->getBody()->getContents();
		}
		catch ( Exception $e )
		{
			if ( ! method_exists( $e, 'getResponse' ) )
				throw new $this->postException( $e->getMessage() );

			$response = $e->getResponse();

			if ( ! method_exists( $response, 'getBody' ) )
				throw new $this->postException( $e->getMessage() );

			$response = $response->getBody()->getContents();
		}

		$responseArray = json_decode( $response, true );

		if ( ! $responseArray )
			throw new $this->postException( fsp__( 'Request error' ) );

		if ( isset( $responseArray[ 'error' ] ) )
		{
			$errorMsg = fsp__( 'Error' );

			if ( isset( $responseArray[ 'error' ][ 'message' ] ) )
				$errorMsg = $responseArray[ 'error' ][ 'message' ];
			else if ( $responseArray[ 'error_description' ] )
				$errorMsg = $responseArray[ 'error_description' ];

			throw new $this->postException( $errorMsg );
		}

		return $responseArray;
	}

	public function getGuild ( $guildID )
	{
		$endpoint = sprintf( 'guilds/%d', $guildID );
		$guild    = $this->apiRequest( 'GET', $endpoint );

		if ( ! isset( $guild[ 'id' ] ) || ! isset( $guild[ 'name' ] ) )
			throw new $this->authException( isset( $guild[ 'message' ] ) ? esc_html( $guild[ 'message' ] ) : fsp__( 'Error' ) );

		return [
			'name'        => $guild[ 'name' ],
			'profile_pic' => sprintf( 'https://cdn.discordapp.com/icons/%s/%s.png', $guild[ 'id' ], $guild[ 'icon' ] ),
		];
	}

	public function getGuildChannels ( $guildID )
	{
		$endpoint       = sprintf( 'guilds/%s/channels', $guildID );
		$remoteChannels = $this->apiRequest( 'GET', $endpoint ) ?: [];

		$categories = [];
		foreach ( $remoteChannels as $category )
		{
			if ( ! isset( $category[ 'type' ] ) || ($category[ 'type' ] != '4') )
				continue;

			$categories[ $category[ 'id' ] ] = $category[ 'name' ];
		}

		$nodes = [];

		foreach ( $remoteChannels as $remoteChannel )
		{
			if ( ! isset( $remoteChannel[ 'type' ] ) || ($remoteChannel[ 'type' ] != '0' && $remoteChannel[ 'type' ] != '5') )
				continue;

			$nodes[] = [
				'id'   => $remoteChannel[ 'id' ],
				'name' => ( ! empty( $remoteChannel['parent_id'] ) && isset( $categories[$remoteChannel['parent_id']] ) ? $categories[$remoteChannel['parent_id']] . ' > ' : '' ) . $remoteChannel[ 'name' ],
			];
		}

		return $nodes;
	}

	public static function getAuthURL ( $clientId, $callbackUrl ) : string
	{
		$url    = 'https://discord.com/api/oauth2/authorize';
		$params = [
			'client_id'     => $clientId,
			'permissions'   => 51200,
			'redirect_uri'  => $callbackUrl,
			'response_type' => 'code',
			'scope'         => 'bot identify',
			'prompt'        => 'none',
		];

		return $url . '?' . http_build_query( $params, '', '&', PHP_QUERY_RFC3986 );
	}

}