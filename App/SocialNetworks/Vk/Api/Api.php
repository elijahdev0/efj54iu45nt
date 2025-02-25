<?php

namespace FSPoster\App\SocialNetworks\Vk\Api;

use Exception;
use FSPoster\App\Providers\Core\Request;
use FSPoster\GuzzleHttp\Client;
use FSPoster\GuzzleHttp\Exception\GuzzleException;

class Api
{
    public ?Client  $client = null;
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

    public function getClient (): Client
    {
        if ( is_null( $this->client ) )
        {
            $this->client = new Client( [
                'allow_redirects' => [ 'max' => 20 ],
                'proxy'           => empty( $this->proxy ) ? null : $this->proxy,
                'verify'          => false,
                'http_errors'     => false,
                'headers'         => [ 'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:67.0) Gecko/20100101 Firefox/67.0' ],
            ] );
        }

        return $this->client;
    }

    /**
     * @return array
     */
	public function getMyInfo (): array
	{
		try
		{
			$me = $this->sendRequest( 'GET', 'users.get', [ 'fields' => 'id,first_name,last_name,screen_name, sex, bdate,photo,common_count' ] );
		} catch ( GuzzleException $e )
		{
			throw new $this->authException( $e->getMessage() );
		}

		if( ! isset( $me[0] ) )
			throw new $this->authException();

		return $me[0];
	}

	public function getMyGroupsList ( $filterAdmin = false ): array
	{
		$requestParameters = [
			'extended' => '1',
			'fields'   => 'members_count',
		];

		if ( $filterAdmin )
			$requestParameters['filter'] = 'admin';
		else
			$requestParameters['count'] = 1000;

		try
		{
			$groups = $this->sendRequest( 'GET', 'groups.get', $requestParameters );
		} catch ( GuzzleException $e )
		{
			throw new $this->authException( $e->getMessage() );
		}

		return isset( $groups[ 'items' ] ) && is_array( $groups[ 'items' ] ) ? $groups[ 'items' ] : [];
	}

    /**
     *
     * @return array|array[]
     * @throws GuzzleException
     */
    public function getStats (  ): array
    {
        return [];
    }

	public function sendPost ( PostingData $postingData ) : string
	{
		$postData = [
			'message'       => $postingData->message,
			'owner_id'      => ($postingData->ownerType === 'account' ? '' : '-') . $postingData->ownerId,
			'attachments'   => [],
		];

		if( ! empty( $postingData->link ) )
			$postData['attachments'][] = $postingData->link;

		if( ! empty( $postingData->uploadMedia ) )
		{
			$images = [];
			$videos = [];

			foreach ( $postingData->uploadMedia AS $media )
			{
				if( $media['type'] == 'video' )
					$videos[] = $media['path'];
				else
					$images[] = $media['path'];
			}

			if( ! empty( $images ) )
			{
				$uploadedImages = $this->uploadImages( $images, $postingData->ownerId, $postingData->ownerType );

				$postData['attachments'] = array_merge( $postData['attachments'], $uploadedImages );
			}

			if( ! empty( $videos ) )
			{
				$videoName = mb_substr( $postingData->message, 0, 100, 'UTF-8' );

				$uploadedVideos = $this->uploadVideos( $videoName, $videos );
				$postData['attachments'] = array_merge( $postData['attachments'], $uploadedVideos );
			}
		}

		if( empty( $postData['attachments'] ) )
			unset( $postData['attachments'] );
		else
			$postData['attachments'] = implode( ',', $postData['attachments'] );

		$result = $this->sendRequest( 'POST', 'wall.post', $postData );

		$postId = $result[ 'post_id' ] ?? 0;

		if ( ! ( $postId > 0 ) )
			throw new $this->postException();

		return ($postingData->ownerType === 'account' ? '' : '-') . $postingData->ownerId. '_' . $postId;
	}

