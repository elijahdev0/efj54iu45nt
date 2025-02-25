<?php

namespace FSPoster\App\SocialNetworks\Telegram\Api;

use FSPoster\GuzzleHttp\Client;// doit ?


class Api
{

	public ?Client  $client = null;
    public AuthData $authData;
    public ?string  $proxy = null;

    public string $authException = \Exception::class;
    public string $postException = \Exception::class;

    public function setProxy ( ?string $proxy ) : self
    {
        $this->proxy = $proxy;

        return $this;
    }

    public function setAuthData ( AuthData $authData ) : self
	{
        $this->authData = $authData;

		return $this;
	}

    public function setAuthException( string $exceptionClass ) : self
    {
        $this->authException = $exceptionClass;

        return $this;
    }

    public function setPostException( string $exceptionClass ) : self
    {
        $this->postException = $exceptionClass;

        return $this;
    }

    public function getClient () : Client
    {
		if( is_null( $this->client ) )
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

	public function getBotInfo () : array
    {
		$myInfo = $this->sendRequest( 'getMe' );

		if ( ! $myInfo[ 'ok' ] )
		{
			return [];
		}

		return [
			'id'       => $myInfo[ 'result' ][ 'id' ] ?? '',
			'name'     => $myInfo[ 'result' ][ 'first_name' ] ?? '',
			'username' => $myInfo[ 'result' ][ 'username' ] ?? '',
		];
	}

	public function getChatInfo ( string $chatId ) : array
    {
		if ( ! is_numeric( $chatId ) && strpos( $chatId, '@' ) !== 0 )
		{
			$chatId = '@' . $chatId;
		}

		$myInfo = $this->sendRequest( 'getChat', [ 'chat_id' => $chatId ] );

		if ( ! $myInfo[ 'ok' ] )
		{
			return [];
		}

		return [
			'id'       => $myInfo[ 'result' ][ 'id' ] ?? '',
			'name'     => $myInfo[ 'result' ][ 'title' ] ?? ( $myInfo[ 'result' ][ 'first_name' ] ?? '' ),
			'username' => $myInfo[ 'result' ][ 'username' ] ?? '',
		];
	}

	public function getActiveChats () : array
    {
		$updates = $this->sendRequest( 'getUpdates', [ 'allowed_updates' => 'message,channel_post' ] );

		if ( ! $updates[ 'ok' ] )
		{
			return [];
		}

		$list      = [];
		$uniqChats = [];

		foreach ( $updates[ 'result' ] as $update )
		{
			if ( ! isset( $update[ 'message' ] ) && ! isset( $update[ 'my_chat_member' ] ) )
			{
				continue;
			}

			$chat = $update[ 'message' ][ 'chat' ] ?? ( $update[ 'my_chat_member' ][ 'chat' ] ?? [] );

			$chatId = $chat[ 'id' ] ?? '';

			if ( empty( $chatId ) )
			{
				if ( isset( $uniqChats[ $chatId ] ) )
				{
					continue;
				}
			}

			$uniqChats[ $chatId ] = true;

            $name = $chat[ 'first_name' ] ?? $chat[ 'title' ] ?? '[unnamed]';

            $list[] = [
				'id'   => $chatId,
				'name' => $name,
                'username' => $myInfo[ 'result' ][ 'username' ] ?? '',
			];
		}

		return $list;
	}

	public function sendPost ( PostingData $postingData ) : int
    {
		if( empty( $postingData->uploadMedia ) )
		{
			$post = $this->sendTextPost( $postingData );
		}
		else if( count( $postingData->uploadMedia ) === 1 && $postingData->uploadMedia[0]['type'] === 'image' )
		{
			$post = $this->sendSinglePhotoPost( $postingData->uploadMedia[0]['path'], $postingData );
		}
		else if( count( $postingData->uploadMedia ) === 1 && $postingData->uploadMedia[0]['type'] === 'video' )
		{
		    $post = $this->sendSignleVideoPost( $postingData->uploadMedia[0]['path'], $postingData );
	    }
		else if( count( $postingData->uploadMedia ) > 0 )
		{
			$post = $this->sendGroupMediaPost( $postingData );
		}

        return $post[ 'result' ][ 'message_id' ] ?? 0;
	}

	private function sendTextPost( PostingData $postingData )
	{
		$data = [
			'chat_id'              => $this->authData->chatId,
			'text'                 => $postingData->message,
			'parse_mode'           => 'HTML',
			'disable_notification' => $postingData->silent,
		];

		$data = $this->buildReplyMarkup( $data, $postingData );

		return $this->sendRequest( 'sendMessage', $data );
	}

	private function sendSinglePhotoPost ( string $photoPath, PostingData $postingData )
	{
        $data = [
            'multipart' => [
                [
                    'name'     => 'photo',
                    'contents' => file_get_contents( $photoPath ),
                    'filename' => 'image',
                    'headers'  => [ 'Content-Type' => 'image/jpeg' ],
                ],
                [
                    'name'     => 'caption',
                    'contents' => $postingData->message,
                ],
                [
                    'name'     => 'disable_notification',
                    'contents' => $postingData->silent,
                ],
                [
                    'name'     => 'parse_mode',
                    'contents' => 'HTML',
                ],
                [
                    'name'     => 'chat_id',
                    'contents' => $this->authData->chatId,
                ],
            ],
        ];

        $data = $this->buildReplyMarkup( $data, $postingData );

        $response = (string) $this->getClient()->post( 'https://api.telegram.org/bot' . $this->authData->token . '/sendPhoto', $data )->getBody();

        $response = json_decode( $response, true );

		$this->handleResponseException( $response );

		return $response;
	}

	private function sendSignleVideoPost ( string $videoPath, PostingData $postingData )
	{
		$data = [
			'multipart' => [
				[
					'name'     => 'video',
					'contents' => file_get_contents( $videoPath ),
					'filename' => 'video',
				],
				[
					'name'     => 'caption',
					'contents' => $postingData->message,
				],
				[
					'name'     => 'disable_notification',
					'contents' => $postingData->silent,
				],
				[
					'name'     => 'parse_mode',
					'contents' => 'HTML',
				],
				[
					'name'     => 'chat_id',
					'contents' => $this->authData->chatId,
				],
			],
		];

		$data = $this->buildReplyMarkup( $data, $postingData );

		$response = (string) $this->getClient()->post( 'https://api.telegram.org/bot' . $this->authData->token . '/sendVideo', $data )->getBody();

		$response = json_decode( $response, true );

		$this->handleResponseException( $response );

		return $response;
	}

	private function sendGroupMediaPost ( PostingData $postingData )
	{
		$data = [
			'multipart' => [
				[
					'name'     => 'disable_notification',
					'contents' => $postingData->silent,
				],
				[
					'name'     => 'chat_id',
					'contents' => $this->authData->chatId,
				]
			],
		];

		$mediaJson = [];

		foreach ( $postingData->uploadMedia as $i => $media )
		{
			$mediaId = "media{$i}";

			$data['multipart'][] = [
				'name'      => $mediaId,
				'contents'  => file_get_contents( $media['path'] ),
				'filename'  => $mediaId
			];


			$mediaArr = [
				'media'         => 'attach://' . $mediaId,
				'type'          => $media['type'] == 'video' ? 'video' : 'photo'
			];

			if( $i === 0 )
			{
				$mediaArr['caption'] = $postingData->message;
				$mediaArr['parse_mode'] = 'HTML';
			}

			$mediaJson[] = $mediaArr;

			if( $i === 9 )
				break;
		}

		$data['multipart'][] = [
			'name'     => 'media',
			'contents' => json_encode( $mediaJson )
		];

		$data = $this->buildReplyMarkup( $data, $postingData );

		$response = (string) $this->getClient()->post( 'https://api.telegram.org/bot' . $this->authData->token . '/sendMediaGroup', $data )->getBody();

		$response = json_decode( $response, true );

		$this->handleResponseException( $response );

		return $response;
	}

	private function buildReplyMarkup ( $data, PostingData $postingData )
	{
		if ( $postingData->addReadMoreBtn && ! empty( $postingData->readMoreBtnUrl ) && ! empty( $postingData->readMoreBtnText ) )
		{
			$replyMarkup = json_encode( [ 'inline_keyboard' => [ [ [
				'url'  => $postingData->readMoreBtnUrl,
				'text' => $postingData->readMoreBtnText,
			] ] ] ] );

			if ( isset( $data[ 'multipart' ] ) )
			{
				$data[ 'multipart' ][] = [
					'name'     => 'reply_markup',
					'contents' => $replyMarkup,
				];
			}
			else
			{
				$data[ 'reply_markup' ] = $replyMarkup;
			}
		}

		return $data;
	}

	private function sendRequest ( $method, $params = [] )
	{
		$url = 'https://api.telegram.org/bot' . $this->authData->token . '/' . $method;

		if ( ! empty( $params ) )
			$url .= '?' . http_build_query( $params );

		$response = (string) $this->getClient()->request( 'GET', $url )->getBody();
		$response = json_decode( $response, true );

		$this->handleResponseException( $response );

		return $response;
	}

    private function handleResponseException( $response )
    {
        if ( ( $response[ 'ok' ] ?? false ) === false || ! isset( $response[ 'result' ] ) )
        {
            if( ( $response['error_code'] ?? -1 ) == 401 )
            {
                throw new $this->authException( $response['description'] ?? '' );
            }
            else
            {
                throw new $this->postException( $response['description'] ?? '' );
            }
        }
    }

}
