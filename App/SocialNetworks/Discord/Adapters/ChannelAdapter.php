<?php

namespace FSPoster\App\SocialNetworks\Discord\Adapters;


use FSPoster\App\Models\Channel;
use FSPoster\App\Providers\Channels\ChannelService;
use FSPoster\App\SocialNetworks\Discord\Api\Api;
use FSPoster\App\SocialNetworks\Discord\App\Bootstrap;

class ChannelAdapter
{

    public static function fetchChannels ( Api $sdk, $guildID ): array
    {
	    $data = $sdk->getGuild( $guildID );

	    $channelSessionId = ChannelService::addChannelSession( [
		    'name'              => $data[ 'name' ],
		    'social_network'    => Bootstrap::getInstance()->getSlug(),
		    'remote_id'         => $guildID,
		    'proxy'             => $sdk->proxy,
		    'method'            => 'app',
		    'data'              => [
			    'auth_data' =>  (array)$sdk->authData
		    ]
	    ] );

	    $existingChannels = Channel::where('channel_session_id', $channelSessionId)->select(['id', 'remote_id'], true)->fetchAll();

	    $existingChannelsIdToRemoteIdMap = [];

	    foreach ( $existingChannels as $existingChannel )
	    {
		    $existingChannelsIdToRemoteIdMap[$existingChannel->remote_id] = $existingChannel->id;
	    }

	    $getGuildChannels = $sdk->getGuildChannels( $guildID );

	    $channels = [];

	    foreach ( $getGuildChannels as $guildChannel )
	    {
		    $channels[] = [
			    'id'                 => $existingChannelsIdToRemoteIdMap[$guildChannel[ 'id' ]] ?? null,
			    'social_network'     => Bootstrap::getInstance()->getSlug(),
			    'name'               => $guildChannel[ 'name' ],
			    'channel_type'       => 'chat',
			    'remote_id'          => $guildChannel['id'],
			    'picture'            => null,
			    'channel_session_id' => $channelSessionId,
		    ];
	    }

        return $channels;
    }

}