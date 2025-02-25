<?php

namespace FSPoster\App\SocialNetworks\Blogger\Adapters;

use FSPoster\App\Models\Channel;
use FSPoster\App\Models\ChannelSession;
use FSPoster\App\Providers\Channels\ChannelService;
use FSPoster\App\Providers\DB\Collection;
use FSPoster\App\Providers\Schedules\SocialNetworkApiException;
use FSPoster\App\SocialNetworks\Blogger\Api\Api;
use FSPoster\App\SocialNetworks\Blogger\Api\AuthData;
use FSPoster\App\SocialNetworks\Blogger\App\Bootstrap;

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

	    $channelSessionId = ChannelService::addChannelSession( [
		    'name'           => $data[ 'displayName' ],
		    'social_network' => Bootstrap::getInstance()->getSlug(),
		    'remote_id'      => $data[ 'id' ],
		    'proxy'          => $api->proxy,
		    'method'         => 'app',
		    'data'           => [
			    'auth_data' =>  (array)$api->authData,
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

	    $blogs = $api->getBlogsList();

	    $channelsList = [];

	    foreach ( $blogs as $blog )
	    {
		    $channelsList[] = [
			    'id'                    => $existingChannelsIdToRemoteIdMap[ $blog[ 'id' ] ] ?? null,
			    'social_network'        => Bootstrap::getInstance()->getSlug(),
			    'name'                  => $blog[ 'name' ],
			    'channel_type'          => 'blog',
			    'channel_session_id'    => $channelSessionId,
			    'remote_id'             => $blog[ 'id' ],
			    'picture'               => null,
			    'data'                  => [
				    'url'   => $blog[ 'url' ]
			    ]
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