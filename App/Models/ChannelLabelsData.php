<?php

namespace FSPoster\App\Models;

use FSPoster\App\Providers\DB\Model;

/**
 * @property-read int $label_id
 * @property-read int $channel_id
 */
class ChannelLabelsData extends Model
{
    public static array $writeableColumns = [
        'label_id',
        'channel_id',
    ];

    public static ?string $tableName = 'channel_labels_data';

}