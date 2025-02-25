<?php

namespace FSPoster\App\SocialNetworks\Twitter\Api\AppMethod;

use FSPoster\App\SocialNetworks\Twitter\Api\PostingData;

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
	    $parameters = [];

		if ( ! empty( $postingData->message ) )
            $parameters['text'] = $postingData->message;

	    if ( ! empty( $postingData->link ) )
		    $parameters['text'] .= "\n" . $postingData->link;

	    $connection = new TwitterOAuth( $this->authData->appKey, $this->authData->appSecret, $this->authData->accessToken, $this->authData->accessTokenSecret, $this->proxy );

		if( ! empty( $postingData->uploadMedia ) )
		{
			foreach ( $postingData->uploadMedia as $c => $mediaInf )
			{
				if ( $c > 3 )
					break;

				if( $mediaInf['type'] == 'video' )
				{
					$uploadVideo = $connection->upload( $mediaInf['path'] );

					if ( ! empty( $uploadVideo[ 'media_id_string' ] ) )
						$parameters['media']['media_ids'][] = $uploadVideo['media_id_string'];
				}
				else
				{
					$uploadImage = $connection->upload( $mediaInf['path'] );

					if ( ! empty( $uploadImage['media_id_string'] ) )
						$parameters['media']['media_ids'][] = $uploadImage['media_id_string'];
				}
			}
		}

		$result = $connection->post( 'https://api.twitter.com/2/tweets', [] ,$parameters );

		if ( empty( $result['data']['id'] ) )
			throw new $this->postException( self::handleErrorMessageFromResultData( $result ) );

		$mediaId = $result['data']['id'];

		if ( ! empty( $postingData->firstComment ) )//post a comment
		{
            $connection->post( 'https://api.twitter.com/2/tweets', [], [
				'text'  => $postingData->firstComment,
				'reply' => [
                    'in_reply_to_tweet_id' => $mediaId,
                ],
			] );
		}

		return (string)$mediaId;
	}


    public function fetchAccessToken ( $oauthToken, $oauthTokenSecret, $oauthVerifier ) : Api
    {
		$connection = new TwitterOAuth( $this->authData->appKey, $this->authData->appSecret, $oauthToken, $oauthTokenSecret, $this->proxy );

		$access_token = $connection->oauth( "access_token", [
			'oauth_verifier' => $oauthVerifier,
		] );

		if ( empty( $access_token['oauth_token'] ) || empty( $access_token['oauth_token_secret'] ) )
            throw new $this->authException();

		$this->authData->accessToken = $access_token[ 'oauth_token' ];
		$this->authData->accessTokenSecret = $access_token[ 'oauth_token_secret' ];

        return $this;
    }

	public function getMyInfo ()
	{
		$client = new TwitterOAuth( $this->authData->appKey, $this->authData->appSecret, $this->authData->accessToken, $this->authData->accessTokenSecret, $this->proxy );

		$user = $client->get( "https://api.twitter.com/1.1/account/verify_credentials.json" );

		if( ! isset( $user['id'] ) )
			throw new $this->authException( self::handleErrorMessageFromResultData( $user ) );

		return $user;
	}

	public function getStats ( string $post_id ) : array
    {
		$connection = new TwitterOAuth( $this->authData->appKey, $this->authData->appSecret, $this->authData->accessToken, $this->authData->accessTokenSecret, $this->proxy );

		$stat = $connection->get( 'https://api.twitter.com/1.1/statuses/show/' . $post_id . '.json' );

		return [
            [
                'label' => fsp__( 'Likes' ),
                'value' => isset( $stat[ 'favorite_count' ] ) ? (int) $stat[ 'favorite_count' ] : 0,
            ],
            [
                'label' => fsp__( 'Shares' ),
                'value' => isset( $stat[ 'retweet_count' ] ) ? (int) $stat[ 'retweet_count' ] : 0,
            ],
		];
	}

	public static function getAuthData ( $appKey, $appSecret, $proxy, $callbackUrl ) : array
	{
		$connection = new TwitterOAuth( $appKey, $appSecret, null, null, $proxy );

		$tokens = $connection->oauth( 'request_token', [
			'oauth_callback' => $callbackUrl,
		] );

		if ( empty( $tokens['oauth_token'] ) || empty( $tokens['oauth_token_secret'] ) )
			throw new \Exception( self::handleErrorMessageFromResultData( $tokens ) );

		return [
			'oauth_token'           => $tokens[ 'oauth_token' ],
			'oauth_token_secret'    => $tokens[ 'oauth_token_secret' ],
			'oauth_url'             => 'https://api.twitter.com/oauth/authorize?oauth_token=' . $tokens[ 'oauth_token' ],
		];
	}

	private static function handleErrorMessageFromResultData ( $data )
	{
		if ( isset( $data['errors'][0]['code'] ) && $data['errors'][0]['code'] == 453 )
			return fsp__( 'You need to apply for Elevated access via the Developer Portal to share posts. <a href="https://www.fs-poster.com/documentation/fs-poster-schedule-share-wordpress-posts-to-twitter-automatically">How to?</a>');

		if ( isset( $data['errors'][0]['message'] ) )
			return $data['errors'][0]['message'];

		if ( isset( $data['detail'] ) )
			return $data['detail'];

		return fsp__( 'Unknown error' );
	}

}