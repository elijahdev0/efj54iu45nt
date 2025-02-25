<?php

namespace FSPoster\App\SocialNetworks\Pinterest\Api\AppMethod;

use Exception;
use FSPoster\App\Providers\Helpers\Curl;
use FSPoster\App\Providers\Helpers\Date;
use FSPoster\App\Providers\Helpers\Helper;
use FSPoster\App\SocialNetworks\Pinterest\Api\PostingData;
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

    public function sendPost ( PostingData $postingData ) : string
    {
        $firstImagePath = $postingData->uploadMedia[0]['path'];
        $result = $this->uploadPhoto( $postingData, $firstImagePath );

        for( $i = 1; $i < count( $postingData->uploadMedia ); $i++ )
        {
            try
            {
                $this->uploadPhoto( $postingData, $postingData->uploadMedia[$i]['path'] );
            }
            catch (Exception $e){}
        }

        return $result;
    }

    private function uploadPhoto ( PostingData $postingData, string $image ) : string
    {

		$sendData = [
			'board_id'    => $postingData->boardId,
			'title'       => $postingData->title,
			'description' => $postingData->message,
			'link'        => $postingData->link,
			'alt_text'    => $postingData->altText,
		];

		if ( function_exists( 'getimagesize' ) )
		{
			$result = @getimagesize( $image );

			if ( isset( $result[0], $result[1] ) )
			{
				$width  = $result[0];
				$height = $result[1];

				if ( $width < 200 || $height < 300 )
				{
					throw new $this->postException( fsp__( 'Pinterest supports images bigger than 200x300. Your image is %sx%s.', [
						$width,
						$height,
					] ) );
				}
			}
		}

		$sendData['media_source']['source_type'] = 'image_base64';

		$mimeType = Helper::mimeContentType( $image );

		$fileContent = false;

		if ( strpos( $mimeType, 'webp' ) !== false )
		{
			$fileContent = Helper::webpToJpg( $image );
		}

		if ( $fileContent === false )
			$fileContent = file_get_contents( $image );
		else
			$mimeType = 'image/png';

		$sendData['media_source']['content_type'] = $mimeType;
		$sendData['media_source']['data'] = base64_encode( $fileContent );

		$result = $this->apiRequest( 'pins', 'POST', $sendData );

		if( ! isset( $result[ 'id' ] ) )
			throw new $this->postException( $result['error']['message'] ?? ( $result['message'] ?? 'Error' ) );

		return (string)$result['id'];
	}

	public function apiRequest ( string $endpoint, string $HTTPMethod, array $data = [] )
	{
		$options = [];
		//$data[ 'access_token' ] = $accessToken;

		$url = 'https://api.pinterest.com/v5/' . trim( $endpoint, '/' ) . '/';

		$method = $HTTPMethod === 'POST' ? 'POST' : ( $HTTPMethod === 'DELETE' ? 'DELETE' : 'GET' );

		$options[ 'headers' ] = [
			'Authorization' => 'Bearer ' . $this->authData->accessToken,
		];

		if ( $method === 'POST' )
		{
			$options[ 'headers' ][ 'Content-Type' ] = 'application/json';
			$data                                   = json_encode( $data );
			$options[ 'body' ]                      = $data;
		}
		else if ( ! empty( $data ) )
		{
			$options[ 'query' ] = $data;
		}

		$client = new Client();

		try
		{
			$data1 = $client->request( $method, $url, $options )->getBody()->getContents();
		}
		catch ( Exception $e )
		{
			if ( method_exists( $e, 'getResponse' ) && ! empty( $e->getResponse() ) )
				throw new $this->postException( $e->getResponse()->getBody()->getContents() );

			throw new $this->postException( $e->getMessage() );
		}

		$data = json_decode( $data1, true );

		if ( ! is_array( $data ) )
			throw new $this->postException( 'Error' );

		return $data;
	}

	public function fetchAccessToken ( $code, $callbackUrl ) : Api
    {
        $appSecret = urlencode( $this->authData->appSecret );
		$appId     = urlencode( $this->authData->appId );

		$token_url = "https://api.pinterest.com/v5/oauth/token";

		$response = Curl::getContents( $token_url, 'POST', [
			'grant_type'   => 'authorization_code',
			'code'         => $code,
			'redirect_uri' => $callbackUrl,
		], [
			'Authorization' => 'Basic ' . base64_encode( $appId . ':' . $appSecret ),
			'Content-Type'  => 'application/x-www-form-urlencoded',
		], $this->proxy, true );

		$params = json_decode( $response, true );

		if ( isset( $params[ 'message' ] ) )
            throw new $this->authException( $params['message'] );

		$this->authData->accessToken = $params['access_token'];
		$this->authData->accessTokenExpiresOn = Date::dateTimeSQL( 'now', '+' . (int) $params['expires_in'] . ' seconds' );
		$this->authData->refreshToken = $params['refresh_token'];

        return $this;
	}

	private function refreshAccessTokenIfNeed ()
	{
		if ( ! empty( $this->authData->accessTokenExpiresOn ) && ( Date::epoch() + 30 ) > Date::epoch( $this->authData->accessTokenExpiresOn ) )
		{
			$this->refreshAccessToken();
		}
	}

    private function refreshAccessToken ()
	{
        $token_url = "https://api.pinterest.com/v5/oauth/token";

        $response = Curl::getContents( $token_url, 'POST', [
            'grant_type'    => 'refresh_token',
            'refresh_token' => $this->authData->refreshToken,
        ], [
            'Authorization' => 'Basic ' . base64_encode( urlencode( $this->authData->appId ) . ':' . urlencode( $this->authData->appSecret ) ),
            'Content-Type'  => 'application/x-www-form-urlencoded',
        ], $this->proxy, true );

        $params = json_decode( $response, true );

        if ( isset( $params['message'] ) )
            throw new $this->authException( $params['message'] );

		$this->authData->accessToken = $params['access_token'];
		$this->authData->accessTokenExpiresOn = Date::dateTimeSQL( Date::epoch() + (int) $params[ 'expires_in' ] );
	}

	public function getMyInfo ()
	{
		$me = self::apiRequest( 'user_account', 'GET', [] );

		if ( ! isset( $me[ 'username' ] )  )
			throw new $this->authException( $me['message'] ?? 'Error' );

		return $me;
	}

	public function getMyBoards () : array
    {
		$bookmark = null;
		$boards   = [];

		do
		{
			$send_data = [ 'page_size' => 250 ];

			if ( ! empty( $bookmark ) )
			{
				$send_data[ 'bookmark' ] = $bookmark;
			}

			$page = $this->apiRequest( 'boards', 'GET', $send_data );

			if ( ! empty( $page[ 'items' ] ) )
			{
				foreach ( $page[ 'items' ] as $item )
				{
					$board = [
						'id'    => $item[ 'id' ],
						'name'  => $item[ 'name' ],
						'photo' => $item[ 'media' ][ 'image_cover_url' ] ?? '',
					];

					$boards[] = $board;
				}
				$bookmark = empty( $page[ 'bookmark' ] ) ? null : $page[ 'bookmark' ];
			}
			else
			{
				break;
			}
		} while ( ! empty( $bookmark ) );

		return $boards;
	}

	public static function getAuthURL ( $appId, $callbackUrl, $state ) : string
	{
		$appId = urlencode( $appId );
		$callbackUrl = urlencode( $callbackUrl );

		return "https://www.pinterest.com/oauth/?client_id={$appId}&redirect_uri=$callbackUrl&response_type=code&scope=boards:read,boards:write,pins:read,pins:write,user_accounts:read&state=" . urlencode( $state );
	}

}