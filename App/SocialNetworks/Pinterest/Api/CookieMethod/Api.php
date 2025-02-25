<?php

namespace FSPoster\App\SocialNetworks\Pinterest\Api\CookieMethod;

use Exception;
use FSPoster\App\Providers\Helpers\Helper;
use FSPoster\App\SocialNetworks\Pinterest\Api\PostingData;
use FSPoster\GuzzleHttp\Client;
use FSPoster\GuzzleHttp\Cookie\CookieJar;
use FSPoster\GuzzleHttp\Psr7\MultipartStream;

class Api
{

	public AuthData $authData;
	public ?string  $proxy = null;

	public string $authException = \Exception::class;
	public string $postException = \Exception::class;
	private Client $client;
	private string $domain = 'www.pinterest.com';

	public function setProxy ( ?string $proxy ): self
	{
		$this->proxy = $proxy;

		return $this;
	}

	public function setAuthData ( AuthData $authData ): self
	{
		$this->authData = $authData;

		$this->setClient();
		$this->findDomainAlias();

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

	private function setClient ( $max_redirects = 0 )
	{
		$csrf_token = base64_encode( microtime( 1 ) . rand( 0, 99999 ) );

		$cookieJar = new CookieJar( false, [
			[
				"Name"     => "_pinterest_sess",
				"Value"    => $this->authData->cookieSess,
				"Domain"   => '.' . $this->domain,
				"Path"     => "/",
				"Max-Age"  => null,
				"Expires"  => null,
				"Secure"   => false,
				"Discard"  => false,
				"HttpOnly" => false,
				"Priority" => "HIGH"
			],
			[
				"Name"     => "csrftoken",
				"Value"    => $csrf_token,
				"Domain"   => '.' . $this->domain,
				"Path"     => "/",
				"Max-Age"  => null,
				"Expires"  => null,
				"Secure"   => false,
				"Discard"  => false,
				"HttpOnly" => false,
				"Priority" => "HIGH"
			]
		] );

		$this->client = new Client( [
			'cookies'         => $cookieJar,
			'allow_redirects' => [ 'max' => $max_redirects ],
			'proxy'           => empty( $this->proxy ) ? null : $this->proxy,
			'verify'          => false,
			'http_errors'     => false,
			'headers'         => [
				'User-Agent'  => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:75.0) Gecko/20100101 Firefox/75.0',
				'x-CSRFToken' => $csrf_token
			]
		] );
	}

	private function findDomainAlias ()
	{
		$result     = $this->client->get( 'https://' . $this->domain );
		$locationTo = $result->getHeader( 'Location' );

		if ( isset( $locationTo[ 0 ] ) && is_string( $locationTo[ 0 ] ) )
		{
			$domain       = parse_url( $locationTo[ 0 ] );
			$this->domain = $domain[ 'host' ];
		}

		$this->setClient( 10 );
	}

    public function sendPost ( PostingData $postingData ) : string
    {
        $result = $this->uploadPhoto( $postingData, $postingData->uploadMedia[0]['path'] );

        for( $i = 1; $i < count( $postingData->uploadMedia ); $i++ )
        {
            try
            {
                $this->uploadPhoto( $postingData, $postingData->uploadMedia[$i]['path'] );
            }
            catch ( Exception $e )
            {}
        }

        return $result;
    }

