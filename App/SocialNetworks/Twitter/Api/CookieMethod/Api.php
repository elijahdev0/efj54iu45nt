<?php

namespace FSPoster\App\SocialNetworks\Twitter\Api\CookieMethod;

use Exception;

use FSPoster\App\Providers\Helpers\Helper;
use FSPoster\App\SocialNetworks\Twitter\Api\PostingData;
use FSPoster\GuzzleHttp\Client;

class Api
{

	public AuthData $authData;
	public ?string  $proxy = null;

	public string $authException = \Exception::class;
	public string $postException = \Exception::class;

	private array $userInfo = [];
	private string $csrfToken;

	private ?Client $client = null;
	private string $bearerToken = 'AAAAAAAAAAAAAAAAAAAAANRILgAAAAAAnNwIzUejRCOuH5E6I8xnZz4puTs%3D1Zv7ttfk8LF81IUq16cHjhLTvJu4FA33AGWWjCpTnA';

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

	private function getClient ()
	{
		if( empty( $this->client ) )
		{
			$options = [
				'verify'      => false,
				'http_errors' => false,
				'proxy'       => empty( $this->proxy ) ? null : $this->proxy,
				'headers'     => [
					'Authorization'                 => 'Bearer ' . $this->bearerToken,
					'User-agent'                    => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:130.0) Gecko/20100101 Firefox/130.0',
					'Content-type'                  => 'application/json',
					'Origin'                        => 'https://x.com',
					'Referer'                       => 'https://x.com/home',
					'X-Client-UUID'                 => '4e188b71-d93d-45e8-96dc-ecc0a94dbe71',
					'x-twitter-auth-type'           => 'OAuth2Session',
					'x-twitter-client-language'     => 'en',
					'x-twitter-active-user'         => 'yes',
					'x-client-transaction-id'       => 'Ugf29pLMxWueqJE+RfurnyAVLVpsG0LbHrmYYBTyN018G0g7KsT4sLCEAAn3XvHlFLhIzFCxAGf1NBdrmvAS88OZrCftUQ'
				]
			];

			$this->client = new Client( $options );
		}

