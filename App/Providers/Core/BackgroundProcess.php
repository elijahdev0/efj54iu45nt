<?php

namespace FSPoster\App\Providers\Core;

use FSPoster\WP_Async_Request;

class BackgroundProcess extends WP_Async_Request
{
    /**
     * @var string
     */
    protected $action = 'fs_poster_background_process';

    /**
     * Handle
     *
     * Override this method to perform any actions required
     * during the async request.
     */
    protected function handle ()
    {
        set_time_limit( 0 );

	    CronJob::runTasks();
    }

    public function getAction ()
    {
        return $this->action;
    }

}
