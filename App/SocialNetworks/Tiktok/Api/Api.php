<?php

namespace FSPoster\App\SocialNetworks\Tiktok\Api;


use FSPoster\GuzzleHttp\Client;

class Api
{

	public AuthData $authData;
	public ?string  $proxy = null;

	public string $authException = \Exception::class;
	public string $postException = \Exception::class;

	private $httpClient;

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

	public function getHTTPClient()
	{
		if( is_null( $this->httpClient ) )
		{
			$this->httpClient = new Client([
				'verify'        => false,
				'proxy'         => $this->proxy,
				'http_errors'   => false,
			]);
		}

		return $this->httpClient;
	}

	public function fetchAccessToken ( $code, $callbackUrl ) : Api
	{
		$postData = [
			'code'              => $code,
			'redirect_uri'      => $callbackUrl,
			'grant_type'        => 'authorization_code',
			'client_key'        => $this->authData->appClientKey,
			'client_secret'     => $this->authData->appClientSecret,
		];

		$params = $this->apiRequest( 'https://open.tiktokapis.com/v2/oauth/token/', 'POST', $postData );

		if ( isset( $params['error_description'] ) )
			throw new $this->authException( $params['error_description'] );

		$this->authData->accessToken = $params['access_token'];
		$this->authData->accessTokenExpiresOn = time() + (int)$params[ 'expires_in' ];
		$this->authData->refreshToken = $params['refresh_token'];

		return $this;
	}

	public function getMyInfo ()
	{
		$me = $this->apiRequest( 'https://open.tiktokapis.com/v2/user/info/', 'GET', [
			'fields'    => 'open_id,union_id,avatar_url_100,display_name,username',
		] );

		if ( ! empty( $me['error']['message'] ) || empty( $me['data']['user'] ) )
			throw new $this->authException( $me['error']['message'] ?? 'Error' );

		return $me;
	}

	private function prepareVideoChunks( string $videoFilePath ) :array
	{
		$fileSize = filesize($videoFilePath);

		$minChunkSize = 5 * 1024 * 1024; // 5 MB
		$maxChunkSize = 64 * 1024 * 1024; // 64 MB

		// Choose a chunk size that satisfies restrictions
		$chunkSize = $fileSize > $maxChunkSize ? $maxChunkSize : $fileSize;

		// Calculate the number of chunks
		$totalChunkCount = floor($fileSize / $chunkSize);

		// Ensure number of chunks is within TikTok's limits
		if ($totalChunkCount > 1000)
			throw new $this->postException("Video exceeds the maximum allowed chunks (1000). File size too large.");

		// Generate chunk metadata
		$chunks = [];
		$fileHandle = fopen($videoFilePath, 'rb');

		for ($chunkIndex = 0; $chunkIndex < $totalChunkCount; $chunkIndex++)
		{
			$isLastChunk = ($chunkIndex+1) == $totalChunkCount;

			$startByte = $chunkIndex * $chunkSize;
			$endByte = $isLastChunk ? $fileSize - 1 : min($startByte + $chunkSize - 1, $fileSize - 1);
			$chunkSizeActual = $endByte - $startByte + 1;

			// Read chunk data
			fseek($fileHandle, $startByte);
			$chunkData = fread($fileHandle, $chunkSizeActual);

			// Save chunk details
			$chunks[] = [
				'chunk_index'   => $chunkIndex,
				'chunk_size'    => $chunkSizeActual,
				'start_byte'    => $startByte,
				'end_byte'      => $endByte,
				'data'          => $chunkData, // Actual data can be uploaded here
			];
		}

		fclose($fileHandle);

		return [
			'file_size'         => $fileSize,
			'chunk_size'        => $chunkSize,
			'total_chunk_count' => $totalChunkCount,
			'chunks'            => $chunks,
		];
	}

	public function sendPost ( PostingData $postingData ) : string
	{
		$publishId = '0';

		if( $postingData->uploadMedia[0]['type'] === 'video' )
		{
			$videoFile = $postingData->uploadMedia[0]['path'];
			$videoChunks = $this->prepareVideoChunks( $videoFile );

			$requestPostContainer = $this->createPostContainer( $postingData, $videoChunks );

			$publishId = $requestPostContainer['data']['publish_id'];
			$uploadUrl = $requestPostContainer['data']['upload_url'];

			$this->uploadVideoChunks( $uploadUrl, $postingData->uploadMedia[0]['mime_type'], $videoChunks );

			/* Error olarsa throw edecek. Eger bu setri kechibse demek ugurlu netice alib. */
			$this->checkPublishStatus( $publishId, $videoChunks['file_size'] );
		}
		else
		{
			$requestPostContainer = $this->createPhotoPostContainer( $postingData );

			$publishId = $requestPostContainer['data']['publish_id'];

			/* Error olarsa throw edecek. Eger bu setri kechibse demek ugurlu netice alib. */
			$this->checkPublishStatus( $publishId );
		}

		return $publishId;
	}

	private function createPostContainer ( PostingData $postingData, array $videoChunks )
	{
		$data = [
			"post_info"     => [
				"title"             => $postingData->message,
				"privacy_level"     => $postingData->privacyLevel,
				"disable_duet"      => $postingData->disableDuet,
				"disable_comment"   => $postingData->disableComment,
				"disable_stitch"    => $postingData->disableStitch
			],
			"source_info"   => [
				"source"            => "FILE_UPLOAD",
				"video_size"        => $videoChunks['file_size'],
				"chunk_size"        => $videoChunks['chunk_size'],
				"total_chunk_count" => $videoChunks['total_chunk_count'],
			]
		];

		$requestPostContainer = $this->apiRequest( 'https://open.tiktokapis.com/v2/post/publish/video/init/', 'POST', json_encode( $data ) );

		if( empty( $requestPostContainer['data']['publish_id'] ) || empty( $requestPostContainer['data']['upload_url'] ) )
			throw new $this->postException( $requestPostContainer['error']['message'] ?? 'Error' );

		return $requestPostContainer;
	}

