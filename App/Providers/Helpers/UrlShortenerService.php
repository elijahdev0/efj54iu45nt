<?php

namespace FSPoster\App\Providers\Helpers;

use Exception;
use FSPoster\App\Providers\Core\Settings;
use FSPoster\GuzzleHttp\Client;

class UrlShortenerService
{
	/**
	 * @param $url
	 *
	 * @return string
	 */
	public static function short ( $url, $service ): string
    {
		switch ( $service )
		{
			case 'bitly':
				return self::shortWithBitly( $url );
			case 'tinyurl':
				return self::shortWithTinyurl( $url );
			case 'yourls':
				return self::shortWithYourls( $url );
			case 'polr':
				return self::shortWithPolr( $url );
			case 'shlink':
				return self::shortWithShlink( $url );
			case 'rebrandly':
				return self::shortWithRebrandly( $url );
			default :
				return $url;
		}
	}

	/**
	 * @param $url
	 *
	 * @return string
	 */
	public static function shortWithTinyurl ( $url ): string
    {
		if ( empty( $url ) )
		{
			return $url;
		}

		$shortenURL = Curl::getURL( 'https://tinyurl.com/api-create.php?url=' . urlencode( $url ) );

		return filter_var( $shortenURL, FILTER_VALIDATE_URL ) ? $shortenURL : $url;
	}

	/**
	 * @param $url
	 *
	 * @return string
	 */
	public static function shortWithBitly ( $url ): string
    {
		$accessToken = Settings::get( 'url_short_access_token_bitly', '' );

		if ( empty( $url ) || empty( $accessToken ) )
		{
			return $url;
		}

		$c = new Client();

		try
		{
			$response = $c->post( 'https://api-ssl.bit.ly/v4/shorten', [
				'body'    => json_encode( [ 'long_url' => $url ] ),
				'headers' => [
					'Authorization' => 'Bearer ' . $accessToken,
					'Content-Type'  => 'application/json'
				]
			] )->getBody()->getContents();

			$response = json_decode( $response, true );

			return empty( $response[ 'link' ] ) ? $url : $response[ 'link' ];
		}
		catch ( Exception $e )
		{
			return $url;
		}
	}

	public static function shortWithYourls ( $url )
	{
		$secretToken = trim( Settings::get( 'url_short_api_token_yourls', '' ) );
		$requestUrl  = trim( Settings::get( 'url_short_api_url_yourls', '' ) );

		if ( empty( $url ) || empty( $secretToken ) || empty( $requestUrl ) )
		{
			return $url;
		}

		$client = new Client();

		try
		{
			$response = $client->post( $requestUrl, [
				'query' => [
					'signature' => $secretToken,
					'action'    => 'shorturl',
					'format'    => 'json',
					'url'       => $url
				]
			] );
		}
		catch ( Exception $e )
		{
			if ( ! method_exists( $e, 'getResponse' ) )
			{
				return $url;
			}

			$response = $e->getResponse();

			if ( is_null( $response ) )
			{
				return $url;
			}
		}

		$response = json_decode( $response->getBody()->getContents(), true );

		return empty( $response[ 'shorturl' ] ) ? $url : $response[ 'shorturl' ];
	}

	public static function shortWithPolr ( $url )
	{
		$apiKey     = trim( Settings::get( 'url_short_api_key_polr', '' ) );
		$requestUrl = trim( Settings::get( 'url_short_api_url_polr', '' ) );

		if ( empty( $url ) || empty( $apiKey ) || empty( $requestUrl ) )
		{
			return $url;
		}

		$client = new Client();

		try
		{
			$response = $client->post( trim( $requestUrl, '/' ) . '/action/shorten', [
				'query' => [
					'key'           => $apiKey,
					'is_secret'     => false,
					'response_type' => 'json',
					'url'           => $url
				]
			] );
		}
		catch ( Exception $e )
		{
			if ( ! method_exists( $e, 'getResponse' ) )
			{
				return $url;
			}

			$response = $e->getResponse();

			if ( is_null( $response ) )
			{
				return $url;
			}
		}

		$response = json_decode( $response->getBody()->getContents(), true );

		return empty( $response[ 'result' ] ) ? $url : $response[ 'result' ];
	}

	public static function shortWithShlink ( $url )
	{
		$apiKey     = Settings::get( 'url_short_api_key_shlink', '' );
		$requestUrl = Settings::get( 'url_short_api_url_shlink', '' );

		if ( empty( $url ) || empty( $apiKey ) || empty( $requestUrl ) )
		{
			return $url;
		}

		$client = new Client();

		try
		{
			$response = $client->post( trim( $requestUrl, '/' ) . '/short-urls', [
				'body'    => json_encode( [
					'longUrl'      => $url,
					'validateUrl'  => false,
					'findIfExists' => true
				] ),
				'headers' => [
					'X-Api-Key' => $apiKey
				]
			] );
		}
		catch ( Exception $e )
		{
			if ( ! method_exists( $e, 'getResponse' ) )
			{
				return $url;
			}

			$response = $e->getResponse();

			if ( is_null( $response ) )
			{
				return $url;
			}
		}

		$response = json_decode( $response->getBody()->getContents(), true );

		return empty( $response[ 'shortUrl' ] ) ? $url : $response[ 'shortUrl' ];
	}

	public static function shortWithRebrandly ( $url )
	{
		$apiKey = Settings::get( 'url_short_api_key_rebrandly', '' );
		$domain = Settings::get( 'url_short_domain_rebrandly', '' );

		if ( empty( $url ) || empty( $apiKey ) || empty( $domain ) )
		{
			return $url;
		}

		$client = new Client();

		try
		{
			$response = $client->post( 'https://api.rebrandly.com/v1/links', [
				'body'    => json_encode( [
					'destination' => $url,
					'domain'      => [
						'fullName' => $domain
					]
				] ),
				'headers' => [
					'Content-Type' => 'application/json',
					'apikey'       => $apiKey
				]
			] );
		}
		catch ( Exception $e )
		{
			if ( ! method_exists( $e, 'getResponse' ) )
			{
				return $url;
			}

			$response = $e->getResponse();

			if ( is_null( $response ) )
			{
				return $url;
			}
		}

		$response = json_decode( $response->getBody()->getContents(), true );

		return empty( $response[ 'shortUrl' ] ) ? $url : ( 'https://' . $response[ 'shortUrl' ] );
	}
}
