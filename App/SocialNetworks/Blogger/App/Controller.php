<?php

namespace FSPoster\App\SocialNetworks\Blogger\App;

use FSPoster\App\Providers\Core\RestRequest;
use FSPoster\App\Providers\Core\Settings;

class Controller
{

    public static function saveSettings ( RestRequest $request ): array
    {
        $postTitle       = $request->param( 'post_title', '', RestRequest::TYPE_STRING );
        $postText        = $request->param( 'post_text', '', RestRequest::TYPE_STRING );
        $postStatus      = $request->param( 'post_status', 'publish', RestRequest::TYPE_STRING, [ 'publish', 'draft' ] );
        $sendCategories  = (int)$request->param( 'send_categories', false, RestRequest::TYPE_BOOL );
        $sendTags        = (int)$request->param( 'send_tags', false, RestRequest::TYPE_BOOL );
        $sendPagesAsPage = (int)$request->param( 'send_pages_as_page', false, RestRequest::TYPE_BOOL );

        Settings::set( 'blogger_post_title', $postTitle );
        Settings::set( 'blogger_post_content', $postText );
        Settings::set( 'blogger_post_status', $postStatus );
        Settings::set( 'blogger_send_categories', $sendCategories );
        Settings::set( 'blogger_send_tags', $sendTags );
        Settings::set( 'blogger_send_pages_as_page', $sendPagesAsPage );

	    do_action( 'fsp_save_settings', $request, Bootstrap::getInstance()->getSlug() );

        return [];
    }

    public static function getSettings ( RestRequest $request ): array
    {
	    return apply_filters('fsp_get_settings', [
		    'post_title'                      => Settings::get( 'blogger_post_title', '{post_title}' ),
		    'post_text'                       => Settings::get( 'blogger_post_content', "<img src='{post_featured_image_url}'>\n\n{post_content} \n\n<a href='{post_url}'>{post_url}</a>" ),
		    'post_status'                     => Settings::get( 'blogger_post_status', 'draft' ),
		    'post_status_options'             => [
			    [ 'label' => 'Publish', 'value' => 'publish' ],
			    [ 'label' => 'Draft', 'value' => 'draft' ],
		    ],
		    'send_categories'                 => (bool)Settings::get( 'blogger_send_categories', false ),
		    'send_tags'                       => (bool)Settings::get( 'blogger_send_tags', false ),
		    'send_pages_as_page'              => (bool)Settings::get( 'blogger_send_pages_as_page', true ),
	    ], Bootstrap::getInstance()->getSlug());
    }

}