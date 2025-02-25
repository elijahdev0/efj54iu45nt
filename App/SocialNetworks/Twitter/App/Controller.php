<?php

namespace FSPoster\App\SocialNetworks\Twitter\App;

use Exception;
use FSPoster\App\Providers\Core\RestRequest;
use FSPoster\App\Providers\Core\Settings;
use FSPoster\App\Providers\Schedules\SocialNetworkApiException;
use FSPoster\App\SocialNetworks\Twitter\Adapters\ChannelAdapter;
use FSPoster\App\SocialNetworks\Twitter\Api\CookieMethod\AuthData;
use FSPoster\App\SocialNetworks\Twitter\Api\CookieMethod\Api;

class Controller
{

	/**
     * @throws SocialNetworkApiException
     * @throws Exception
     */
    public static function addChannelViaCookie ( RestRequest $request ): array
    {
        $authToken  = $request->require( 'auth_token', 'string', fsp__( 'Please enter the cookie "%s"', [ 'auth_token' ] ) );
        $proxy      = $request->param( 'proxy', '', 'string' );

	    $authData = new AuthData();
	    $authData->authToken = $authToken;

	    $api = new Api();
	    $api->setProxy( $proxy )
	        ->setAuthData( $authData );

	    $channels = ChannelAdapter::fetchChannels( $api, 'cookie' );

	    return [ 'channels' => $channels ];
    }

    /**
     * @throws Exception
     */
    public static function saveSettings ( RestRequest $request ): array
    {
        $cutPostTitle        = (int)$request->param( 'cut_post_text', false, RestRequest::TYPE_BOOL );
        $postText            = $request->param( 'post_text', '', RestRequest::TYPE_STRING );
        $shareToFirstComment = (int)$request->param( 'share_to_first_comment', false, RestRequest::TYPE_BOOL );
        $firstCommentText    = $request->param( 'first_comment_text', '', RestRequest::TYPE_STRING );
	    $attachLink         = $request->param( 'attach_link', true, RestRequest::TYPE_BOOL );
	    $uploadMedia        = $request->param( 'upload_media', false, RestRequest::TYPE_BOOL );
	    $mediaTypeToUpload  = $request->param( 'media_type_to_upload', 'featured_image', RestRequest::TYPE_STRING );

        if ( $shareToFirstComment && empty( $firstCommentText ) )
            throw new Exception( fsp__( 'First comment can\'t be empty if enabled' ) );

		if( ! $shareToFirstComment )
			$firstCommentText = '';

        Settings::set( 'twitter_cut_post_text', $cutPostTitle );
        Settings::set( 'twitter_post_content', $postText );
        Settings::set( 'twitter_share_to_first_comment', $shareToFirstComment );
        Settings::set( 'twitter_first_comment_text', $firstCommentText );
	    Settings::set( 'twitter_attach_link', (int)$attachLink );
	    Settings::set( 'twitter_upload_media', (int)$uploadMedia );
	    Settings::set( 'twitter_media_type_to_upload', $mediaTypeToUpload );

	    do_action( 'fsp_save_settings', $request, Bootstrap::getInstance()->getSlug() );

        return [];
    }

    public static function getSettings ( RestRequest $request ): array
    {
	    return apply_filters('fsp_get_settings', [
		    'cut_post_text'             => (bool)Settings::get( 'twitter_cut_post_text', true ),
		    'post_text'                 => Settings::get( 'twitter_post_content', '{post_title}' ),
		    'share_to_first_comment'    => (bool)Settings::get( 'twitter_share_to_first_comment', false ),
		    'first_comment_text'        => Settings::get( 'twitter_first_comment_text', '' ),
		    'attach_link'               => (bool)Settings::get( 'twitter_attach_link', true ),
		    'upload_media'              => (bool)Settings::get( 'twitter_upload_media', false ),
		    'media_type_to_upload'      => Settings::get( 'twitter_media_type_to_upload', 'featured_image' )
	    ], Bootstrap::getInstance()->getSlug());
    }
}