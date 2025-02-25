<?php

namespace FSPoster\App\SocialNetworks\Reddit\Api;

use Exception;
use FSPoster\App\Providers\Helpers\Date;
use FSPoster\App\Providers\Helpers\Helper;
use FSPoster\GuzzleHttp\Client;
use FSPoster\GuzzleHttp\Psr7\MultipartStream;

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
		$endpoint = 'https://oauth.reddit.com/api/submit';
		$body     = null;

		if ( $postingData->channelType !== 'account' )
		{
            $srRemoteId = explode(':', $postingData->channelId);
            $postData['sr'] = $srRemoteId[0];

			if ( $srRemoteId[1] != 'no_flair' )
			{
                $postData['flair_text'] = $postingData->flairText;
                $postData['flair_id']   = $postingData->flairId;
			}
		}
		else
		{
            $postData['sr']          = 'u_' . $postingData->username;
            $postData['submit_type'] = 'profile';
		}

        $postData['title'] = $postingData->title;

        if( ! empty( $postingData->message ) )
            $postData['text'] = $postingData->message;

	    $postData[ 'kind' ] = 'self';

		if ( count( $postingData->uploadMedia ) === 1 && $postingData->uploadMedia[0]['type'] === 'image' )
		{
			$imageurl = $this->uploadImage( $postingData->uploadMedia[0]['path'] );

            $postData['resubmit']       = 'true';
            $postData['send_replies']   = 'true';
            $postData['api_type']       = 'json';
            $postData['kind']           = 'image';
            $postData['url']            = $imageurl;
		}
		else if ( count( $postingData->uploadMedia ) > 1 && $postingData->uploadMedia[0]['type'] === 'image' )
		{
			$endpoint = 'https://oauth.reddit.com/api/submit_gallery_post.json?raw_json=1';

            $postData['send_replies']       = true;
            $postData['api_type']           = 'json';
            $postData['kind']               = 'self';
            $postData['show_error_list']    = true;
            $postData['spoiler']            = false;
            $postData['nsfw']               = false;
            $postData['original_content']   = false;
            $postData['post_to_twitter']    = false;
            $postData['sendreplies']        = true;
            $postData['validate_on_submit'] = true;

			foreach ( $postingData->uploadMedia as $image )
			{
				$imageurl = $this->uploadImage( $image['path'] );
				$imageID = explode( '/', $imageurl );

				if ( $imageID !== false )
				{
					$postData[ 'items' ][] = [
						'caption'      => '',
						'outbound_url' => '',
						'media_id'     => end( $imageID ),
					];
				}
			}

			$body     = json_encode( $postData );
            $postData = null;
		}
		else if ( ! empty( $postingData->uploadMedia ) && $postingData->uploadMedia[0]['type'] === 'video' )
		{
            $postData['kind'] = 'video';
            $postData['url']  = $postingData->uploadMedia[0]['url'];
		}
		else if ( ! empty( $postingData->link ) )
		{
            $postData['kind'] = 'link';
            $postData['url']  = $postingData->link;
		}

		$result = $this->apiRequest( $endpoint, 'POST', $postData, $body );

		if ( isset( $result['error']['message'] ) )
			throw new $this->postException( $result['error']['message'] );

        if ( ! empty( $result['json']['errors'] ) && is_array( $result['json']['errors'] ) )
		{
			$error = reset( $result['json']['errors'] );
			throw new $this->postException( self::getErrorMessage( $error ) );
		}

		$postId = '';

        if ( isset( $result['jquery'], $result['success'] ) )
		{
			if ( $result['success'] === true )
			{
                preg_match( '/comments\/(.+?)\//', json_encode( $result, JSON_UNESCAPED_SLASHES ), $matches );

                if ( ! empty( $matches[1] ) )
	                $postId = $matches[1];
			}
			else if ( ! empty( $result['jquery'][14][3][0] ) && is_string( $result['jquery'][14][3][0] ) )
				throw new $this->postException( $result['jquery'][14][3][0] );
			else if ( ! empty( $result['jquery'][22][3][0] ) && is_string( $result['jquery'][22][3][0] ) )
				throw new $this->postException( $result['jquery'][22][3][0] );
			else if( ! $result['success'] )
				throw new $this->postException( 'Unknown error!' );
		}
		else if ( ! isset( $result['json'] ) )
		{
			if ( isset( $result['error'] ) && $result['error'] == 403 )
				throw new $this->postException( fsp__( 'It seems that your account is banned by Reddit or you do not have permission to share posts on Reddit' ) );
			else if ( ! empty( $result['error'] ) && ! empty( $result['message'] ) )
				throw new $this->postException( $result['message'] );
			else
				throw new $this->postException( fsp__( 'Error result' ) . esc_html( json_encode( $result ) ) );
		}
		else
		{
			if ( empty( $result['json']['data']['id'] ) )
			{
				sleep( 10 );
				$postId = self::getLastPostID( $postingData->username );
			}
			else
			{
				$postId = $result['json']['data']['id'];
			}

			if ( strpos( $postId, '_' ) !== false )
			{
				$id1 = explode( '_', $postId );

				if ( isset( $id1[1] ) )
					$postId = $id1[1];
			}

			$commentThingId = empty( $result['json']['data']['name'] ) ? null : $result['json']['data']['name'];
		}

		if ( ! empty( $postingData->firstComment ) && ! empty( $postId ) )
		{
			$thingID = empty($commentThingId) ? ( 't3_' . $postId ) : $commentThingId;

			self::writeComment( $postingData->firstComment, $thingID );
		}

		return (string)$postId;
	}

	public function uploadImage ( $image )
	{
		$res = $this->apiRequest( 'https://oauth.reddit.com/api/media/asset.json?raw_json=1', 'POST', [
			'api_type' => 'json',
			'filepath' => basename( $image ),
			'mimetype' => Helper::mimeContentType( $image ),
		] );

		if ( ! isset( $res['args']['fields'], $res['args']['action'] ) )
			throw new $this->postException( fsp__( 'Unknown error' ) );

		$uploadData = [];

		foreach ( $res['args']['fields'] as $field )
		{
			if ( ! isset( $field['name'], $field['value'] ) )
				throw new $this->postException( fsp__( 'Unknown error' ) );

			$uploadData[] = [
				'name'     => $field[ 'name' ],
				'contents' => $field[ 'value' ],
			];
		}

		$uploadData[] = [
			'name'     => 'file',
			'filename' => 'blob',
			'contents' => file_get_contents( $image ),
			'headers'  => [ 'Content-Type' => Helper::mimeContentType( $image ) ],
		];

		$body = new MultipartStream( $uploadData, '----WebKitFormBoundaryo1KdMBb4Cj4G8xhU' );

		$uploadURL = trim( $res['args']['action'], '/' );

		if ( strpos( $uploadURL, 'https' ) === false )
			$uploadURL = 'https://' . $uploadURL;

		$client = new Client(['verify'=>false]);
		try
		{
			$uploaded = $client->post( $uploadURL, [
				'proxy'   => empty( $this->proxy ) ? null : $this->proxy,
				'headers' => [
					'Content-Length' => strlen( $body ),
					'User-Agent'     => 'Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36',
					'Origin'         => 'https://www.reddit.com',
					'Referer'        => 'https://www.reddit.com/',
					'Content-Type'   => 'multipart/form-data; boundary=----WebKitFormBoundaryo1KdMBb4Cj4G8xhU',
				],
				'body'    => $body,
			] )->getHeaders();
		}
		catch ( Exception $e )
		{
			throw new $this->postException( $e->getMessage() );
		}

		if ( ! isset( $uploaded['Location'][0] ) )
			throw new $this->postException( fsp__( 'Unknown error' ) );

		return urldecode( $uploaded['Location'][0] );
	}

	public function getLastPostID ( $username )
	{
		$c = new Client(['verify'=>false]);

		try
		{
			$get = $c->get( sprintf( 'https://www.reddit.com/user/%s', $username ), [
				'headers' => [
					'Authorization' => 'bearer ' . $this->authData->accessToken,
					'User-Agent'    => 'Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36',
				],
				'proxy'   => empty( $this->proxy ) ? null : $this->proxy,
			] )->getBody()->getContents();

			if ( ! preg_match( '/comments\/(.+?)\//', $get, $matches ) )
				return '';

			if ( empty( $matches[1] ) )
				return '';

			return $matches[1];
		}
		catch ( Exception $e )
		{
			return '';
		}
	}

	public function writeComment ( string $comment, string $mediaId ) : string
    {
		$sendData = [
			'api_type' => 'json',
			'thing_id' => $mediaId,
			'text'     => $comment,
		];

		$response = $this->apiRequest( 'https://oauth.reddit.com/api/comment', 'POST', $sendData );

		if ( isset( $response[ 'error' ] ) )
		{
			$error = $response['error']['message'] ?? ( $response['message'] ?? $response['error'] );

			throw new $this->postException( $error );
		}

		if ( ! isset( $response[ 'json' ] ) )
			throw new $this->postException( fsp__( 'Unknown error' ) );

		$response = $response[ 'json' ];

		if ( ! empty( $response[ 'errors' ] ) )
			throw new $this->postException( fsp__( 'Unknown error' ) );

		if ( ! isset( $response[ 'data' ] ) && ! isset( $response[ 'data' ][ 'things' ] ) && ! isset( $response[ 'data' ][ 'things' ][ 0 ] ) && ! isset( $response[ 'data' ][ 'things' ][ 0 ][ 'data' ] ) )
			throw new $this->postException( fsp__( 'Unknown error' ) );

		$response = $response[ 'data' ][ 'things' ][ 0 ][ 'data' ];

		if ( empty( $response[ 'permalink' ] ) )
			throw new $this->postException( fsp__( 'Unknown error' ) );

	    $url = str_replace( "/r/u_", "", $response[ 'permalink' ] );

	    return sprintf( "https://www.reddit.com/user/%s", $url );
	}

	public function apiRequest ( $url, $method, $data = null, $body = null, $isAuthTypeBearer = true )
	{
		$method = strtolower( $method ) === 'post' ? 'post' : 'get';

		$c = new Client(['verify'=>false]);

		$authType = $isAuthTypeBearer ? 'bearer' : 'Basic';

		$options = [
			'headers'     => [
				'Authorization' => sprintf( '%s %s', $authType, $this->authData->accessToken ),
				'User-Agent'    => 'Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36',
			],
			'form_params' => $data,
			'body'        => $body,
			'proxy'       => empty( $this->proxy ) ? null : $this->proxy,
		];

		if ( ! empty( $body ) )
			$options[ 'headers' ][ 'Content-Type' ] = 'application/json';

		try
		{
			$result = $c->$method( $url, $options )->getBody()->getContents();
		}
		catch ( Exception $e )
		{
			if ( ! method_exists( $e, 'getResponse' ) )
				throw new $this->postException( $e->getMessage() );

			$resp = $e->getResponse();

			if ( is_null( $resp ) || ! method_exists( $resp, 'getBody' ) )
				throw new $this->postException( $e->getMessage() );

			$result = $resp->getBody()->getContents();
		}

        if ( str_contains( $result, "Your request has been blocked due to a network policy." ) )
        {
            throw new $this->postException( 'Reddit have blocked this request. Please try using a proxy.' );
        }

		$result_arr = json_decode( $result, true );

		if ( ! is_array( $result_arr ) )
        {
            throw new $this->postException( 'Error data' );
        }

		return $result_arr;
	}

	public function fetchAccessToken ( $code, $callbackUrl ) : Api
    {
        $appSecret = urlencode( $this->authData->appSecret );
		$appId     = urlencode( $this->authData->appId );

		$url = 'https://www.reddit.com/api/v1/access_token';

		$postData = [
			'grant_type'   => 'authorization_code',
			'code'         => $code,
			'redirect_uri' => $callbackUrl,
		];

        $this->authData->accessToken = base64_encode( $appId . ':' . $appSecret );

		$response = $this->apiRequest( $url, 'POST', $postData, null, false );

		if ( isset( $response[ 'error' ][ 'message' ] ) )
            throw new $this->authException( $response['error']['message'] );

		$this->authData->accessToken = $response['access_token'];
		$this->authData->refreshToken = $response['refresh_token'];
		$this->authData->accessTokenExpiresOn = Date::dateTimeSQL( 'now', '+' . (int) $response['expires_in'] . ' seconds' );

        return $this;
	}

	public function getMyInfo ()
	{
		$me = $this->apiRequest( 'https://oauth.reddit.com/api/v1/me', 'GET' );

		if ( isset( $me['error']['message'] ) || empty( $me['id'] ) )
			throw new $this->authException( $me['error']['message'] ?? 'Error' );

		return $me;
	}

	public function getFlairs( $subreddit )
	{
		$flairs = $this->apiRequest( 'https://oauth.reddit.com/r/' . $subreddit . '/api/link_flair_v2', 'GET' );

		if( isset( $flairs['error'] ) )
			return [];

		return $flairs;
	}

	public function searchSubreddits( $search ) : array
	{
		$searchSubreddits = $this->apiRequest( 'https://oauth.reddit.com/api/search_subreddits', 'POST', [
			'query'                  => $search,
			'include_over_18'        => true,
			'exact'                  => false,
			'include_unadvertisable' => true,
		] );

		$preventDuplicates = [];

		foreach ( $searchSubreddits[ 'subreddits' ] ?? [] AS $subreddit )
		{
			$preventDuplicates[ $subreddit[ 'name' ] ] = $subreddit;
		}

		// for fixing Reddit API bug
		$searchSubreddits = $this->apiRequest( 'https://oauth.reddit.com/api/search_subreddits', 'POST', [
			'query' => $search,
			'exact' => true,
		] );

		foreach ( $searchSubreddits[ 'subreddits' ] ?? [] AS $subreddit )
		{
			$preventDuplicates[ $subreddit[ 'name' ] ] = $subreddit;
		}

		return array_values( $preventDuplicates );
	}

	public function searchExactSubreddit ( $search )
	{
		$searchSubreddits = $this->apiRequest( 'https://oauth.reddit.com/api/search_subreddits', 'POST', [
			'query'                  => $search,
			'include_over_18'        => true,
			'exact'                  => true,
			'include_unadvertisable' => true,
		] );

		return $searchSubreddits['subreddits'] ?? [];
	}

	public static function getErrorMessage ( $error )
	{
		$reddit_errors = [
			'NO_URL'   => fsp__( 'Required URL (or featured image path) not found' ),
			'TOO_LONG' => fsp__( 'Title is too long. Maximum allowed character limit is 300. You can enable auto-cut option from Reddit settings.' ),
			'NO_TEXT'  => fsp__( 'Content can\'t be empty' ),
		];

		if ( ! empty( $error[ 0 ] ) && array_key_exists( $error[ 0 ], $reddit_errors ) )
		{
			return $reddit_errors[ $error[ 0 ] ];
		}
		else
		{
			return json_encode( $error );
		}
	}

	private function refreshAccessTokenIfNeed()
	{
		if ( ! empty( $this->authData->accessTokenExpiresOn ) && ( Date::epoch() + 30 ) > Date::epoch( $this->authData->accessTokenExpiresOn ) )
		{
			$this->refreshAccessToken();
		}
	}

	public function refreshAccessToken ()
	{
		$appRemoteId = urlencode( $this->authData->appId );
		$appSecret = urlencode( $this->authData->appSecret );

		$refreshToken = $this->authData->refreshToken;
		$this->authData->accessToken = base64_encode( $appRemoteId . ':' . $appSecret );

		$response = self::apiRequest(
			'https://www.reddit.com/api/v1/access_token',
			'POST',
			[
				'grant_type'    => 'refresh_token',
				'refresh_token' => $refreshToken,
			], null, false );

		if ( isset( $response[ 'error' ][ 'message' ] ) )
			throw new $this->authException( $response['error']['message'] );

		if( empty( $response['access_token'] ) )
			throw new $this->authException( 'Error! Access token expired!' );

		$this->authData->accessToken = $response['access_token'];
		$this->authData->accessTokenExpiresOn = Date::dateTimeSQL( 'now', '+' . (int) $response[ 'expires_in' ] . ' seconds' );
	}

	public static function getAuthURL ( $appId, $callbackUrl, $state ) : string
	{
		$appId       = urlencode( $appId );
		$callbackUrl = urlencode( $callbackUrl );

		return sprintf( 'https://www.reddit.com/api/v1/authorize?client_id=%s&response_type=code&redirect_uri=%s&duration=permanent&scope=identity,submit,flair,read&state=%s', $appId, $callbackUrl, $state );
	}

}