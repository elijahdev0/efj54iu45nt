<?php

namespace FSPoster\App\Pages\Analytics;

use FSPoster\App\Providers\Core\RestRequest;

class Controller
{
    /**
     * @throws \Exception
     */
    public static function getStats(RestRequest $request): array
    {
        $widgets  = $request->require('widgets', RestRequest::TYPE_ARRAY, fsp__('No widgets selected'));

        Widgets::register();

        $response = [];

        foreach ($widgets as $widget)
        {
            $response[] = [
                'name' => $widget['name'],
                'data' => apply_filters('fsp_analytics_get_widget', [], $widget['name'], $widget['options'] ?? [])
            ];
        }

        return [
            'widgets' => $response
        ];
    }
}