<?php

namespace FSPoster\App\SocialNetworks\Webhook\App;

use Exception;
use FSPoster\App\Models\Channel;
use FSPoster\App\Providers\Channels\ChannelService;
use FSPoster\App\Providers\Core\RestRequest;
use FSPoster\App\SocialNetworks\Webhook\Api\Api;
use FSPoster\GuzzleHttp\Client;

class Controller
{
    /**
     * @throws Exception
     */
    public static function addChannel ( RestRequest $request ): array
    {
        $id          = $request->param( 'channel_id', 0, RestRequest::TYPE_INTEGER );
        $icon        = $request->param( 'icon', '', RestRequest::TYPE_STRING );
        $name        = $request->param( 'name', '', RestRequest::TYPE_STRING );
        $method      = $request->param( 'method', 'post', RestRequest::TYPE_STRING, [
            'post',
            'get',
            'put',
            'delete',
        ] );
        $url         = $request->param( 'url', '', RestRequest::TYPE_STRING );
        $headers     = $request->param( 'headers', [], RestRequest::TYPE_ARRAY );
        $contentType = $request->param( 'content', 'none', RestRequest::TYPE_STRING, [
            'none',
            'form',
            'json',
        ] );
        $json        = $request->param( 'json', '', RestRequest::TYPE_STRING );
        $formData    = $request->param( 'form', [], RestRequest::TYPE_ARRAY );
        $proxy       = $request->param( 'proxy', null, RestRequest::TYPE_STRING );

        $urlParsed = parse_url( $url, PHP_URL_HOST );

        $error_msg = '';

        if ( empty( $name ) )
        {
            $error_msg = fsp__( 'Name cannot be left empty' );
        } else if ( filter_var( $url, FILTER_VALIDATE_URL ) === false || empty( $urlParsed ) )
        {
            $error_msg = fsp__( 'The request URL must be a valid url' );
        } else if ( $contentType === 'json' && empty( json_decode( $json, true ) ) )
        {
            $error_msg = fsp__( 'The JSON data must be valid JSON' );
        }

        if ( !empty( $error_msg ) )
        {
            throw new Exception( $error_msg );
        }

        $webhook = [
            'method'       => $method,
            'url'          => $url,
            'content_type' => $contentType,
            'headers'      => []
        ];

		foreach ( $headers AS $headerKey => $headerValue )
		{
			if( ! empty( $headerKey ) && is_string( $headerKey ) && is_string( $headerValue ) )
				$webhook['headers'][ $headerKey ] = $headerValue;
		}

        if ( !empty( $icon ) )
        {
            $webhook['icon'] = $icon;
        }

        if ( !empty( $proxy ) )
        {
            $webhook['proxy'] = $proxy;
        }

        if ( $method === 'post' || $method === 'put' )
        {
            if ( $contentType === 'json' )
            {
                $webhook[ 'json_data' ] = $json;
            } else if ( $contentType === 'form' )
            {
                $webhook[ 'form_data' ] = $formData;
            }
        }

        $channel = [
            'social_network' => Bootstrap::getInstance()->getSlug(),
            'name'           => $name,
            'channel_type'   => 'request',
            'remote_id'      => md5( json_encode( $webhook ) ),
            'picture'        => null,
            'data'           => $webhook
        ];

        if ( $id > 0 )
        {
            $ifExists = Channel::where( 'id', $id )->where( 'social_network', Bootstrap::getInstance()->getSlug() )->fetch();

            if ( empty( $ifExists ) )
            {
                throw new Exception( fsp__( 'Channel doesn\'t exist' ) );
            }

            $channelData = json_decode( $ifExists[ 'data' ], true );

            $channel['data'] = array_merge( $channelData, $webhook );
            $channel['id'] = $id;
        } else
        {
            $channel[ 'channel_session_id' ] = ChannelService::addChannelSession( [
	            'name'           => $name,
                'social_network' => Bootstrap::getInstance()->getSlug(),
                'remote_id'      => md5( json_encode( $webhook ) ),
                'method'         => 'default',
            ] );
        }

        return [
            'channels' => [
                $channel
            ]
        ];
    }

    public static function sendTestRequest ( RestRequest $request ): array
    {
        $method      = $request->param( 'method', 'post', RestRequest::TYPE_STRING, [
            'post',
            'get',
            'put',
            'delete',
        ] );
        $url         = $request->param( 'url', '', RestRequest::TYPE_STRING );
        $headers     = $request->param( 'headers', [], RestRequest::TYPE_ARRAY );
        $contentType = $request->param( 'content', 'none', RestRequest::TYPE_STRING, [
            'none',
            'form',
            'json',
        ] );
        $json        = $request->param( 'json', '', RestRequest::TYPE_STRING );
        $formData    = $request->param( 'form', [], RestRequest::TYPE_ARRAY );
        $proxy       = $request->param( 'proxy', null, RestRequest::TYPE_STRING );

        $options = [
            'headers' => $headers,
        ];

        if ( !empty( $proxy ) )
        {
            $options[ 'proxy' ] = $proxy;
        }

        if ( $method === 'post' || $method === 'put' )
        {
            if ( $contentType === 'json' )
            {
                $options[ 'body' ] = $json;
            } else if ( $contentType === 'form' )
            {
                $options[ 'form_params' ] = $formData;
            }
        }

        try
        {
            $client = new Client();
            $client->request( strtoupper( $method ), $url, $options );
        } catch ( Exception $e )
        {
        }

        return [];
    }
}