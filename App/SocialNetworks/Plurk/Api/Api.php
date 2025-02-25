<?php

namespace FSPoster\App\SocialNetworks\Plurk\Api;

use Exception;
use FSPoster\GuzzleHttp\Client;
use FSPoster\GuzzleHttp\Exception\GuzzleException;

class Api
{

    const REQUEST_TOKEN_LINK = 'https://www.plurk.com/OAuth/request_token';
    const ACCESS_TOKEN_LINK  = 'https://www.plurk.com/OAuth/access_token';
    const AUTH_APP_LINK      = "https://www.plurk.com/OAuth/authorize?oauth_token=";
    const ADD_PLURK_LINK     = 'https://www.plurk.com/APP/Timeline/plurkAdd';
    const GET_USER_INFO      = 'https://www.plurk.com/APP/Users/me';
    const GET_PLURK_LINK     = 'https://www.plurk.com/APP/Timeline/getPlurk';

    public ?Client  $client = null;
    public AuthData $authData;
    public ?string  $proxy;

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
            return $this->sendRequest( $this->getApiLink( 'GET', Api::GET_USER_INFO ) );
        } catch ( GuzzleException $e )
        {
            throw new $this->authException( $e->getMessage() );
        }
    }

    /**
     * @param $plurkId
     *
     * @return array|array[]
     * @throws GuzzleException
     */
    public function getStats ( $plurkId ): array
    {
        return $this->sendRequest( $this->getApiLink( 'GET', self::GET_PLURK_LINK, '', [ 'plurk_id' => $plurkId ] ) );
    }

    public function sendPost ( PostingData $postingData ): string
    {
        $postContent = [
            'content'   => $postingData->message,
            'qualifier' => $postingData->qualifier
        ];

        $apiLink = $this->getApiLink( 'GET', Api::ADD_PLURK_LINK, '', $postContent );

        $post = $this->sendRequest( $apiLink );

        if ( empty( $post[ 'plurk_id' ] ) )
        {
            if ( !empty( $post[ 'error_text' ] ) && $post[ 'error_text' ] === 'anti-flood-same-content' )
            {
                throw new $this->postException( fsp__( 'It seems that you have recently shared this content. Duplicate content is not allowed in a short time.' ) );
            } else
            {
                throw new $this->postException( !empty( $post[ 'error_text' ] ) ? esc_html( $post[ 'error_text' ] ) : fsp__( 'Error! Couldn\'t share post' ) );
            }
        }

        return (string)($post[ 'plurk_id' ] ?? '0');
    }

    /**
     * @throws Exception
     */
    public function fetchAccessToken ( string $type, string $verifier = '' ): array
    {
        $link = $type === 'access' ? Api::ACCESS_TOKEN_LINK : Api::REQUEST_TOKEN_LINK;

        try
        {
			$url = $this->getApiLink( 'GET', $link, $verifier );
	        $data = (string)$this->getClient()->request( 'GET', $url )->getBody();

            if ( strpos( $data, '=' ) === false || strpos( $data, '&' ) === false )
            {
                throw new $this->authException( fsp__( 'Couldn\'t get token' ) );
            }
        } catch ( GuzzleException $e )
        {
            throw new $this->authException( $e->getMessage() );
        }

        $auth_token = explode( '&', $data );
        $token      = explode( '=', $auth_token[ 0 ] )[ 1 ];
        $secret     = explode( '=', $auth_token[ 1 ] )[ 1 ];

        return [
            'token'  => $token,
            'secret' => $secret,
        ];
    }

    /**
     * @throws GuzzleException
     */
    private function sendRequest ( $url )
    {
        $response = (string)$this->getClient()->request( 'GET', $url )->getBody();

        return json_decode( $response, true );
    }

    private function getApiLink ( $request_method, $apiLink, $verifier = '', $content = [] ): string
    {
        $defaults = [
            'oauth_consumer_key'     => $this->authData->appKey,
            'oauth_nonce'            => mt_rand( 10000000, 99999999 ),
            'oauth_timestamp'        => date( 'U' ),
            'oauth_token'            => $this->authData->accessToken,
            'oauth_verifier'         => $verifier,
            'oauth_signature_method' => 'HMAC-SHA1',
            'oauth_version'          => '1.0',
        ];

        $args = array_merge( $defaults, $content );

        $args_purified = array_filter( $args, function ( $value )
        {
            return $value !== null && $value !== '';
        } );

        ksort( $args_purified );

        $url_params = [];

        foreach ( $args_purified as $key => $value )
        {
            $url_params[] = rawurlencode( $key ) . '=' . rawurlencode( $value );
        }

        $url_params_str = implode( '&', $url_params );

        $base = $request_method . '&' . rawurlencode( $apiLink ) . '&' . rawurlencode( $url_params_str );

        $key       = rawurlencode( $this->authData->appSecret ) . '&' . rawurlencode( $this->authData->accessTokenSecret );
        $signature = rawurlencode( base64_encode( hash_hmac( 'SHA1', $base, $key, true ) ) );

        return $apiLink . '?' . $url_params_str . '&oauth_signature=' . $signature;
    }

}
