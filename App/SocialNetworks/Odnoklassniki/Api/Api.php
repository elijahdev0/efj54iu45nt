<?php

namespace FSPoster\App\SocialNetworks\Odnoklassniki\Api;


use FSPoster\App\Providers\Helpers\Curl;
use FSPoster\App\Providers\Helpers\Date;

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

    public function fetchAccessToken ( $code, $callbackUrl ) : Api
    {
		$token_url = 'https://api.odnoklassniki.ru/oauth/token.do';

		$postData = [
			'code'          => $code,
			'redirect_uri'  => $callbackUrl,
			'grant_type'    => 'authorization_code',
			'client_id'     => $this->authData->appId,
			'client_secret' => $this->authData->appSecret,
		];

		$response = Curl::getContents( $token_url, 'POST', $postData, [], $this->proxy, true );
		$params   = json_decode( $response, true );

		if ( isset( $params[ 'error_description' ] ) )
			throw new $this->authException( $params[ 'error_description' ] );

		$this->authData->accessToken = $params[ 'access_token' ];
		$this->authData->accessTokenExpiresOn = Date::dateTimeSQL( 'now', '+' . (int) $params[ 'expires_in' ] . ' seconds' );
		$this->authData->refreshToken = $params[ 'refresh_token' ];

		return $this;
	}

	public function getMyInfo ()
	{
		$me = $this->apiRequest( 'users.getCurrentUser', 'GET' );

		if ( isset( $me[ 'error_msg' ] ) || ! isset( $me['uid'] ) )
			throw new $this->authException( $me[ 'error_msg' ] ?? 'Error' );

		return $me;
	}

	public function getGroupsList ()
	{
		$groups = [];
		$ids_arr = [];
		$groupIDsList = $this->apiRequest( 'group.getUserGroupsV2', 'GET', [ 'count' => 100 ] );

		if ( isset( $groupIDsList[ 'groups' ] ) && is_array( $groupIDsList[ 'groups' ] ) )
		{
			foreach ( $groupIDsList[ 'groups' ] as $groupIdInf )
			{
				if ( $groupIdInf[ 'status' ] === 'ADMIN' || ( $groupIdInf[ 'status' ] === 'MODERATOR' ) && $groupIdInf[ 'role' ] !== 'ANALYST' )
				{
					$ids_arr[] = $groupIdInf[ 'groupId' ];
				}
			}
		}

		if ( ! empty( $ids_arr ) )
		{
			$ids_arr    = implode( ',', $ids_arr );
			$groupsList = $this->apiRequest( 'group.getInfo', 'GET', [
				'uids'   => $ids_arr,
				'fields' => 'pic_avatar,uid,name',
			]);

			foreach ( $groupsList as $groupInf )
			{
				$groups[] = $groupInf;
			}
		}

		return $groups;
	}

    public function sendPost ( PostingData $postingData ) : string
    {
		$postData = [
			'text_link_preview' => true,
		];

		if ( $postingData->channelType === 'group' )
		{
            $postData[ 'gid' ]  = $postingData->channelId;
            $postData[ 'type' ] = 'GROUP_THEME';
		}

        $postData[ 'attachment' ] = [
			'media' => []
		];

		if ( ! empty( $postingData->message ) )
            $postData['attachment']['media'][] = [ 'type' => 'text', 'text' => $postingData->message ];

		if ( ! empty( $postingData->link ) )
            $postData['attachment']['media'][] = [ 'type' => 'link', 'url' => $postingData->link ];

		if( ! empty( $postingData->uploadMedia ) )
		{
			if( $postingData->uploadMedia[0]['type'] === 'image' )
			{
				$uplServerSendData = [ 'count' => count( $postingData->uploadMedia ) ];

				if ($postingData->channelType === 'group' )
					$uplServerSendData[ 'gid' ] = $postingData->channelId;

				$uplServer = $this->apiRequest( 'photosV2.getUploadUrl', 'GET', $uplServerSendData );

				if ( isset( $uplServer[ 'upload_url' ] ) )
				{
					$uplServer = $uplServer[ 'upload_url' ];

					$images2 = [];
					foreach ( $postingData->uploadMedia as $i => $imageInf )
					{
						if ( function_exists( 'curl_file_create' ) )
							$images2['pic'.($i+1)] = curl_file_create( $imageInf['path'] );
						else
							$images2['pic'.($i+1)] = '@' . $imageInf['path'];
					}

					$uploadFile = Curl::getContents( $uplServer, 'POST', $images2, [], $this->proxy );
					$uploadFile = json_decode( $uploadFile, true );

					$okMediaJson = [];
					$okMediaJson['type'] = 'photo';
					$okMediaJson['list'] = [];

					foreach ( $uploadFile['photos'] as $photoTok )
					{
						$okMediaJson['list'][] = [ 'id' => $photoTok['token'] ];
					}

					$postData['attachment']['media'][] = $okMediaJson;
				}
			}
			else
			{
				$uplServerSendData = [
					'file_name' => mb_substr( $postingData->message, 0, 50, 'UTF-8' ),
					'file_size' => 0,
				];

				if ( $postingData->channelType === 'group' )
					$uplServerSendData[ 'gid' ] = $postingData->channelId;

				$videoUplServer = $this->apiRequest( 'video.getUploadUrl', 'GET', $uplServerSendData );

				if ( isset( $videoUplServer[ 'upload_url' ] ) )
				{
					$videoId = $videoUplServer['video_id'];
					$uploadURL = $videoUplServer['upload_url'];

					Curl::getContents( $uploadURL, 'POST', [
						'file' => function_exists( 'curl_file_create' ) ? curl_file_create( $postingData->uploadMedia[0]['path'] ) : '@' . $postingData->uploadMedia[0]['path'],
					], [], $this->proxy );

					$okMediaJson = [];
					$okMediaJson['type'] = 'movie-reshare';
					$okMediaJson['movieId'] = $videoId;

					$postData['attachment']['media'][] = $okMediaJson;
				}
			}
		}

        $postData['attachment'] = json_encode( $postData['attachment'] );

		$endPoint = 'mediatopic.post';

		$result = $this->apiRequest( $endPoint, 'POST', $postData );

		if ( isset( $result['error_msg'] ) || ! isset( $result['id'] ) )
            throw new $this->postException( $result['error_msg'] ?? 'Error' );

		if ( $postingData->channelType === 'group' )
			$pIdFull = $postingData->channelId . '/topic/' . $result['id'];
		else
			$pIdFull = $postingData->channelId . '/statuses/' . $result['id'];

		return $pIdFull;
	}

	public function getStats ( $post_id ) : array
    {
		$result = $this->apiRequest( 'mediatopic.getByIds', 'GET', [
			'topic_ids' => $post_id,
			'fields'    => 'media_topic.*',
		]);

		return [
			[
				'label' => fsp__( 'Comments' ),
				'value' => $result[ 'media_topics' ][ 0 ][ 'discussion_summary' ][ 'comments_count' ] ?? 0,
			],
			[
				'label' => fsp__( 'Likes' ),
				'value' => $result[ 'media_topics' ][ 0 ][ 'like_summary' ][ 'count' ] ?? 0,
			],
			[
				'label' => fsp__( 'Shares' ),
				'value' => $result[ 'media_topics' ][ 0 ][ 'reshare_summary' ][ 'count' ] ?? 0,
			],
		];
	}

	private function apiRequest( $endpoint, $HTTPMethod, $data = [] )
	{
		$data[ "application_key" ] = $this->authData->appPublicKey;
		$data[ "method" ]          = $endpoint;
		$data[ "sig" ]             = $this->calcSignature( $data );
		$data[ 'access_token' ]    = $this->authData->accessToken;

		$url = 'https://api.odnoklassniki.ru/fb.do';

		$HTTPMethod = $HTTPMethod === 'POST' ? 'POST' : ( $HTTPMethod === 'DELETE' ? 'DELETE' : 'GET' );

		$data1 = Curl::getContents( $url, $HTTPMethod, $data, [], $this->proxy, true );
		$data = json_decode( $data1, true );

		if ( ! is_array( $data ) )
		{
			if ( is_numeric( $data ) )
				$data = [ 'id' => $data ];
			else
				throw new $this->postException( 'Error' );
		}

		return $data;
	}

	private function calcSignature ( $parameters ) : string
    {
		ksort( $parameters );

		$requestStr = '';
		foreach ( $parameters as $key => $value )
		{
			$requestStr .= $key . '=' . $value;
		}

		$requestStr .= md5( $this->authData->accessToken . $this->authData->appSecret );

		return md5( $requestStr );
	}

	private function refreshAccessTokenIfNeed()
	{
		if ( ! empty( $this->authData->accessTokenExpiresOn ) && ( Date::epoch() + 30 ) > Date::epoch( $this->authData->accessTokenExpiresOn ) )
		{
			$this->refreshAccessToken();
		}
	}

    private function refreshAccessToken()
	{
		$url = 'https://api.odnoklassniki.ru/oauth/token.do';

		$postData = [
			'refresh_token' => $this->authData->refreshToken,
			'grant_type'    => 'refresh_token',
			'client_id'     => $this->authData->appId,
			'client_secret' => $this->authData->appSecret,
		];

		$response = Curl::getContents( $url, 'POST', $postData, [], $this->proxy, true );
		$params   = json_decode( $response, true );

		if ( isset( $params[ 'error_description' ] ) )
            throw new $this->authException( $params[ 'error_description' ] );

        $this->authData->accessToken = $params[ 'access_token' ];
		$this->authData->accessTokenExpiresOn = Date::dateTimeSQL( 'now', '+' . (int) $params[ 'expires_in' ] . ' seconds' );
	}

	public static function getAuthURL ( $appId, $callbackUrl ) : string
	{
		$permissions    = 'VALUABLE_ACCESS,SET_STATUS,PHOTO_CONTENT,LONG_ACCESS_TOKEN,PUBLISH_TO_STREAM,GROUP_CONTENT,VIDEO_CONTENT';

		return "https://www.odnoklassniki.ru/oauth/authorize?client_id=$appId&scope=$permissions&response_type=code&redirect_uri=$callbackUrl";
	}

}