	private function uploadImages( $images, $ownerId, $ownerType ): array
	{
		$uplData = [];

		if ( $ownerType === 'group' )
			$uplData[ 'group_id' ] = $ownerId;

		$uplServer = $this->sendRequest( 'GET', 'photos.getWallUploadServer', $uplData );

		if ( ! isset( $uplServer[ 'upload_url' ] ) )
			throw new $this->postException();

		$uplServer = $uplServer[ 'upload_url' ];

		$imagesData = [];
		foreach ( $images as $i => $imagePath )
		{
			$imagesData[] = [
				'name'      =>  'file' . ($i+1),
				'contents'  => fopen( $imagePath, 'r' )
			];
		}

		$uploadFile = $this->getClient()->request( 'POST', $uplServer, [ 'multipart' => $imagesData ] )->getBody();
		$uploadFile = json_decode( $uploadFile, true );

		if ( ! is_array( $uploadFile ) || empty( $uploadFile ) )
			throw new $this->postException();

		if ( $ownerType === 'group' )
			$uploadFile[ 'group_id' ] = $ownerId;
		else
			$uploadFile[ 'user_id' ] = $ownerId;

		$uploadPhotos = $this->sendRequest( 'GET', 'photos.saveWallPhoto', $uploadFile );

		if ( ! is_array( $uploadPhotos ) || isset( $uploadPhotos[ 'error' ] ) )
			throw new $this->postException();

		$uploaded = [];
		foreach ( $uploadPhotos as $photoInf )
		{
			$uploaded[] = 'photo' . $photoInf[ 'owner_id' ] . '_' . $photoInf[ 'id' ];
		}

		return $uploaded;
	}

	private function uploadVideos ( $videoName, $videosPath ): array
	{
		$videoUplServer = $this->sendRequest( 'GET', 'video.save', [
			'name'     => $videoName,
			'wallpost' => 1,
		] );

		if ( ! isset( $videoUplServer[ 'owner_id' ] ) || ! isset( $videoUplServer[ 'video_id' ] ) || ! isset( $videoUplServer[ 'upload_url' ] ) )
			throw new $this->postException();

		$ownerId   = $videoUplServer[ 'owner_id' ];
		$videoId   = $videoUplServer[ 'video_id' ];
		$uploadURL = $videoUplServer[ 'upload_url' ];

		$uploadedVideos = [];

		foreach ( $videosPath AS $videoPath )
		{
			if ( function_exists( 'curl_file_create' ) )
				$videoData = curl_file_create( $videoPath );
			else
				$videoData = '@' . $videoPath;

			$uploadFile = $this->getClient()->request( 'POST', $uploadURL, [ 'form_params' => $videoData ] )->getBody();
			$uploadFile = json_decode( $uploadFile, true );

			if ( isset( $uploadFile[ 'error' ] ) )
				throw new $this->postException();

			$uploadedVideos[] = 'video' . $ownerId . '_' . $videoId;
		}

		return $uploadedVideos;
	}

    /**
     * @throws GuzzleException
     */
    private function sendRequest ( string $HTTPMethod, string $endpoint, array $data = [] )
    {
	    $url = 'https://api.vk.com/method/' . $endpoint;

        $data[ 'access_token' ] = $this->authData->accessToken;
        $data[ 'v' ] = '5.131';

        $HTTPMethod = in_array( $HTTPMethod, ['POST', 'DELETE'] ) ? $HTTPMethod : 'GET';

        if ( $HTTPMethod === 'GET' && !empty( $data ) && is_array( $data ) )
        {
            $url .= ( strpos( $url, '?' ) !== false ? '&' : '?' ) . http_build_query( $data );
        }

		$requestOptions = [];
		if( $HTTPMethod !== "GET" && !empty( $data ) )
		{
			$requestOptions = [
				'form_params' => $data,
			];
		}

	    $response = (string)$this->getClient()->request( $HTTPMethod, $url, $requestOptions )->getBody();
	    $data = json_decode( $response, true );

		if( ! isset( $data['response'] ) || ! is_array( $data['response'] ) )
			throw new \Exception( $data['error']['error_msg'] ?? '' );

        return $data['response'];
    }

	public static function getAuthUrl( $clientId, $callbackUrl )
	{
		return 'https://oauth.vk.com/authorize?client_id=' . urlencode( $clientId ) . '&redirect_uri=' . urlencode( $callbackUrl ) . '&display=page&scope=offline,wall,groups,email,photos,video&response_type=token&v=5.69';
	}

}
