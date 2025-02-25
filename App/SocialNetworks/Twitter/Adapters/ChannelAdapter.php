<?php

namespace FSPoster\App\SocialNetworks\Twitter\Adapters;

use FSPoster\App\Models\Channel;
use FSPoster\App\Providers\Channels\ChannelService;
use FSPoster\App\SocialNetworks\Twitter\App\Bootstrap;

class ChannelAdapter
{


    public static function fetchChannels ( $api, $method ): array
    {
	    $data = $api->getMyInfo();

	    $channelSessionId = ChannelService::addChannelSession( [
		    'name'           => $data[ 'name' ],
		    'social_network' => Bootstrap::getInstance()->getSlug(),
		    'remote_id'      => $data[ 'id_str' ],
		    'proxy'          => $api->proxy,
		    'method'         => $method,
		    'data'           => [
			    'auth_data' =>  (array)$api->authData,
			    'username'  => $data[ 'screen_name' ]
		    ],
	    ] );

	    $existingChannels = Channel::where( 'channel_session_id', $channelSessionId )
	                               ->select( [ 'id', 'remote_id' ], true )
	                               ->fetchAll();

	    $existingChannelsIdToRemoteIdMap = [];

	    foreach ( $existingChannels as $existingChannel )
	    {
		    $existingChannelsIdToRemoteIdMap[ $existingChannel->remote_id ] = $existingChannel->id;
	    }

	    $channelsList = [];
	    $channelsList[] = [
		    'id'                    => $existingChannelsIdToRemoteIdMap[ $data[ 'id_str' ] ] ?? null,
		    'social_network'        => Bootstrap::getInstance()->getSlug(),
		    'name'                  => $data[ 'name' ],
		    'channel_type'          => 'account',
		    'channel_session_id'    => $channelSessionId,
		    'remote_id'             => $data[ 'id_str' ],
		    'picture'               => $data[ 'profile_image_url' ],
		    'data'                  => [
			    'username'  => $data[ 'screen_name' ]
		    ]
	    ];

	    return $channelsList;
    }


}