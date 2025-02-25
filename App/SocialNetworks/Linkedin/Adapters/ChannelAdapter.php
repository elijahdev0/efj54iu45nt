<?php

namespace FSPoster\App\SocialNetworks\Linkedin\Adapters;

use FSPoster\App\Models\Channel;
use FSPoster\App\Models\ChannelSession;
use FSPoster\App\Providers\Channels\ChannelService;
use FSPoster\App\Providers\DB\Collection;
use FSPoster\App\Providers\Schedules\SocialNetworkApiException;
use FSPoster\App\SocialNetworks\Linkedin\Api\Api;
use FSPoster\App\SocialNetworks\Linkedin\Api\AuthData;
use FSPoster\App\SocialNetworks\Linkedin\App\Bootstrap;

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

	    $channelName = ( $data['localizedFirstName'] ?? '-' ) . ' ' . ( $data['localizedLastName'] ?? '' );
	    $channelId = $data['id'];

        $channelSessionId = ChannelService::addChannelSession( [
            'name'           => $channelName,
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
		    'name'                  => $channelName,
		    'channel_type'          => 'account',
		    'remote_id'             => $channelId,
		    'picture'               => $data['profilePicture']['displayImage~']['elements'][0]['identifiers'][0]['identifier'] ?? 0,
		    'channel_session_id'    => $channelSessionId
	    ];

	    foreach ( $api->getMyOrganizations() as $organizationInf )
	    {
		    $channelRemoteId   = $organizationInf[ 'organizationalTarget~' ][ 'id' ] ?? 0;
		    $channelName       = $organizationInf[ 'organizationalTarget~' ][ 'localizedName' ] ?? '-';
		    $channelCoverPhoto = '';

		    if ( isset( $organizationInf[ 'organizationalTarget~' ][ 'logoV2' ][ 'original~' ][ 'elements' ][ 0 ][ 'identifiers' ][ 0 ][ 'identifier' ] ) )
			    $channelCoverPhoto = $organizationInf[ 'organizationalTarget~' ][ 'logoV2' ][ 'original~' ][ 'elements' ][ 0 ][ 'identifiers' ][ 0 ][ 'identifier' ];

		    $channelsList[] = [
			    'id'                    => $existingChannelsIdToRemoteIdMap[$channelRemoteId] ?? null,
			    'social_network'        => Bootstrap::getInstance()->getSlug(),
			    'name'                  => $channelName,
			    'channel_type'          => 'company',
			    'remote_id'             => $channelRemoteId,
			    'picture'               => $channelCoverPhoto,
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