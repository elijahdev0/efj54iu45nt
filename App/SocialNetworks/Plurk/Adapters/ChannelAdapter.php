<?php

namespace FSPoster\App\SocialNetworks\Plurk\Adapters;

use FSPoster\App\Models\Channel;
use FSPoster\App\Providers\Channels\ChannelService;
use FSPoster\App\SocialNetworks\Plurk\App\Bootstrap;

class ChannelAdapter
{

    public static function fetchChannels ( $api ): array
    {
        $data = $api->getMyInfo();

	    if ( empty( $data[ 'id' ] ) )
		    throw new $api->authException( fsp__( 'Account not found' ) );

	    $channelSessionId = ChannelService::addChannelSession( [
		    'name'           => $data['full_name'],
		    'social_network' => Bootstrap::getInstance()->getSlug(),
		    'remote_id'      => $data['id'],
		    'proxy'          => $api->proxy,
		    'method'         => 'app',
		    'data'           => [
			    'auth_data' =>  (array)$api->authData
		    ]
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
			'id'                    => $existingChannelsIdToRemoteIdMap[$data['id']] ?? null,
			'name'                  => $data['full_name'],
			'social_network'        => Bootstrap::getInstance()->getSlug(),
			'channel_type'          => 'account',
			'remote_id'             => $data['id'],
			'channel_session_id'    => $channelSessionId,
			'picture'               => $data['avatar_big'],
			'data'                  => [
				'username' => $data['nick_name']
			]
		];

        return $channelsList;
    }

}