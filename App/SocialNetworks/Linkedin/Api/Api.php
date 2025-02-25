<?php

namespace FSPoster\App\SocialNetworks\Linkedin\Api;

use Exception;
use FSPoster\App\Providers\Helpers\Curl;
use FSPoster\App\Providers\Helpers\Date;
use FSPoster\App\Providers\Helpers\Helper;
use FSPoster\App\Providers\Helpers\URLScraper;
use FSPoster\App\Providers\Helpers\WPPostThumbnail;
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
            'commentary'                => $postingData->message,
            'visibility'                => 'PUBLIC',
            'distribution'              => [
                'feedDistribution'               => 'MAIN_FEED',
                'targetEntities'                 => [],
                'thirdPartyDistributionChannels' => [],
            ],
            'lifecycleState'            => 'PUBLISHED',
            'isReshareDisabledByAuthor' => false,
        ];

        if ( isset( $postingData->channelType ) && $postingData->channelType === 'company' )
            $postData['author'] = 'urn:li:organization:' . $postingData->channelId;
		else
            $postData['author'] = 'urn:li:person:' . $postingData->channelId;

        if ( ! empty( $postingData->link ) )
		{
	        $postData[ 'content' ][ 'article' ] = $this->scrapeURL( $postingData->link, $postData[ 'author' ] );
        }
		else if( ! empty( $postingData->uploadMedia ) )
		{
			if( $postingData->uploadMedia[0]['type'] === 'image' )
			{
				$uploadedImages = $this->uploadImages( array_column( $postingData->uploadMedia, 'path' ), $postData['author'] );

				if ( ! empty( $uploadedImages ) )
				{
					if ( count( $uploadedImages ) == 1 )
					{
						$postData['content']['media'] = reset( $uploadedImages );
					} else
					{
						$postData['content']['multiImage']['images'] = $uploadedImages;
					}
				}
			}
			else
			{
				$videoUploaded = $this->uploadVideo( $postData['author'], $postingData->uploadMedia[0]['path'] );

				$postData['content']['media'] = [
					'id'    => $videoUploaded,
					'title' => mb_substr( $postingData->message, 100 )
				];
			}
		}

        $client  = new Client();
        $options = [
            'headers' => [
                'Connection'                => 'Keep-Alive',
                'X-li-format'               => 'json',
                'Content-Type'              => 'application/json',
                'X-RestLi-Protocol-Version' => '2.0.0',
                'LinkedIn-Version'          => 202404,
                'Authorization'             => 'Bearer ' . $this->authData->accessToken,
            ],
            'body'    => json_encode( $postData ),
        ];

        if ( !empty( $this->proxy ) )
            $options[ 'proxy' ] = $this->proxy;

        try
        {
	        $response = $client->post( 'https://api.linkedin.com/rest/posts', $options );
            $result = json_decode( $response->getBody()->getContents(), true );
        } catch ( Exception $e )
        {
            if ( ! method_exists( $e, 'getResponse' ) )
	            throw new $this->postException( $e->getMessage() );

	        $result = json_decode( $e->getResponse()->getBody()->getContents(), true );
        }

		if ( isset( $result['message'] ) )
            throw new $this->postException( $result['message'] );

	    if ( ! isset( $response->getHeader( 'x-restli-id' )[0] ) )
		    throw new $this->postException( 'Error' );

        return (string)$response->getHeader( 'x-restli-id' )[0];
    }

    private function scrapeURL ( $url, $author ): array
    {
        $scrapeData = [
            'source' => $url,
        ];

        $scraped = URLScraper::scrape( $url );

        $scrapeData['title']       = $scraped['title'];
        $scrapeData['description'] = $scraped['description'];

        if ( !empty( $scraped['image'] ) )
        {
            $image = WPPostThumbnail::saveRemoteImage( $scraped['image'] );

            if ( $image !== false )
            {
                $uploadThumb = $this->uploadImages( [ $image ], $author );

                if ( !empty( $uploadThumb ) )
                    $scrapeData['thumbnail'] = reset( $uploadThumb )['id'];
            }

        }

        return $scrapeData;
    }

    private function uploadImages ( $images, $author ): array
    {
        $send_upload_data = [
            'initializeUploadRequest' => [
                'owner' => $author,
            ],
        ];

        $uploaded_images = [];
        $client          = new Client();

        foreach ( $images as $imagePath )
        {
            try
            {
                $result = $this->apiRequest( 'images?action=initializeUpload', 'POST', $send_upload_data );

                if ( empty( $result[ 'value' ][ 'uploadUrl' ] ) || empty( $result[ 'value' ][ 'image' ] ) )
                {
                    throw new Exception();
                }

                $uploadURL = $result[ 'value' ][ 'uploadUrl' ];
                $mediaID   = $result[ 'value' ][ 'image' ];

                $mimeType = Helper::mimeContentType( $imagePath );

                $fileContent = false;

                if ( strpos( $mimeType, 'webp' ) !== false )
                {
                    $fileContent = Helper::webpToJpg( $imagePath );
                }

                if ( $fileContent === false )
                {
                    $fileContent = file_get_contents( $imagePath );
                }

                $resp = $client->request( 'PUT', $uploadURL, [
                    'body'    => $fileContent,
                    'headers' => [
                        'Authorization' => 'Bearer ' . $this->authData->accessToken,
                        'proxy'         => empty( $this->proxy ) ? null : $this->proxy,
                    ],
                ] );

                $mediaStatus = $this->apiRequest( 'assets/' . explode( ':', $mediaID )[ 3 ], 'GET' );

                if ( isset( $mediaStatus[ 'recipes' ][ 0 ][ 'status' ] ) && $mediaStatus[ 'recipes' ][ 0 ][ 'status' ] === 'PROCESSING' )
                {
                    sleep(10);
                    $mediaStatus = $this->apiRequest( 'assets/' . explode( ':', $mediaID )[ 3 ], 'GET' );
                }

                if ( isset( $mediaStatus[ 'status' ] ) && $mediaStatus[ 'status' ] === 'ALLOWED' )
                {
                    $uploaded_images[] = [ 'id' => $mediaID ];
                } else
                {
                    throw new Exception();
                }
            } catch ( Exception $e )
            {
            }
        }

        return $uploaded_images;
    }

    private function uploadVideo ( $owner, $file )
    {
        $initialData = [
            'initializeUploadRequest' => [
                'owner'           => $owner,
                'fileSizeBytes'   => strlen( file_get_contents( $file ) ),
                'uploadCaptions'  => false,
                'uploadThumbnail' => false,
            ],
        ];

        $client = new Client();

        $etags = [];

        try
        {
            $res = $client->post( 'https://api.linkedin.com/rest/videos?action=initializeUpload', [
                'headers' => $this->makeHeaders(),
                'proxy'   => empty( $this->proxy ) ? null : $this->proxy,
                'body'    => json_encode( $initialData ),
            ] )->getBody()->getContents();

            $res = json_decode( $res, true );
        }
		catch ( Exception $e )
        {
            throw new $this->postException( $e->getMessage() );
        }

        if ( ! isset( $res['value']['uploadInstructions'] ) || !isset( $res['value']['video'] ) )
	        throw new $this->postException( 'Error' );

        $video       = $res['value']['video'];
        $uploadToken = $res['value']['uploadToken'] ?? '';

        $fileContent = file_get_contents( $file );

        foreach ( $res['value']['uploadInstructions'] as $part )
        {
            try
            {
                $headers = $client->post( $res[ 'value' ][ 'uploadInstructions' ][ 0 ][ 'uploadUrl' ], [
                        'headers' => [
                            'X-RestLi-Protocol-Version' => '2.0.0',
                            'Authorization'             => 'Bearer ' . $this->authData->accessToken,
                            'LinkedIn-Version'          => 202404,
                            'Content-Type'              => 'application/octet-stream',
                        ],
                        'proxy'   => empty( $this->proxy ) ? null : $this->proxy,
                        'body'    => substr( $fileContent, $part[ 'firstByte' ], $part[ 'lastByte' ] - $part[ 'firstByte' ] + 1 ),
                    ]
                )->getHeaders();

                if ( ! isset( $headers[ 'ETag' ][ 0 ] ) )
	                throw new $this->postException( 'Error' );

                $etags[] = $headers[ 'ETag' ][ 0 ];
            }
			catch ( Exception $e )
            {
	            throw new $this->postException( 'Error' );
            }
        }

        //finalize
        $final = [
            'finalizeUploadRequest' => [
                'video'           => $video,
                'uploadToken'     => $uploadToken,
                'uploadedPartIds' => $etags,
            ],
        ];

        try
        {
            $done = $client->post( 'https://api.linkedin.com/rest/videos?action=finalizeUpload', [
                'body'    => json_encode( $final ),
                'headers' => $this->makeHeaders(),
                'proxy'   => empty( $this->proxy ) ? null : $this->proxy,
            ] )->getStatusCode();
        } catch ( Exception $e )
        {
	        throw new $this->postException( $e->getMessage() );
        }

	    if ( $done != 200 )
		    throw new $this->postException( 'Error' );

	    return $video;
    }

    //for upload video
    private function makeHeaders (): array
    {
        return [
            'Content-Type'              => 'application/json',
            'X-RestLi-Protocol-Version' => '2.0.0',
            'Authorization'             => 'Bearer ' . $this->authData->accessToken,
            'LinkedIn-Version'          => 202404,
        ];
    }

    public function apiRequest ( $cmd, $HTTPMethod, array $data = [] )
    {
        $url = 'https://api.linkedin.com/v2/' . $cmd;

        $HTTPMethod = $HTTPMethod === 'POST' ? 'POST' : ( $HTTPMethod === 'DELETE' ? 'DELETE' : 'GET' );

        $headers = [
            'Connection'                => 'Keep-Alive',
            'X-li-format'               => 'json',
            'Content-Type'              => 'application/json',
            'X-RestLi-Protocol-Version' => '2.0.0',
            'LinkedIn-Version'          => 202404,
            'Authorization'             => 'Bearer ' . $this->authData->accessToken,
        ];

        if ( $HTTPMethod === 'POST' )
            $data = json_encode( $data );

        $data1 = Curl::getContents( $url, $HTTPMethod, $data, $headers, $this->proxy );
        $data  = json_decode( $data1, true );

        if ( ! is_array( $data ) )
            throw new $this->postException( 'Error' );

        return $data;
    }

    public function fetchAccessToken ( $code, $callbackUrl ): Api
    {
        $appSecret = $this->authData->appClientSecret;
        $appId     = $this->authData->appClientId;

        $token_url = "https://www.linkedin.com/oauth/v2/accessToken?client_id=" . $appId . "&redirect_uri=" . urlencode( $callbackUrl ) . "&client_secret=" . $appSecret . "&code=" . $code . '&grant_type=authorization_code';

        $response = Curl::getURL( $token_url, $this->proxy );
        $params = json_decode( $response, true );

        if ( ! isset( $params['access_token'] ) || ! isset( $params['refresh_token'] ) || ! isset( $params['expires_in'] ) )
            throw new $this->authException( 'Error' );

		$this->authData->accessToken = $params['access_token'];
		$this->authData->refreshToken = $params['refresh_token'];
		$this->authData->accessTokenExpiresOn = Date::dateTimeSQL( 'now', '+' . (int)$params['expires_in'] . ' seconds' );

        return $this;
    }

    public function refreshAccessTokenIfNeed()
    {
	    if ( ! empty( $this->authData->accessTokenExpiresOn ) && ( Date::epoch() + 30 ) > Date::epoch( $this->authData->accessTokenExpiresOn ) )
	    {
		    $this->refreshAccessToken();
	    }
    }

    public function refreshAccessToken ()
    {
        $sendData = [
            'grant_type'    => 'refresh_token',
            'refresh_token' => $this->authData->refreshToken,
            'client_id'     => $this->authData->appClientId,
            'client_secret' => $this->authData->appClientSecret,
        ];

        $token_url = 'https://www.linkedin.com/oauth/v2/accessToken';
        $response  = Curl::getContents( $token_url, 'POST', $sendData, [], $this->proxy, true );

        $token_data = json_decode( $response, true );

        if ( ! is_array( $token_data ) || ! isset( $token_data[ 'access_token' ] ) )
            throw new $this->authException( fsp__( 'LinkedIn API access token life is a year and it is expired. Please add your account to the plugin again without deleting the account from the plugin; as a result, account settings will remain as it is.' ) );

		$this->authData->accessToken = $token_data[ 'access_token' ];
		$this->authData->accessTokenExpiresOn = Date::dateTimeSQL( 'now', '+' . (int)$token_data[ 'expires_in' ] . ' seconds' );
    }

	public function getMyInfo ()
	{
		$me = $this->apiRequest( 'me', 'GET', [
			'projection' => '(id,localizedFirstName,localizedLastName,profilePicture(displayImage~digitalmediaAsset:playableStreams))',
		] );

		if ( isset( $me[ 'status' ] ) && $me[ 'status' ] === '401' )
			throw new $this->authException( fsp__( 'LinkedIn API access token life is a year and it is expired. Please add your account to the plugin again without deleting the account from the plugin; as a result, account settings will remain as it is.' ) );

		if ( isset( $me[ 'status' ] ) && $me[ 'status' ] === '429' )
			throw new $this->authException( fsp__( 'You reached a limit. Please try again later.' ) );

		if ( ! isset( $me[ 'id' ] ) )
			throw new $this->authException( fsp__( 'Unknown error2' ) );

		return $me;
	}

	public function getMyOrganizations()
	{
		$companies = $this->apiRequest( 'organizationalEntityAcls', 'GET', [
			'q'          => 'roleAssignee',
			'role'       => 'ADMINISTRATOR',
			'projection' => '(elements*(organizationalTarget~(id,localizedName,vanityName,logoV2(original~:playableStreams))))',
		] );

		if ( ! isset( $companies['elements'] ) || ! is_array( $companies['elements'] ) )
			throw new $this->authException('Error');

		return $companies['elements'];
	}

	public static function getAuthURL ( $appId, $callbackUrl ): string
	{
		$permissions = self::getScope();

		return sprintf( 'https://www.linkedin.com/oauth/v2/authorization?redirect_uri=%s&scope=%s&response_type=code&client_id=%s&state=%s', $callbackUrl, $permissions, $appId, uniqid() );
	}

	private static function getScope (): string
	{
		$permissions = [ 'r_liteprofile', 'rw_organization_admin', 'w_member_social', 'w_organization_social' ];

		return implode( ',', array_map( 'urlencode', $permissions ) );
	}

}
