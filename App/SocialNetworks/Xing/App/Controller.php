<?php

namespace FSPoster\App\SocialNetworks\Xing\App;

use Exception;
use FSPoster\App\Providers\Channels\ChannelSessionException;
use FSPoster\App\Providers\Core\RestRequest;
use FSPoster\App\Providers\Core\Settings;
use FSPoster\App\SocialNetworks\Xing\Adapters\ChannelAdapter;
use FSPoster\App\SocialNetworks\Xing\Api\Api;
use FSPoster\App\SocialNetworks\Xing\Api\AuthData;

class Controller
{
    /**
     * @throws Exception
     */
    public static function addChannel ( RestRequest $request ): array
    {
        $login         = $request->require( 'login', RestRequest::TYPE_STRING, fsp__( 'Please enter the cookie "%s"', [ 'login' ] ) );
        $csrf_token    = $request->require( 'csrf_token', RestRequest::TYPE_STRING, fsp__( 'Please enter the cookie "%s"', [ 'csrf_token' ] ) );
        $csrf_checksum = $request->require( 'csrf_checksum', RestRequest::TYPE_STRING, fsp__( 'Please enter the cookie "%s"', [ 'csrf_checksum' ] ) );
        $proxy         = $request->param( 'proxy', '', RestRequest::TYPE_STRING );

        $authData = new AuthData();
        $authData->cookies = [
            'login'              => $login,
            'xing_csrf_token'    => $csrf_token,
            'xing_csrf_checksum' => $csrf_checksum,
        ];

        $api = new Api();
        $api->setProxy($proxy)
	        ->setAuthException( ChannelSessionException::class )
            ->setAuthData($authData)
            ->setClient();

        $channelsList = ChannelAdapter::fetchChannels($api);

        return [ 'channels' => $channelsList ];
    }

    public static function saveSettings ( RestRequest $request ): array
    {
        $postText       = $request->param( 'post_text', '', RestRequest::TYPE_STRING );
        $postVisibility = $request->param( 'post_visibility', '', RestRequest::TYPE_STRING, [ 'public', 'only_contacts', 'same_city' ] );
        $attachLink         = $request->param( 'attach_link', true, RestRequest::TYPE_BOOL );
        $uploadMedia        = $request->param( 'upload_media', false, RestRequest::TYPE_BOOL );
        $mediaTypeToUpload  = $request->param( 'media_type_to_upload', 'featured_image', RestRequest::TYPE_STRING );

        Settings::set( 'xing_post_content', $postText );
        Settings::set( 'xing_post_visibility', $postVisibility );
        Settings::set( 'xing_attach_link', (int)$attachLink );
        Settings::set( 'xing_upload_media', (int)$uploadMedia );
        Settings::set( 'xing_media_type_to_upload', $mediaTypeToUpload );

	    do_action( 'fsp_save_settings', $request, Bootstrap::getInstance()->getSlug() );

        return [];
    }

    public static function getSettings ( RestRequest $request ): array
    {
	    return apply_filters('fsp_get_settings', [
		    'post_text'               => Settings::get( 'xing_post_content', '{post_title}' ),
		    'attach_link'             => (bool)Settings::get( 'xing_attach_link', true ),
		    'upload_media'            => (bool)Settings::get( 'xing_upload_media', false ),
		    'media_type_to_upload'    => Settings::get( 'xing_media_type_to_upload', 'featured_image' ),
		    'post_visibility'         => Settings::get( 'xing_post_visibility', 'public' ),
		    'post_visibility_options' => [
			    [
				    'label' => fsp__( 'Public' ),
				    'value' => 'public',
			    ],
			    [
				    'label' => fsp__( 'Only contacts' ),
				    'value' => 'only_contacts',
			    ],
			    [
				    'label' => fsp__( 'Only contacts in my city' ),
				    'value' => 'same_city',
			    ],
		    ],
	    ], Bootstrap::getInstance()->getSlug());
    }
}