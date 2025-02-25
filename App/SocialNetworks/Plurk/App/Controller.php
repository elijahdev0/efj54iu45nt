<?php

namespace FSPoster\App\SocialNetworks\Plurk\App;

use FSPoster\App\Providers\Core\RestRequest;
use FSPoster\App\Providers\Core\Settings;

class Controller
{
    public static function saveSettings ( RestRequest $request ): array
    {
        $postText    = $request->param( 'post_content', '', RestRequest::TYPE_STRING );
        $cutText     = (int)$request->param( 'cut_post_content', false, RestRequest::TYPE_BOOL );
        $qualifier   = $request->param( 'qualifier', ':', RestRequest::TYPE_STRING, [ ':', 'shares', 'plays', 'buys', 'sells', 'loves', 'likes', 'hates', 'wants', 'wishes', 'needs', 'has', 'will', 'hopes', 'asks', 'wonders', 'feels', 'thinks', 'draws', 'is', 'says', 'eats', 'writes', 'whispers' ] );

        Settings::set( 'plurk_cut_post_content', $cutText );
        Settings::set( 'plurk_post_content', $postText );
        Settings::set( 'plurk_qualifier', $qualifier );

	    do_action( 'fsp_save_settings', $request, Bootstrap::getInstance()->getSlug() );

        return [];
    }

    public static function getSettings ( RestRequest $request ): array
    {
	    return apply_filters('fsp_get_settings', [
		    'post_content'         => Settings::get( 'plurk_post_content', "{post_title}\n{post_featured_image_url}\n{post_content limit=\"200\"}" ),
		    'cut_post_content'     => (bool)Settings::get( 'plurk_cut_post_content', true ),
		    'qualifier'            => Settings::get( 'plurk_qualifier', ':' ),
		    'qualifier_options'    => [
			    [ 'label' => fsp__( 'None' ), 'value' => ':' ],
			    [ 'label' => fsp__( 'Shares' ), 'value' => 'shares' ],
			    [ 'label' => fsp__( 'Plays' ), 'value' => 'plays' ],
			    [ 'label' => fsp__( 'Buys' ), 'value' => 'buys' ],
			    [ 'label' => fsp__( 'Sells' ), 'value' => 'sells' ],
			    [ 'label' => fsp__( 'Loves' ), 'value' => 'loves' ],
			    [ 'label' => fsp__( 'Likes' ), 'value' => 'likes' ],
			    [ 'label' => fsp__( 'Hates' ), 'value' => 'hates' ],
			    [ 'label' => fsp__( 'Wants' ), 'value' => 'wants' ],
			    [ 'label' => fsp__( 'Wishes' ), 'value' => 'wishes' ],
			    [ 'label' => fsp__( 'Needs' ), 'value' => 'needs' ],
			    [ 'label' => fsp__( 'Has' ), 'value' => 'has' ],
			    [ 'label' => fsp__( 'Will' ), 'value' => 'will' ],
			    [ 'label' => fsp__( 'Hopes' ), 'value' => 'hopes' ],
			    [ 'label' => fsp__( 'Asks' ), 'value' => 'asks' ],
			    [ 'label' => fsp__( 'Wonders' ), 'value' => 'wonders' ],
			    [ 'label' => fsp__( 'Feels' ), 'value' => 'feels' ],
			    [ 'label' => fsp__( 'Thinks' ), 'value' => 'thinks' ],
			    [ 'label' => fsp__( 'Draws' ), 'value' => 'draws' ],
			    [ 'label' => fsp__( 'Is' ), 'value' => 'is' ],
			    [ 'label' => fsp__( 'Says' ), 'value' => 'says' ],
			    [ 'label' => fsp__( 'Eats' ), 'value' => 'eats' ],
			    [ 'label' => fsp__( 'Writes' ), 'value' => 'writes' ],
			    [ 'label' => fsp__( 'Whispers' ), 'value' => 'whispers' ],
		    ]
	    ], Bootstrap::getInstance()->getSlug());
    }
}