	private function createPhotoPostContainer ( PostingData $postingData )
	{
		$photosList = [];
		foreach ( $postingData->uploadMedia AS $photo ) {
			/**
			 * Bele olmasinin sebebi, TikTok photolari upload etmeye qoymur, yalniz URL ile qebul edir;
			 * Ve URL-deki domain de tiktok APP-de verified olmalidir. O sebebden bele yazilib;
			 * tiktokworker.fs-poster.com subdomaini cloudflare workerde ishleyir,
			 * ve tek ishi url parametrini alib redirect vermekdir.
			 */
			$photosList[] = 'https://tiktokworker.fs-poster.com/?url=' . urlencode($photo['url']);
		}

		$data = [
			"media_type"    => "PHOTO",
			"post_mode"     => "DIRECT_POST",
			"post_info"     => [
				"title"             => $postingData->message,
				"description"       => $postingData->message,
				"privacy_level"     => $postingData->privacyLevel,
				"disable_comment"   => $postingData->disableComment,
				"auto_add_music"    => $postingData->autoAddMusicToPhoto
			],
			"source_info"   => [
				"source"            => "PULL_FROM_URL",
				"photo_images"      => $photosList,
				"photo_cover_index" => 0
			]
		];

		$requestPostContainer = $this->apiRequest( 'https://open.tiktokapis.com/v2/post/publish/content/init/', 'POST', json_encode( $data ) );

		if( empty( $requestPostContainer['data']['publish_id'] ) )
			throw new $this->postException( $requestPostContainer['error']['message'] ?? 'Error' );

		return $requestPostContainer;
	}

	private function uploadVideoChunks ( string $uploadUrl, string $mimeType, array $videoChunks )
	{
		foreach ( $videoChunks['chunks'] as $chunk )
		{
			$this->apiRequest( $uploadUrl, 'PUT', $chunk['data'], [
				'Content-Range'     => sprintf( 'bytes %s-%s/%s', (string)$chunk['start_byte'], (string)$chunk['end_byte'], $videoChunks['file_size'] ),
				'Content-Length'    => $chunk['chunk_size'],
				'Content-Type'      => $mimeType
			], [], false );
		}
	}

	private function checkPublishStatus ( $publishId, $uploadedFileSize = null )
	{
		$tries = 0;

		while( true )
		{
			$getStatus = $this->apiRequest( 'https://open.tiktokapis.com/v2/post/publish/status/fetch/', 'POST', json_encode([
				'publish_id' =>  $publishId,
			]));

			$status = $getStatus['data']['status'] ?? '';

			if( $status == 'FAILED' )
				throw new $this->postException( $getStatus['data']['fail_reason'] ?? 'Something went wrong' );

			if( in_array( $status, ['SEND_TO_USER_INBOX', 'PUBLISH_COMPLETE'] ) )
				return;

			if( !is_null( $uploadedFileSize ) && $getStatus['data']['uploaded_bytes'] != $uploadedFileSize )
				throw new $this->postException( 'Something went wrong while uploading video chunks!' );

			$tries++;

			if( $tries > 20 )
				break;

			sleep(5);
		}

		throw new $this->postException( 'Even after 100 seconds, we are unable to receive a successful response from the TikTok API regarding the publication of the video. There is a possibility that your video has been successfully published; please check your TikTok account.' );
	}

	private function apiRequest( $url, $HTTPMethod, $data = [], $headers = [], $checkResponse = true )
	{
		$headers['Authorization'] = 'Bearer ' . $this->authData->accessToken;
		$options = [ 'headers' => $headers ];

		if( $HTTPMethod === 'GET' )
			$url .= '?' . http_build_query( $data );
		else if( ! empty( $data ) )
		{
			if( is_array( $data ) )
				$options['form_params'] = $data;
			else
				$options['body'] = $data;
		}

		$request = $this->getHTTPClient()->request( $HTTPMethod, $url, $options );

		if( ! $checkResponse )
			return $request;

		$data = json_decode( $request->getBody()->getContents(), true );

		if ( ! is_array( $data ) )
			throw new $this->postException( 'Something went wrong!' );

		return $data;
	}

	private function refreshAccessTokenIfNeed()
	{
		if ( ! empty( $this->authData->accessTokenExpiresOn ) && ( time() + 30 ) > $this->authData->accessTokenExpiresOn )
		{
			$this->refreshAccessToken();
		}
	}

	private function refreshAccessToken()
	{
		$postData = [
			'refresh_token' => $this->authData->refreshToken,
			'grant_type'    => 'refresh_token',
			'client_key'     => $this->authData->appClientKey,
			'client_secret' => $this->authData->appClientSecret,
		];

		$params = $this->apiRequest( 'https://open.tiktokapis.com/v2/oauth/token/', 'POST', $postData );

		if ( isset( $params['error_description'] ) )
			throw new $this->authException( $params['error_description'] );

		$this->authData->accessToken = $params['access_token'];
		$this->authData->accessTokenExpiresOn = time() + (int)$params['expires_in'];
	}

	public static function getAuthURL ( $clientKey, $state, $callbackUrl ) : string
	{
		$scopes    = 'user.info.basic,video.upload,user.info.profile,video.publish';

		$callbackUrl = urlencode( $callbackUrl );

		return "https://www.tiktok.com/v2/auth/authorize/?client_key={$clientKey}&response_type=code&scope={$scopes}&redirect_uri={$callbackUrl}&state={$state}";
	}

}
