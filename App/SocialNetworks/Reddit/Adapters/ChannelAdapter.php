<?php

namespace FSPoster\App\SocialNetworks\Reddit\Adapters;

use FSPoster\App\Models\Channel;
use FSPoster\App\Models\ChannelSession;
use FSPoster\App\Providers\Channels\ChannelService;
use FSPoster\App\Providers\DB\Collection;
use FSPoster\App\Providers\Schedules\SocialNetworkApiException;
use FSPoster\App\SocialNetworks\Reddit\Api\Api;
use FSPoster\App\SocialNetworks\Reddit\Api\AuthData;
use FSPoster\App\SocialNetworks\Reddit\App\Bootstrap;

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
	    $channelId = $data['id'];

        $channelSessionId = ChannelService::addChannelSession( [
            'name'           => ! empty( $data['subreddit']['title'] ) ? $data['subreddit']['title'] : $data['name'],
            'social_network' => Bootstrap::getInstance()->getSlug(),
            'remote_id'      => $channelId,
            'proxy'          => $api->proxy,
            'method'         => 'app',
            'data'           => [
                'auth_data' => (array)$api->authData,
			    'username'  =>  $data['name']
            ]
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
		    'name'                  => ! empty( $data['subreddit']['title'] ) ? $data['subreddit']['title'] : $data['name'],
		    'channel_type'          => 'account',
		    'remote_id'             => $channelId,
		    'picture'               => $data['icon_img'],
		    'channel_session_id'    => $channelSessionId,
		    'data'                  => [
				'username'  =>  $data['name']
		    ]
	    ];

        return $channelsList;
    }

	/**
	 * @param ChannelSession $channelSession
	 * @param AuthData       $authData
	 *
	 * @return bool
	 */
	public static function updateAuthDataIfRefreshed( Collection $channelSession, AuthData $authData ): bool
	{
		$authDataArray = $channelSession->data_obj->auth_data;

		if( $authDataArray['accessToken'] !== $authData->accessToken )
		{
			$updateSessionData = $channelSession->data_obj->toArray();

			$updateSessionData[ 'auth_data' ] = (array)$authData;

			ChannelSession::where( 'id', $channelSession->id )->update( [
				'data' => json_encode( $updateSessionData )
			] );

			return true;
		}

		return false;
	}

}