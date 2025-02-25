<?php

namespace FSPoster\App\Models;

use FSPoster\App\Providers\DB\BlogScope;
use FSPoster\App\Providers\DB\Model;

/**
 * @property-read string $id
 * @property-read string $provider
 * @property-read string $prompt
 * @property-read string $ai_model
 * @property-read string $status
 * @property-read string $template_id
 * @property-read string $body
 * @property-read string $raw_response
 * @property-read string $response
 * @property-read int    $planner_id
 * @property-read string $endpoint
 * @property-read int    $blog_id
 * @property-read int    $schedule_id
 * @property-read string $created_at
 */
class AILogs extends Model
{
	use BlogScope;

    public static ?string $tableName = 'ai_logs';

    public static array $writeableColumns = [
        'id',
        'provider',
        'prompt',
        'ai_model',
        'template_id',
        'endpoint',
        'status',
        'raw_response',
        'response',
        'body',
        'blog_id',
        'planner_id',
        'created_at',
	    'schedule_id'
    ];
}