		return $this->client;
	}

	public function sendPost ( PostingData $postingData ) : string
    {
		$sendMedia = [];
		$message = $postingData->message;

		if ( ! empty( $postingData->link ) )
			$message .= "\n" . $postingData->link;

		if ( ! empty( $postingData->uploadMedia ) )
		{
			foreach ( $postingData->uploadMedia as $c => $media )
			{
				if ( $c > 3 )
					break;

				$mediaType = $media['type'] === 'video' ? 'tweet_video' : 'tweet_image';

				$mediaId = $this->uploadMedia( $media['path'], $mediaType );

				if ( ! empty( $mediaId ) )
				{
					$sendMedia[] = [
						'media_id'     => $mediaId,
						'tagged_users' => []
					];
				}
			}
		}

        $postId = $this->createTweet( $message, $sendMedia );

		if ( ! empty( $postingData->firstComment ) )
			$this->createTweet( $postingData->firstComment, [], $postId );

		return (string)$postId;
	}

	private function getCSRFToken () : string
    {
		if ( empty( $this->csrfToken ) )
		{
			$randomCSRF = md5( mt_rand( 1000, 9999 ) . microtime() );

			try
			{
				$getCSRF = $this->getClient()->get( 'https://x.com/i/api/graphql/hXkPYUuiQAltqmDjG3G9Dw/Viewer', [
					'headers' => [
						'cookie'       => 'ct0=' . $randomCSRF . '; auth_token=' . $this->authData->authToken . ';',
						//'cookie'        => 'ct0=' . $randomCSRF . '; auth_token=4caf2cd88b435a3e8ce8b2fea9cf47e3b3a07793;',
						'x-csrf-token' => $randomCSRF
					],
					'query'   => [
						'variables' => json_encode( [
							'withUserResults'            => true,
							'withSuperFollowsUserFields' => true,
							'withNftAvatar'              => false
						] )
					]
				] );

				$body = $getCSRF->getBody()->getContents();

				$body = json_decode( $body, true );

				$this->userInfo['twid']             = $body[ 'data' ][ 'viewer' ][ 'user_results' ][ 'result' ][ 'rest_id' ] ?? '';
				$this->userInfo['username']         = $body[ 'data' ][ 'viewer' ][ 'user_results' ][ 'result' ][ 'legacy' ][ 'screen_name' ] ?? '';
				$this->userInfo['name']             = $body[ 'data' ][ 'viewer' ][ 'user_results' ][ 'result' ][ 'legacy' ][ 'name' ] ?? '';
				$this->userInfo['profile_picture']  = $body[ 'data' ][ 'viewer' ][ 'user_results' ][ 'result' ][ 'legacy' ][ 'profile_image_url_https' ] ?? '';

				foreach ( $getCSRF->getHeader( 'set-cookie' ) as $setCookie )
				{
					preg_match( '/ct0=(.*?);/', $setCookie, $cookie );

					if ( ! empty( $cookie[ 1 ] ) )
					{
						$this->csrfToken = $cookie[ 1 ];
					}
				}
			}
			catch ( Exception $e )
			{
				throw new $this->authException( $e->getMessage() );
			}
		}

		return $this->csrfToken;
	}

	public function createTweet ( $content, $mediaList, $replyMmediaId = false ) : string
    {
		$queryId = 'xT36w0XM3A8jDynpkram2A';

		$sendData = [
			"features"  => [
				"articles_preview_enabled"  =>  true,
				"c9s_tweet_anatomy_moderator_badge_enabled" =>  true,
				"communities_web_enable_tweet_community_results_fetch"  =>  true,
				"creator_subscriptions_quote_tweet_preview_enabled" =>  false,
				"freedom_of_speech_not_reach_fetch_enabled" =>  true,
				"graphql_is_translatable_rweb_tweet_is_translatable_enabled"    =>  true,
				"longform_notetweets_consumption_enabled"   =>  true,
				"longform_notetweets_inline_media_enabled"  =>  true,
				"longform_notetweets_rich_text_read_enabled"    =>  true,
				"responsive_web_edit_tweet_api_enabled" =>  true,
				"responsive_web_enhance_cards_enabled"  =>  false,
				"responsive_web_graphql_exclude_directive_enabled"  =>  true,
				"responsive_web_graphql_skip_user_profile_image_extensions_enabled" =>  false,
				"responsive_web_graphql_timeline_navigation_enabled"    =>  true,
				"responsive_web_twitter_article_tweet_consumption_enabled"  =>  true,
				"rweb_tipjar_consumption_enabled"   =>  true,
				"rweb_video_timestamps_enabled" =>  true,
				"standardized_nudges_misinfo"   =>  true,
				"tweet_awards_web_tipping_enabled"  =>  false,
				"tweet_with_visibility_results_prefer_gql_limited_actions_policy_enabled"   =>  true,
				"verified_phone_label_enabled"  =>  false,
				"view_counts_everywhere_api_enabled"    =>  true
			],
			"queryId"   => $queryId,
			"variables" => [
				"dark_request"                  => false,
				"disallowed_reply_options"      => null,
				"media"                         => [
					"media_entities"     => [],
					"possibly_sensitive" => false
				],
				"semantic_annotation_ids"       => [],
				"tweet_text"                    => $content,
				"withDownvotePerspective"       => false,
				"withReactionsMetadata"         => false,
				"withReactionsPerspective"      => false,
				"withSuperFollowsTweetFields"   => true,
				"withSuperFollowsUserFields"    => true
			]
		];

		if( ! empty( $mediaList ) )
		{
			$sendData['variables']['media'] = [
				'media_entities'     => $mediaList,
				'possibly_sensitive' => false
			];
		}

		if( ! empty( $replyMmediaId ) )
		{
			$sendData['variables']['reply'] = [
				"exclude_reply_user_ids"    => [],
				"in_reply_to_tweet_id"      => $replyMmediaId
			];
		}

		$csrfToken = $this->getCSRFToken();

		$response = $this->getClient()->post( 'https://x.com/i/api/graphql/'.$queryId.'/CreateTweet', [
			'headers' => [
				'x-csrf-token' => $csrfToken,
				'cookie'       => 'ct0=' . $csrfToken . '; auth_token=' . $this->authData->authToken . ';'
			],
			'body'    => json_encode( $sendData )
		] )->getBody();

		$resArr = json_decode( $response, true );

		if ( ! is_array( $resArr ) )
			throw new $this->postException( $response );

		if ( empty( $resArr[ 'data' ][ 'create_tweet' ][ 'tweet_results' ][ 'result' ][ 'rest_id' ] ) )
			throw new $this->postException( $resArr[ 'errors' ][ 0 ][ 'message' ] ?? fsp__( 'Unknown error' ) );

		return (string)$resArr[ 'data' ][ 'create_tweet' ][ 'tweet_results' ][ 'result' ][ 'rest_id' ];
	}

	private function uploadMedia ( $file, $type )
	{
		$csrfToken = $this->getCSRFToken();
		$header    = [
			'x-csrf-token' => $csrfToken,
			'cookie'       => 'ct0=' . $csrfToken . '; auth_token=' . $this->authData->authToken . ';',
			//'cookie'        => 'ct0=' . $csrfToken . '; auth_token=4caf2cd88b435a3e8ce8b2fea9cf47e3b3a07793;',
		];

		try
		{
			$uploadINIT = ( string ) $this->getClient()->post( 'https://upload.x.com/i/media/upload.json', [
				'headers' => $header,
				'query'   => [
					'command'        => 'INIT',
					'total_bytes'    => filesize( $file ),
					'media_type'     => Helper::mimeContentType( $file ),
					'media_category' => $type
				]
			] )->getBody();
			$uploadINIT = json_decode( $uploadINIT );

			if ( empty( $uploadINIT->media_id ) )
				throw new $this->postException();

			$mediaID = $uploadINIT->media_id_string;
			//$mediaID = ( int ) $uploadINIT->media_id;
		}
		catch ( Exception $e )
		{
			throw new $this->postException( $e->getMessage() );
		}

		try
		{
			$segmentIndex = 0;
			$handle       = fopen( $file, 'rb' );

			if ( empty( $handle ) )
				throw new $this->postException();

			while ( ! feof( $handle ) )
			{
				$this->getClient()->post( 'https://upload.x.com/i/media/upload.json', [
					'headers'   => $header,
					'query'     => [
						'command'       => 'APPEND',
						'segment_index' => $segmentIndex,
						'media_id'      => $mediaID,
					],
					'multipart' => [
						[
							'name'     => 'media',
							'contents' => fread( $handle, 250000 ),
							'filename' => 'blob',
							'headers'  => [
								'Content-Type' => 'application/octet-stream',
							]
						]
					],
				] );

				$segmentIndex++;
			}

			fclose( $handle );
		}
		catch ( Exception $e )
		{
			throw new $this->postException( $e->getMessage() );
		}

		try
		{
			$uploadFINALIZE = ( string ) $this->getClient()->post( 'https://upload.x.com/i/media/upload.json', [
				'headers' => $header,
				'query'   => [
					'command'  => 'FINALIZE',
					'media_id' => $mediaID,
				],
			] )->getBody();

			$uploadFINALIZE = json_decode( $uploadFINALIZE );

			if ( empty( $uploadFINALIZE->media_id ) )
				throw new $this->postException();

			if ( $type === 'tweet_video' )
			{
				if ( ! empty( $uploadFINALIZE->processing_info->state ) )
				{
					$uploaded = false;

					while ( ! $uploaded )
					{
						$uploadSTATUS = ( string ) $this->getClient()->get( 'https://upload.x.com/i/media/upload.json', [
							'headers' => $header,
							'query'   => [
								'command'  => 'STATUS',
								'media_id' => $mediaID,
							],
						] )->getBody();
						$uploadSTATUS = json_decode( $uploadSTATUS );

						if ( ! empty( $uploadSTATUS->processing_info->state ) && $uploadSTATUS->processing_info->state === 'succeeded' )
						{
							$uploaded = true;
						}

						usleep(0.2 * 1000 * 1000);
					}
				}
				else
				{
					throw new $this->postException();
				}
			}
		}
		catch ( Exception $e )
		{
			throw new $this->postException( $e->getMessage() );
		}

		return $mediaID;
	}

	public function getMyInfo () : array
    {
		$this->getCSRFToken();

		return [
			'name'              => $this->userInfo['name'],
			'screen_name'       => $this->userInfo['username'],
			'id_str'            => $this->userInfo['twid'],
			'profile_image_url' => $this->userInfo['profile_picture']
		];
	}

}
