<?php

namespace FSPoster\App\SocialNetworks\Threads\Adapters;

use FSPoster\App\Models\Channel;
use FSPoster\App\Providers\Channels\ChannelService;
use FSPoster\App\SocialNetworks\Threads\Api\ThreadsClient;
use FSPoster\App\SocialNetworks\Threads\App\Bootstrap;

class ChannelAdapter
{

    public static function fetchChannels ( ThreadsClient $api ): array
    {
	    $me = $api->getMe();

        $username = $me['username'];
        $profileName = $me['name'] ?? $username;

        $channelSessionId = ChannelService::addChannelSession( [
		    'name'           => $profileName,
		    'social_network' => Bootstrap::getInstance()->getSlug(),
		    'remote_id'      => $api->authData->userId,
		    'proxy'          => $api->proxy ?? '',
		    'method'         => 'app',
		    'data'           => [
			    'auth_data' => (array)$api->authData
		    ]
	    ] );

	    $existingChannels = Channel::where( 'channel_session_id', $channelSessionId )
	                               ->select( [ 'id', 'remote_id', 'channel_type' ], true )
	                               ->fetchAll();

	    $existingChannelsIdToRemoteIdMap = [];

	    foreach ( $existingChannels as $existingChannel )
	    {
		    $existingChannelsIdToRemoteIdMap[ $existingChannel->remote_id . ':' . $existingChannel->channel_type ] = $existingChannel->id;
	    }

	    $channelsList = [];
	    $channelsList[] = [
		    'id'                    => $existingChannelsIdToRemoteIdMap[ $api->authData->userId ] ?? null,
		    'social_network'        => 'threads',
		    'name'                  => $profileName,
		    'channel_type'          => 'account',
		    'remote_id'             => $api->authData->userId,
		    'picture'               => $me['threads_profile_picture_url'] ?? null,
		    'channel_session_id'    => $channelSessionId,
		    'data'                  => [
				'username'  =>  $username
		    ]
	    ];

	    return $channelsList;
    }

}