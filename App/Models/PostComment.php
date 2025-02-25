<?php

namespace FSPoster\App\Models;

use FSPoster\App\Providers\DB\Model;
use FSPoster\App\Providers\DB\QueryBuilder;

/**
 * @property-read int $id
 * @property-read int $channel_id
 * @property-read string $comment
 * @property-read string $comment_url
 * @property-read string $error
 * @property-read string $created_at
 */
// doit remove it.
class PostComment extends Model
{
    public static array $writeableColumns = [
         'id',
         'channel_id',
         'comment',
         'comment_url',
         'error',
         'created_at',
    ];
}