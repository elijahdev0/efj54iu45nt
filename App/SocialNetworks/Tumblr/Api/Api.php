<?php

namespace FSPoster\App\SocialNetworks\Tumblr\Api;

use Exception;
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

	public function getMyInfo ()
	{
		$client = new TumblrClient( $this->authData->accessToken, $this->proxy );

		return $client->getUserInfo();
	}

    public function sendPost ( PostingData $postingData ) : string
    {
		$postData = [];

	    $messages = self::messageBlocks( $postingData->message );

        $postData['layout'] = [
            [
                'type'    => 'rows',
                'display' => [],
            ],
        ];

		if( ! empty( $postingData->link ) )
			$postData[ 'source_url' ] = $postingData->link;

	    if ( ! empty( $postingData->title ) )
	    {
		    $postData[ 'content' ][] = [
			    'type'    => 'text',
			    'subtype' => 'heading1',
			    'text'    => $postingData->title,
		    ];
	    }

	    foreach ( $postingData->uploadMedia ?? [] as $media )
	    {
		    $postData[ 'content' ][] = [
			    'type'  => 'image',
			    'media' => [
				    'url'  => $media['url']
			    ]
		    ];
	    }

	    foreach ( $messages as $blockText )
	    {
		    $postData[ 'content' ][] = [
			    'type' => 'text',
			    'text' => $blockText,
		    ];
	    }

        for( $i = 0; $i < count( $postData[ 'content' ] ); $i++ )
        {
            $postData[ 'layout' ][0][ 'display' ][] = [
                'blocks' => [ $i ],
            ];
        }

		if ( ! empty( $postingData->tags ) )
            $postData[ 'tags' ] = implode(',', $postingData->tags);

		try
		{
            $client = new TumblrClient( $this->authData->accessToken, $this->proxy );
			$result = $client->post('blog/' . $postingData->blogId . '/posts', [
                'json' => $postData,
            ]);
		}
		catch ( Exception $e )
		{
            throw new $this->postException( $e->getMessage() );
		}

		return strval( $result['id'] );
    }

    private static function messageBlocks( $message ) : array
    {
        $messages      = [];
        $messageLength = mb_strlen( $message );
        $lastCut       = 0;
        $blockText     = $message;

        do{
            $cutLength = 4096;

            if ( ! $messageLength < 4096 )
            {
                $searchText  = html_entity_decode( mb_substr( $message, 0, $lastCut + 4095 ) );
                $needles     = [ "\n", "<br>", "<br/>", ".", " ", "&nbsp;", "&#160;" ];

                foreach ( $needles as $needle )
                {
                    if( empty( $searchText ) || ( $lastCut + 4000 > $messageLength ) )
                    {
                        break;
                    }
                    else
                    {
                        $pos = mb_strpos( $searchText, $needle, $lastCut + 4000 );
                    }

                    if ( $pos !== false )
                    {
                        $cutLength = $pos - $lastCut;

                        if( $needle == '.' )
                        {
                            $cutLength += 1;
                        }

                        break;
                    }
                }

                $blockText = html_entity_decode( mb_substr( $message, $lastCut, $cutLength ) );
            }

            $lastCut   = $lastCut + $cutLength;

            if ( ! empty( $blockText ) )
            {
                $messages[] = $blockText;
            }
        } while( $messageLength > 4096 && $lastCut < $messageLength );

        return $messages;
    }


	public function fetchAccessToken ( $code, $callbackUrl ) : Api
    {
        $response = Curl::getContents( 'https://api.tumblr.com/v2/oauth2/token', 'post', [
            'client_secret' => $this->authData->appConsumerSecret,
            'client_id'     => $this->authData->appConsumerKey,
            'redirect_uri'  => $callbackUrl,
            'code'          => $code,
            'grant_type'    => 'authorization_code',
        ], [], $this->proxy ?: null, true);

        $params = json_decode( $response, true );

        if ( isset( $params[ 'errors' ] ) )
            throw new $this->authException( reset($params['errors'])['title'] );

        if( isset( $params[ 'error_description' ] ) )
            throw new $this->authException($params['error_description']);

		$this->authData->accessToken = $params[ 'access_token' ];
		$this->authData->accessTokenExpiresOn = Date::dateTimeSQL( 'now', '+' . (int) $params[ 'expires_in' ] . ' seconds' );
		$this->authData->refreshToken = $params[ 'refresh_token' ];

        return $this;
    }

	private function refreshAccessTokenIfNeed() : void
	{
		if ( !empty( $this->authData->accessTokenExpiresOn ) && ( Date::epoch() + 30 ) > Date::epoch( $this->authData->accessTokenExpiresOn ) )
		{
			$this->refreshAccessToken();
		}
	}

    public function refreshAccessToken () : void
    {
        $sendData = [
            'grant_type'    => 'refresh_token',
            'refresh_token' => $this->authData->refreshToken,
            'client_id'     => $this->authData->appConsumerKey,
            'client_secret' => $this->authData->appConsumerSecret,
        ];

        $token_url = 'https://api.tumblr.com/v2/oauth2/token';
        $response  = Curl::getContents( $token_url, 'POST', $sendData, [], $this->proxy, true );

        $token_data = json_decode( $response, true );

        if ( ! is_array( $token_data ) || ! isset( $token_data[ 'access_token' ] ) )
            throw new $this->authException(fsp__( 'Tumblr refresh token is expired. Please add your channel to the plugin again without deleting it from the plugin; as a result, channel settings will remain as it is.' ));

		$this->authData->accessToken = $token_data[ 'access_token' ];
		$this->authData->accessTokenExpiresOn = Date::dateTimeSQL( 'now', '+' . (int) $token_data[ 'expires_in' ] . ' seconds' );
    }

	public static function getAuthURL ( $appKey, $callbackUrl ) : string
	{
		$permissions = urlencode('basic write offline_access');

		return sprintf( 'https://www.tumblr.com/oauth2/authorize?redirect_uri=%s&scope=%s&response_type=code&client_id=%s&state=%s', $callbackUrl, $permissions, $appKey, uniqid() );
	}

}