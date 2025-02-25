<?php

namespace FSPoster\App\SocialNetworks\Youtube\Api;

use Exception;
use FSPoster\App\Providers\Helpers\GuzzleClient;
use FSPoster\GuzzleHttp\Cookie\CookieJar;

class Api
{

	private ?GuzzleClient $client = null;
	private ?CookieJar $cookieJar = null;
	private string $origin  = 'https://www.youtube.com';
	private string $channelId;
	private array $ytCfg = [];
	private string $referer = 'https://www.youtube.com';
	public AuthData $authData;
	public ?string  $proxy;

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

		$this->rotateCookies();

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

    private function rotateCookies()
    {
        if( time() - $this->authData->cookieLastUpdatedAt < 300 )
            return;

	    $this->authData->cookieLastUpdatedAt = time();

        $client = new GuzzleClient([
            'verify'    => false,
            'proxy'     => $this->proxy
        ]);

        try
        {
            $headers = $client->post('https://accounts.youtube.com/RotateCookies', [
                'cookies' => $this->getCookieJar(),
                'json'    => [null,"806640469208651494",1]
            ])->getHeaders();

            $setCookies = $headers['Set-Cookie'] ?? [];

            $setCookies = array_filter($setCookies, fn($c) => strpos($c, '__Secure-3PSIDTS') === 0);

            if( !empty($setCookies) )
            {
                $psidts = reset($setCookies);
                $psidts = explode(';', $psidts)[0];
                $psidts = explode('=', $psidts)[1];

                $this->authData->cookies['__Secure-3PSIDTS'] = $psidts;
            }
        }
        catch (Exception $e) {}
    }

	private function getCookieJar() : CookieJar
    {
		if( is_null( $this->cookieJar ) )
		{
			$cook = [
				[
					"Name"     => "LOGIN_INFO",
					"Value"    => $this->authData->cookies['LOGIN_INFO'] ?? '',
					"Domain"   => ".youtube.com",
					"Path"     => "/",
					"Max-Age"  => null,
					"Expires"  => null,
					"Secure"   => false,
					"Discard"  => false,
					"HttpOnly" => false,
					"Priority" => "HIGH"
				],
				[
					"Name"     => "__Secure-3PAPISID",
					"Value"    => $this->authData->cookies['__Secure-3PAPISID'] ?? '',
					"Domain"   => ".youtube.com",
					"Path"     => "/",
					"Max-Age"  => null,
					"Expires"  => null,
					"Secure"   => false,
					"Discard"  => false,
					"HttpOnly" => false,
					"Priority" => "HIGH"
				],
				[
					"Name"     => "__Secure-3PSID",
					"Value"    => $this->authData->cookies['__Secure-3PSID'] ?? '',
					"Domain"   => ".youtube.com",
					"Path"     => "/",
					"Max-Age"  => null,
					"Expires"  => null,
					"Secure"   => false,
					"Discard"  => false,
					"HttpOnly" => false,
					"Priority" => "HIGH"
				],
				[
					"Name"     => "__Secure-3PSIDTS",
					"Value"    => $this->authData->cookies['__Secure-3PSIDTS'] ?? '',
					"Domain"   => ".youtube.com",
					"Path"     => "/",
					"Max-Age"  => null,
					"Expires"  => null,
					"Secure"   => false,
					"Discard"  => false,
					"HttpOnly" => false,
					"Priority" => "HIGH"
				]
			];

			$this->cookieJar = new CookieJar( false, $cook );
		}

        return $this->cookieJar;
    }

	public function getMyInfo () : array
    {
		$name  = '';
		$image = '';

		$this->init();

		$response = $this->cmd( 'GET', sprintf( 'channel/%s/', $this->channelId ), '', [
			'Referer' => $this->referer
		] );

		$response = $response->getBody();

		preg_match( '/<meta property=\"og:image\" content=\"(.+?)\">/', $response, $matchedImage );
		preg_match( '/<meta property=\"og:title\" content=\"(.+?)\">/', $response, $matchedName );

		if ( ! empty( $matchedImage ) )
			$image = $matchedImage[1];

		if ( ! empty( $matchedName ) )
			$name = $matchedName[1];

		if ( empty( $image ) || empty( $name ) )
			throw new $this->authException( fsp__( 'Couldn\'t fetch the channel' ) );

		return [
			'name'    => $name,
			'image'   => $image,
			'id'      => $this->channelId
		];
	}