    public function uploadPhoto ( PostingData $postingData, $imagePath ) : string
    {
		if ( function_exists( 'getimagesize' ) )
		{
			$result = @getimagesize( $imagePath );

			if ( isset( $result[0], $result[1] ) )
			{
				$width  = $result[0];
				$height = $result[1];

				if ( $width < 200 || $height < 300 )
				{
					throw new $this->postException( fsp__( 'Pinterest supports images bigger than 200x300. Your image is %sx%s.', [
						$width,
						$height
					] ) );
				}
			}
		}

		try
		{
			$response = $this->client->post( 'https://' . $this->domain . '/resource/VIPResource/create/', [
				'form_params' => [
					'source_url' => '/pin-builder/',
					'data'       => '{"options":{"type":"pinimage"},"context":{}}'
				]
			] )->getBody()->getContents();
		}
		catch ( Exception $e )
		{
			$response = '';

			if ( method_exists( $e, 'getResponse' ) )
				$response = $e->getResponse();

			if ( empty( $response ) )
                throw new $this->postException( $e->getMessage() );

			if ( ! method_exists( $response, 'getBody' ) )
				throw new $this->postException( 'Unknown error' );

			$response = $response->getBody()->getContents();
		}

		$response = json_decode( $response, true );

		if ( empty( $response['resource_response']['data']['upload_id'] ) || empty( $response['resource_response']['data']['upload_url'] ) || empty( $response['resource_response']['data']['upload_parameters'] ) )
            throw new $this->postException( $this->errorMessage( $response ) );

		$uploadData = [];

		foreach ( $response['resource_response']['data']['upload_parameters'] as $k => $v )
		{
			$uploadData[] = [
				'name'     => $k,
				'contents' => $v
			];
		}

		$uploadData[] = [
			'name'     => 'file',
			'filename' => 'blob',
			'contents' => fopen( $imagePath, 'r' ),
			'headers'  => [ 'Content-Type' => Helper::mimeContentType( $imagePath ) ]
		];

		$body = new MultipartStream( $uploadData, '----WebKitFormBoundaryIddk0tpr7i6Kd6Bz' );

		$c = new Client();
		try
		{
			$uploaded = $c->post( $response['resource_response']['data']['upload_url'], [
				'proxy'     => $this->proxy,
				'verify'    => false,
				'headers'   => [
					'Content-Length'     => strlen( $body ),
					'Accept'             => '*/*',
					'Accept-Encoding'    => 'gzip',
					'User-Agent'         => 'Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36',
					'Origin'             => 'https://www.pinterest.com',
					'Referer'            => 'https://www.pinterest.com',
					'sec-ch-ua'          => '".Not/A)Brand";v="99", "Google Chrome";v="103", "Chromium";v="103"',
					'sec-ch-ua-mobile'   => '?0',
					'sec-ch-ua-full'     => '?1',
					'sec-ch-ua-platform' => '"Windows"',
					'Sec-Fetch-Dest'     => 'empty',
					'Sec-Fetch-Mode'     => 'cors',
					'Sec-Fetch-Site'     => 'same-origin',
					'Connection'         => 'keep-alive',
					'Content-Type'       => 'multipart/form-data; boundary=----WebKitFormBoundaryIddk0tpr7i6Kd6Bz'
				],
				'body'      => $body
			] )->getHeaders();
		}
		catch ( Exception $e )
		{
            throw new $this->postException( $e->getMessage() );
		}

		if ( empty( $uploaded['ETag'][0] ) )
			throw new $this->postException( 'Unknown error' );

		$etag     = trim( $uploaded['ETag'][0], '"' );
		$imageUrl = sprintf( 'https://i.pinimg.com/736x/%s%s/%s%s/%s%s/%s.jpg', $etag[0], $etag[1], $etag[2], $etag[3], $etag[4], $etag[5], $etag );

		$sendData = [
			'options' => [
				'board_id'                     => $postingData->boardId,
				'field_set_key'                => 'create_success',
				'skip_pin_create_log'          => true,
				'description'                  => $postingData->message,
				'alt_text'                     => $postingData->altText,
				'link'                         => $postingData->link,
				'title'                        => $postingData->title,
				'image_url'                    => $imageUrl,
				'method'                       => 'uploaded',
				'upload_metric'                => [
					'source' => 'pinner_upload_standalone'
				],
				'user_mention_tags'            => [],
				'no_fetch_context_on_resource' => false
			],
			'context' => []
		];

		try
		{
			$response = $this->client->post( 'https://' . $this->domain . '/resource/PinResource/create/', [
				'form_params' => [
					'source_url' => '/pin-builder/',
					'data'       => json_encode( $sendData )
				]
			] )->getBody()->getContents();
		}
		catch ( Exception $e )
		{
			$response = $e->getResponse()->getBody()->getContents();
		}

		$response = json_decode( $response, true );

		$pinId = $response['resource_response']['data']['id'] ?? '';

		if ( empty( $pinId ) )
            throw new $this->postException( $this->errorMessage( $response ) );

		return (string)$pinId;
	}

