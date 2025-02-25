<?php

namespace FSPoster\App\SocialNetworks\Telegram\Adapters;


use FSPoster\App\Models\Channel;
use FSPoster\App\Providers\Channels\ChannelService;
use FSPoster\App\SocialNetworks\Telegram\Api\Api;
use FSPoster\App\SocialNetworks\Telegram\App\Bootstrap;

class ChannelAdapter
{

    public static function fetchChannels ( Api $sdk ): array
    {
        $data = $sdk->getBotInfo();

        if ( empty( $data[ 'id' ] ) )
            throw new \Exception( fsp__( 'The entered Bot Token is invalid' ) );

        $channelSessionId = ChannelService::addChannelSession( [
            'name'              => $data[ 'name' ],
            'social_network'    => Bootstrap::getInstance()->getSlug(),
            'remote_id'         => (string)$data[ 'id' ],
            'proxy'             => $sdk->proxy,
            'method'            => 'bot_token',
            'data'              => [
                'bot_token' => $sdk->authData->token,
                'username'  => $data[ 'username' ]
            ]
        ] );

        $chats = [];

        if ( isset( $sdk->authData->chatId ) )
        {
            try
            {
                $chat = $sdk->getChatInfo( $sdk->authData->chatId );
            } catch ( \Exception $e )
            {
                throw new \Exception( fsp__( 'Chat not found' ) );
            }

            if ( empty( $chat[ 'id' ] ) )
                throw new \Exception( fsp__( 'Chat not found' ) );

            $chats[] = $chat;
        } else
        {
            $chats = $sdk->getActiveChats();
        }

        $existingChannels = Channel::where( 'channel_session_id', $channelSessionId )->select( [ 'id', 'remote_id' ], true )->fetchAll();

        $existingChannelsIdToRemoteIdMap = [];

        foreach ( $existingChannels as $existingChannel )
        {
            $existingChannelsIdToRemoteIdMap[ $existingChannel->remote_id ] = $existingChannel->id;
        }

        $channels = [];

        $preventDuplicates = [];

        foreach ( $chats as $chat )
        {
            if ( in_array( $chat[ 'id' ], $preventDuplicates ) )
            {
                continue;
            }

            $preventDuplicates[] = $chat[ 'id' ];

            $channels[] = [
                'id'                    => $existingChannelsIdToRemoteIdMap[ $chat[ 'id' ] ] ?? null,
                'social_network'        => Bootstrap::getInstance()->getSlug(),
                'name'                  => $chat[ 'name' ],
                'channel_type'          => 'chat',
                'remote_id'             => (string)$chat[ 'id' ],
                'channel_session_id'    => $channelSessionId,
                'picture'               => null,
                'data'                  => [
	                'username'  => $chat[ 'username' ]
                ]
            ];
        }

        return $channels;
    }

}