	public function sendPost ( PostingData $postingData ) : string
    {
	    $this->init();

		$this->referer = sprintf( 'https://www.youtube.com/channel/%s', $this->channelId );

		$backstage = $this->backStageParams();

		$this->referer = sprintf( '%s/community', $this->referer );

		$body = [
			'context'                   => $this->ytCfg['context'],
			'createBackstagePostParams' => $backstage,
			'commentText'               => $postingData->message
		];

		foreach ( $postingData->uploadMedia ?? [] AS $media )
		{
			$body['imagesAttachment']['imagesData'][] = $this->upload( $media['path'] );
		}

		$time     = time();
		$apiSid   = $this->getCookieJar()->getCookieByName( '__Secure-3PAPISID' )->getValue();
		$hash     = sha1( sprintf( '%d %s %s', $time, $apiSid, $this->origin ) );
		$endpoint = sprintf( 'youtubei/v1/backstage/create_post?key=%s&prettyPrint=false', $this->ytCfg[ 'apiKey' ] );

		$response = $this->cmd( 'POST', $endpoint, json_encode( $body ), [
			'X-Origin'                      => $this->origin,
			'X-Youtube-Bootstrap-Logged-In' => 'true',
			'Authorization'                 => sprintf( 'SAPISIDHASH %s_%s', $time, $hash ),
			'Content-Type'                  => 'application/json',
			'X-Youtube-Client-Name'         => 1,
			'X-Youtube-Client-Version'      => $this->ytCfg[ 'context' ][ 'client' ][ 'clientVersion' ],
			'X-Goog-AuthUser'               => 0,
			'X-Goog-PageId'                 => $this->ytCfg[ 'pageId' ],
			'X-Goog-Visitor-Id'             => $this->ytCfg[ 'context' ][ 'client' ][ 'visitorData' ],
			'Accept'                        => '*/*',
			'Sec-GPC'                       => 1,
			'Accept-Language'               => 'en-US,en;q=0.5'
		] );

		preg_match( '/\"postId\":\"(.+?)\"/', $response->getBody(), $matchedId );

		if ( empty( $matchedId ) )
			throw new $this->postException( 'Error' );

		return (string)$matchedId[1];
	}

	private function upload ( $url ) : array
    {
		$image = [];
		$raw   = file_get_contents( $url );

	    $uploadUrl = $this->getUploadUrl( strlen( $raw ) );

		$response = $this->cmd( 'POST', $uploadUrl, $raw, [
			'X-Goog-Upload-Command' => 'upload, finalize',
			'X-Goog-Upload-Offset'  => '0',
			'X-YouTube-ChannelId'   => $this->channelId,
			'Content-Type'          => 'application/x-www-form-urlencoded;charset=utf-8'
		] );

		$response = json_decode( $response->getBody(), true );

		if ( empty( $response ) || empty( $response[ 'encryptedBlobId' ] ) )
			throw new $this->postException( fsp__( 'Couldn\'t upload the image' ) );

		[ $width, $height ] = getimagesize( $url ); //contains images width and height

		if ( ! $width || ! $height )
			throw new $this->postException( fsp__( 'Couldn\'t resize the image' ) );

		if ( $width > $height )
		{
			$top  = 0;
			$left = ( $width - $height ) / ( 2 * $width );
		}
		else
		{
			$left = 0;
			$top  = ( $height - $width ) / ( 2 * $height );
		}

		$image = [
			'encryptedBlobId'    => $response['encryptedBlobId'],
			'previewCoordinates' => [
				'top'    => $top,
				'right'  => 1 - $left,
				'bottom' => 1 - $top,
				'left'   => $left
			]
		];

        return $image;
	}

