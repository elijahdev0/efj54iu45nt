<?php

namespace FSPoster\App\SocialNetworks\WordPress\Api;

use Exception;
use FSPoster\App\Providers\Helpers\Helper;
use FSPoster\GuzzleHttp\Client;
use IXR_Base64;
use IXR_Message;
use IXR_Request;

class Api
{

	public AuthData $authData;
	public ?string  $proxy = null;

	public string $authException = \Exception::class;
	public string $postException = \Exception::class;

	public function __construct()
	{
		include_once( ABSPATH . WPINC . '/class-IXR.php' );
		//include_once( ABSPATH . WPINC . '/class-wp-http-ixr-client.php' );
	}

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

	private ?Client $client = null;

    public function getClient() : Client
    {
		if( is_null( $this->client ) )
		{
			$this->client = new Client( [
				'proxy'       => empty( $this->proxy ) ? null : $this->proxy,
				'verify'      => false,
				'http_errors' => false,
				'headers'     => [
					'Content-Type' => 'text/xml',
					'User-Agent'   => 'wp-android',
				],
			] );
		}

        return $this->client;
    }

	public function sendPost ( PostingData $postingData ) : string
    {
		$postTypes = $this->apiRequest( 'wp.getPostTypes' );
		$postType = $postingData->postType;

		if ( ! array_key_exists( $postType, $postTypes ) )
		{
			if ( $postingData->preservePostType )
				$postType = 'post';
			else
				throw new $this->postException( fsp__( 'Failed to share the post because the post type is not available on the remote website.' ) );
		}

		$params = [
			'post_title'   => $postingData->title,
			'post_excerpt' => $postingData->excerpt,
			'post_content' => $postingData->message,
			'post_type'    => $postType,
			'post_status'  => $postingData->postStatus,
			'terms'        => [],
		];

		foreach( ( $postingData->categories ?? [] ) AS $category )
		{
			$termId = $this->syncTerm( $category['slug'], $category['name'], 'category' );
			if( $termId > 0 )
				$params[ 'terms' ][ 'category' ][] = $termId;
		}

		foreach( ( $postingData->tags ?? [] ) AS $tag )
		{
			$termId = $this->syncTerm( $tag['slug'], $tag['name'], 'post_tag' );
			if( $termId > 0 )
				$params[ 'terms' ][ 'post_tag' ][] = $termId;
		}

		if ( empty( $params[ 'terms' ] ) )
			unset( $params[ 'terms' ] );

		if( ! empty( $postingData->uploadMedia ) )
		{
			$uploadMedia = reset( $postingData->uploadMedia );
			$mediaId = $this->uploadPhoto( $uploadMedia['path'] );

			if ( ! empty( $mediaId ) )
				$params[ 'post_thumbnail' ] = $mediaId;
		}

		$createPost = $this->apiRequest( 'wp.newPost', $params );

		if ( ! is_numeric( $createPost ) )
			throw new $this->postException( fsp__( 'An error occurred while processing the request' ) );

		if( isset( $mediaId ) )
            $this->apiRequest('wp.editPost', $mediaId, [ 'post_parent' => $createPost ] );

        return (string)$createPost;
	}

	public function syncTerm ( $termSlug, $termName, $taxonomy )
	{
		$termId = $this->findTerm( $termSlug, $taxonomy );

		if ( ! empty( $termId ) )
			return $termId;

		$termId = $this->apiRequest( 'wp.newTerm', [
			'name'     => $termName,
			'slug'     => $termSlug,
			'taxonomy' => $taxonomy,
		] );

		if( ! isset( $termId[ 'faultCode' ] ) && ! empty( $termId ) )
			return $termId;

		return 0;
	}

	public function findTerm ( $termSlug, $taxonomy )
	{
		$result = $this->apiRequest( 'wp.getTerms', $taxonomy, [ 'search' => $termSlug ] );

		foreach ( $result as $termInf )
		{
			if ( isset( $termInf[ 'slug' ] ) && $termInf[ 'slug' ] == $termSlug )
			{
				return $termInf[ 'term_id' ];
			}
		}

		return false;
	}

	public function apiRequest ()
	{
		$args    = func_get_args();
		$command = array_shift( $args );

		$params = array_merge( [ 0, $this->authData->username, $this->authData->password ], $args );

		$request = new IXR_Request( $command, $params );
		$xml     = $request->getXml();

		try
		{
			$result = $this->getClient()->request( 'POST', $this->authData->siteUrl . '/xmlrpc.php', [
				'body' => $xml,
			] );
		}
		catch ( Exception $e )
		{
			throw new $this->postException( $e->getMessage() );
		}

		$message = new IXR_Message( (string) $result->getBody() );

        if ( is_string( $message->message ) )
            $message->message = trim($message->message, '﻿');

		$message->parse();

		if ( ! isset( $message->params[ 0 ] ) )
			throw new $this->postException( fsp__( 'An error occurred while processing the request' ) );

		return $message->params[ 0 ];
	}

	public function uploadPhoto ( $image = null )
	{
		if ( empty( $image ) )
			return false;

		$content = [
			'name' => basename( $image ),
			'type' => Helper::mimeContentType( $image ),
			'bits' => new IXR_Base64( file_get_contents( $image ) ),
			true,
		];

		$result = $this->apiRequest( 'metaWeblog.newMediaObject', $content );

		return $result[ 'id' ] ?? false;
	}

	public function getMyInfo ()
	{
		$info = $this->apiRequest( 'wp.getProfile' );

		if ( isset( $info[ 'faultString' ] ) || ! isset( $info[ 'user_id' ] ) )
			throw new $this->authException( $info[ 'faultString' ] ?? 'Error' );

		return true;
	}

}
