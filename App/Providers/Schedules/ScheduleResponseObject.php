<?php

namespace FSPoster\App\Providers\Schedules;


class ScheduleResponseObject
{
    public ?string $remote_post_id = null;
    public ?string $error_msg = null;
    public string $status = 'error';
    public ?array                         $data    = [];
}