	private function getUploadUrl ( $length ) : string
    {
		$url = $this->cmd( 'POST', 'channel_image_upload/posts', '', [
			'X-YouTube-ChannelId'                 => $this->channelId,
			'X-Goog-Upload-Protocol'              => 'resumable',
			'X-Goog-Upload-Header-Content-Length' => $length,
			'X-Goog-Upload-Command'               => 'start',
			'Content-Type'                        => 'application/x-www-form-urlencoded;charset=UTF-8'
		] );

		$uploadUrl = $url->getHeader( 'X-Goog-Upload-URL' );

		if ( empty( $uploadUrl ) )
			throw new $this->postException( 'Error!' );

		return str_replace( 'https://www.youtube.com/', '', $uploadUrl[0] );
	}

	private function getClient () : GuzzleClient
    {
		if( is_null( $this->client ) )
		{
			$this->client = new GuzzleClient( [
				'cookies'     => $this->getCookieJar(),
				'proxy'       => $this->proxy,
				'verify'      => false,
				'http_errors' => false,
				'headers'     => [
					'User-Agent'                => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/105.0.0.0 Safari/537.36,gzip(gfe)',
					'Host'                      => 'www.youtube.com',
					'Connection'                => 'keep-alive',
					'Cache-Control'             => 'max-age=0',
					'DNT'                       => 1,
					'Upgrade-Insecure-Requests' => 1,
				]
			] );
		}

        return $this->client;
	}

	private function cmd ( $method = 'GET', $endpoint = '', $body = '', $headers = [] )
	{
		$headers['Referer'] = $this->referer;

		try
		{
			$response = $this->getClient()->request( $method, sprintf( 'https://www.youtube.com/%s', $endpoint ), [
				'headers' => $headers,
				'body'    => $body
			] );
		}
		catch ( Exception $e )
		{
			throw new $this->postException( $e->getMessage() );
		}

		return $response;
	}

	private function init ()
	{
		$response = $this->cmd(); //fetches homepage

		$response = $response->getBody();

		preg_match( '/window\.ytplayer=\{};\nytcfg\.set\((.*?)\);/', $response, $matchedConfig );

		if ( ! empty( $matchedConfig ) )
		{
			$config = json_decode( $matchedConfig[1], true );

			if ( ! empty( $config ) )
			{
				if ( ! empty( $config['DELEGATED_SESSION_ID'] ) )
				{
					$this->ytCfg['pageId'] = $config['DELEGATED_SESSION_ID'];
				}
				else
				{
					$this->ytCfg['pageId'] = null;
				}

				$this->ytCfg['apiKey']  = $config['INNERTUBE_API_KEY'];
				$this->ytCfg['context'] = $config['INNERTUBE_CONTEXT'];
			}
		}

		preg_match( '/\/channel\/([A-Za-z0-9_-]+)\/community/', $response, $matchedChannel );

		if ( ! empty( $matchedChannel ) )
			$this->channelId = $matchedChannel[1];

		if ( empty( $this->ytCfg ) || empty( $this->ytCfg[ 'apiKey' ] ) )
			throw new $this->authException( fsp__( 'Couldn\'t fetch the state' ) );

		if ( empty( $this->channelId ) )
			throw new $this->authException( fsp__( 'Your channel doesn\'t meet all eligibility requirements to access the Community posts. <a href="https://support.google.com/youtube/answer/9409631?hl=en" target="_blank">Why?</a>' ) );
	}

	private function backStageParams ()
    {
		$response = $this->cmd( 'GET', sprintf( 'channel/%s/community', $this->channelId ) );

		preg_match( '/\"createBackstagePostParams\":\"(.+?)\"/', $response->getBody(), $matchedBackstage );

		if ( ! $matchedBackstage )
			throw new $this->postException( fsp__( 'Couldn\'t fetch the state' ) );

	    $params = $matchedBackstage[1];

		return $params;
	}

}