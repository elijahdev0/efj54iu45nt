<?php

namespace FSPoster\App\SocialNetworks\Odnoklassniki\Adapters;

use FSPoster\App\Models\Channel;
use FSPoster\App\Models\ChannelSession;
use FSPoster\App\Providers\Channels\ChannelService;
use FSPoster\App\Providers\DB\Collection;
use FSPoster\App\Providers\Schedules\SocialNetworkApiException;
use FSPoster\App\SocialNetworks\Odnoklassniki\Api\Api;
use FSPoster\App\SocialNetworks\Odnoklassniki\Api\AuthData;
use FSPoster\App\SocialNetworks\Odnoklassniki\App\Bootstrap;

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

		$channelId = $data['uid'];

        $channelSessionId = ChannelService::addChannelSession( [
            'name'           => $data['name'] ?? '-',
            'social_network' => Bootstrap::getInstance()->getSlug(),
            'remote_id'      => $channelId,
            'proxy'          => $api->proxy,
            'method'         => 'app',
            'data'           => [
                'auth_data' => (array)$api->authData
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
		    'name'                  => $data['name'] ?? '-',
		    'channel_type'          => 'account',
		    'remote_id'             => $channelId,
		    'picture'               => $data[ 'pic_1' ] ?? '',
		    'channel_session_id'    => $channelSessionId
	    ];

	    foreach ( $api->getGroupsList() as $groupInf )
	    {
		    $channelsList[] = [
			    'id'                    => $existingChannelsIdToRemoteIdMap[$groupInf['uid']] ?? null,
			    'social_network'        => Bootstrap::getInstance()->getSlug(),
			    'name'                  => $groupInf['name'] ?? '-',
			    'channel_type'          => 'group',
			    'remote_id'             => $groupInf['uid'] ?? '',
			    'picture'               => $groupInf['picAvatar'] ?? '',
			    'channel_session_id'    => $channelSessionId
		    ];
	    }

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