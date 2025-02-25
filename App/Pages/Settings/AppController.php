<?php

namespace FSPoster\App\Pages\Settings;

use Exception;
use FSPoster\App\Models\App;
use FSPoster\App\Providers\Core\RestRequest;
use FSPoster\App\Providers\Helpers\Helper;
use FSPoster\App\Providers\Helpers\PluginHelper;
use FSPoster\App\Providers\SocialNetwork\SocialNetworkAddon;

class AppController
{

    public static function list ( RestRequest $request ): array
    {
	    $apps = App::where( 'slug', 'IS', null )->orderBy( 'social_network' )->fetchAll();
        $list = [];

        foreach ( $apps as $app )
        {
            $list[] = apply_filters( 'fsp_get_app', [
                'id'             => (int)$app->id,
                'name'           => $app->name,
                'social_network' => $app->social_network,
                'created_by'     => $app->created_by,
            ], $app );
        }

        return [ 'apps' => $list ];
    }

    /**
     * @throws Exception
     */
    public static function save ( RestRequest $request ): array
    {
	    if( ! PluginHelper::canAccessToSettings() )
		    return [];

	    $socialNetwork = $request->require( "social_network", RestRequest::TYPE_STRING, fsp__( "Social network not found" ), array_keys( SocialNetworkAddon::getSocialNetworks() ) );
        $name          = $request->require( "name", "string", fsp__( "Name is required" ) );

        $appData = apply_filters( 'fsp_add_app', [], $socialNetwork, $request );

        if ( empty( $appData ) )
        {
            throw new Exception( fsp__( 'Invalid credentials' ) );
        }

        App::insert( [
            'name'           => $name,
            'created_by'     => get_current_user_id(),
            'blog_id'        => Helper::getBlogId(),
            'social_network' => $socialNetwork,
            'data'           => json_encode( $appData ),
        ] );

        return [
            'app' => [
                'id'             => App::lastId(),
                'name'           => $name,
                'social_network' => $socialNetwork,
                'created_by'     => get_current_user_id(),
            ],
        ];
    }

    /**
     * @throws Exception
     */
    public static function delete ( RestRequest $request ): array
    {
	    if( ! PluginHelper::canAccessToSettings() )
		    return [];

	    $ids = $request->param( 'ids', [], RestRequest::TYPE_ARRAY );
        $ids = is_array( $ids ) ? array_filter( $ids, 'is_numeric' ) : [];

        if ( empty( $ids ) )
        {
            throw new Exception( fsp__( 'Please select apps to delete' ) );
        }

        App::where( 'slug', 'IS', null )->where( 'created_by', get_current_user_id() )->where( 'id', $ids )->delete();

        return [];
    }

}