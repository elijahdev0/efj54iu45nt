<?php

namespace FSPoster\App\SocialNetworks\Instagram\Adapters;

use FSPoster\App\Models\Channel;
use FSPoster\App\Providers\Channels\ChannelService;
use FSPoster\App\SocialNetworks\Instagram\App\Bootstrap;

class ChannelAdapter
{

    public static function fetchChannels ( $api, $method ): array
    {
	    $me = $api->getMe();

	    $me['name'] = $me['name'] ?? '?';

	    $channelSessionId = ChannelService::addChannelSession( [
		    'name'           => $me['name'],
		    'social_network' => Bootstrap::getInstance()->getSlug(),
		    'remote_id'      => $me['id'],
		    'proxy'          => $api->proxy,
		    'method'         => $method,
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

		if( $method === 'app' )
			$fetchedAccounts = $api->fetchInstagramAccounts();
		else
			$fetchedAccounts = [ $me ];

	    foreach ( $fetchedAccounts as $instagramAccountInfo )
	    {
		    $channelData = [
			    'id'                    => $existingChannelsIdToRemoteIdMap[ $instagramAccountInfo['id'].':account' ] ?? null,
			    'social_network'        => Bootstrap::getInstance()->getSlug(),
			    'name'                  => $instagramAccountInfo['name'] ?? $instagramAccountInfo['username'],
			    'channel_session_id'    => $channelSessionId,
			    'channel_type'          => 'account',
			    'remote_id'             => $instagramAccountInfo['id'],
			    'picture'               => $instagramAccountInfo['profile_picture_url'] ?? '',
			    'data'                  => [
					'username'  => $instagramAccountInfo['username']
			    ]
		    ];

		    $channelsList[] = $channelData;

			/* Add account for story channel */
			$channelData['id'] = $existingChannelsIdToRemoteIdMap[ $instagramAccountInfo[ 'id' ].':account_story' ] ?? null;
			$channelData['channel_type'] = 'account_story';
			$channelsList[] = $channelData;
	    }

	    return $channelsList;
    }

}