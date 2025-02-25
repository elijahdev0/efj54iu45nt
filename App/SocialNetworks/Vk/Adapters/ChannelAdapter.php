<?php

namespace FSPoster\App\SocialNetworks\Vk\Adapters;

use FSPoster\App\Models\Channel;
use FSPoster\App\Providers\Channels\ChannelService;
use FSPoster\App\Providers\Schedules\SocialNetworkApiException;
use FSPoster\App\SocialNetworks\Vk\Api\Api;
use FSPoster\App\SocialNetworks\Vk\App\Bootstrap;

class ChannelAdapter
{

    /**
     * @param Api $api
     *
     * @return array[]
     * @throws SocialNetworkApiException
     */
    public static function fetchChannels ( Api $api ): array
    {
        $data = $api->getMyInfo();

        if ( empty( $data[ 'id' ] ) )
            throw new $api->authException( fsp__( 'Account not found' ) );

		$channelId = $data[ 'id' ];

        $channelSessionId = ChannelService::addChannelSession( [
            'name'           => ($data[ 'first_name' ] ?? '-') . ' ' . ($data[ 'last_name' ] ?? ''),
            'social_network' => Bootstrap::getInstance()->getSlug(),
            'remote_id'      => $channelId,
            'proxy'          => $api->proxy,
            'method'         => 'app',
            'data'           => [
                'auth_data' => (array)$api->authData,
                'username'  => $data[ 'screen_name' ] ?? '-',
            ],
        ]);

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
		    'id'                    => $existingChannelsIdToRemoteIdMap[$channelId] ?? null,
		    'social_network'        => Bootstrap::getInstance()->getSlug(),
		    'name'                  => ($data[ 'first_name' ] ?? '') . ' ' . ($data[ 'last_name' ] ?? ''),
		    'channel_type'          => 'account',
		    'remote_id'             => $channelId,
		    'picture'               => $data[ 'photo' ] ?? '',
		    'channel_session_id'    => $channelSessionId,
		    'data'                  => [
			    'username'  => $data[ 'screen_name' ] ?? ''
		    ]
	    ];

	    $preventDublicates = [];

	    //load admin groups
	    foreach ( $api->getMyGroupsList( true ) as $vkGroupInf )
	    {
		    if( ! isset( $vkGroupInf[ 'id' ] ) )
			    continue;

		    $preventDublicates[] = $vkGroupInf[ 'id' ];

		    $channelsList[] = [
			    'id'                    => $existingChannelsIdToRemoteIdMap[$vkGroupInf[ 'id' ]] ?? null,
			    'social_network'        => Bootstrap::getInstance()->getSlug(),
			    'name'                  => $vkGroupInf[ 'name' ] ?? '-',
			    'channel_type'          => $vkGroupInf[ 'type' ] ?? '',
			    'remote_id'             => $vkGroupInf[ 'id' ] ?? '',
			    'picture'               => $vkGroupInf[ 'photo_50' ] ?? '',
			    'channel_session_id'    => $channelSessionId,
			    'data'                  => [
				    'username'  => $vkGroupInf[ 'screen_name' ] ?? ''
			    ]
		    ];
	    }

	    //load public groups
	    foreach ( $api->getMyGroupsList() as $vkGroupInf )
	    {
			if( ! isset( $vkGroupInf[ 'id' ] ) )
				continue;

		    if( in_array( $vkGroupInf[ 'id' ], $preventDublicates ) )
			    continue;

		    $channelsList[] = [
			    'id'                    => $existingChannelsIdToRemoteIdMap[$vkGroupInf[ 'id' ]] ?? null,
			    'social_network'        => Bootstrap::getInstance()->getSlug(),
			    'name'                  => $vkGroupInf[ 'name' ] ?? '-',
			    'channel_type'          => $vkGroupInf[ 'type' ] ?? '',
			    'remote_id'             => $vkGroupInf[ 'id' ],
			    'picture'               => $vkGroupInf[ 'photo_50' ] ?? '',
			    'channel_session_id'    => $channelSessionId,
			    'data'                  => [
				    'username'  => $vkGroupInf[ 'screen_name' ] ?? ''
			    ]
		    ];
	    }

        return $channelsList;
    }

}