	private function errorMessage ( $result, $defaultError = '' )
	{
		if ( isset( $result['resource_response']['message'] ) && is_string( $result['resource_response']['message'] ) )
			return esc_html( $result['resource_response']['message'] );

		if ( isset( $result['resource_response']['error']['message'] ) && is_string( $result['resource_response']['error']['message'] ) )
			return esc_html( $result['resource_response']['error']['message'] . ( isset( $result['resource_response']['error']['message_detail'] ) && is_string( $result['resource_response']['error']['message_detail'] ) ? ' ' . $result['resource_response']['error']['message_detail'] : '' ) );

		if ( ! empty( $defaultError ) )
			return $defaultError;

		return fsp__( 'Couldn\'t upload the image' );
	}

    public function getMyInfo () : array
    {
		try
		{
			$response = (string) $this->client->get( 'https://' . $this->domain . '/resource/HomefeedBadgingResource/get/' )->getBody();
		}
		catch ( Exception $e )
		{
            throw new $this->authException( $e->getMessage() );
		}

		if ( strpos( $response, 'a bot running on your network' ) > -1 )
            throw new $this->authException( fsp__( 'Error! Your domain has been blocked by Pinterest. You can use a proxy to avoid the issue.' ) );

		$result = json_decode( $response, true );

		$id        = $result['client_context']['user']['id'] ?? '';
		$image     = $result['client_context']['user']['image_medium_url'] ?? '';
		$username  = $result['client_context']['user']['username'] ?? '';
		$full_name = $result['client_context']['user']['full_name'] ?? '';

		if ( empty( $id ) || empty( $username ) )
            throw new $this->authException( $this->errorMessage( $result, fsp__( 'Error! Please check the data and try again' ) ) );

		return [
			'id'          => $id,
			'full_name'   => $full_name,
			'profile_pic' => $image,
			'username'    => $username
		];
	}

	public function getMyBoards ( $userName ) : array
    {
		$data = [
			"options" => [
				"isPrefetch"           => false,
				"privacy_filter"       => "all",
				"sort"                 => "custom",
				"field_set_key"        => "profile_grid_item",
				"username"             => $userName,
				"page_size"            => 25,
				"group_by"             => "visibility",
				"include_archived"     => true,
				"redux_normalize_feed" => true
			],
			"context" => []
		];

		$boards_arr = [];
		$bookmark   = '';

		while ( true )
		{
			if ( ! empty( $bookmark ) )
			{
				$data[ 'options' ][ 'bookmarks' ] = [ $bookmark ];
			}

			try
			{
				$response = (string) $this->client->get( 'https://' . $this->domain . '/resource/BoardsResource/get/?data=' . urlencode( json_encode( $data ) ) )->getBody();
				$response = json_decode( $response, true );
			}
			catch ( Exception $e )
			{
				$response = [];
			}

			if ( ! isset( $response[ 'resource_response' ][ 'data' ] ) || ! is_array( $response[ 'resource_response' ][ 'data' ] ) )
			{
				$boards = [];
			}
			else
			{
				$boards = $response[ 'resource_response' ][ 'data' ];
			}

			foreach ( $boards as $board )
			{
				$boards_arr[] = [
					'id'    => $board[ 'id' ],
					'name'  => $board[ 'name' ],
					'url'   => ltrim( $board[ 'url' ], '/' ),
					'photo' => $board[ 'image_cover_url' ] ?? ''
				];
			}

			if (  ! empty( $response[ 'resource_response' ][ 'bookmark' ] ) && is_string( $response[ 'resource_response' ][ 'bookmark' ] ) && $response[ 'resource_response' ][ 'bookmark' ] != '-end-' )
			{
				$bookmark = $response[ 'resource_response' ][ 'bookmark' ];
			}
			else
			{
				break;
			}
		}

		return $boards_arr;
	}

}
