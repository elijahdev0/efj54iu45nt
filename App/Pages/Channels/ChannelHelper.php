<?php

namespace FSPoster\App\Pages\Channels;

use FSPoster\App\Models\Channel;
use FSPoster\App\Models\ChannelSession;

class ChannelHelper
{
    public static function getPartialChannels(array $channelIds = [], bool $getActiveChannelsIfEmpty = false): array
    {
        $channels = [];

        if(empty($channelIds) && !$getActiveChannelsIfEmpty)
        {
            return [];
        }

        if(empty($channelIds) && $getActiveChannelsIfEmpty)
        {
            $channels = Channel::where('auto_share', 1)->fetchAll();
        }
        else if(!empty($channelIds))
        {
            $channels = Channel::where('id', 'in', $channelIds)->fetchAll();
        }

        if(empty($channels)){
            return [];
        }

        $channelsList = [];

        foreach ( $channels as $channel )
        {
            $channelSession = $channelSessions[$channel->channel_session_id] ?? $channel->channel_session->fetch();
            $channelSessions[$channel->channel_session_id] = $channelSession;

            $channelsList[] = apply_filters('fsp_get_channel', [
                'id' => (int) $channel->id,
                'name' => ! empty( $channel->name ) ? $channel->name : "[no name]",
                'social_network' => $channelSession->social_network,
                'picture' => $channel->picture,
                'channel_type' => $channel->channel_type,
                'channel_link' => apply_filters('fsp_get_channel_link', '', $channelSession->social_network, $channel),
                'method' => $channelSession->method,
            ], $channelSession->social_network, $channel, $channelSession);
        }

        return $channelsList;
    }

}