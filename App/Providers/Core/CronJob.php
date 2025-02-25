<?php

namespace FSPoster\App\Providers\Core;

use FSPoster\App\Providers\Helpers\Helper;
use FSPoster\App\Providers\License\LicenseAdapter;
use FSPoster\App\Providers\Planners\PlannerService;
use FSPoster\App\Providers\Schedules\ScheduleService;


class CronJob
{
    /**
     * @var BackgroundProcess
     */
    private static BackgroundProcess $backgroundProcess;

    public static function init ()
    {
		/** Virtual cron job sondurulubse ve wp-cron.php deyilse, davam etmesin. */
		if( ! defined( 'DOING_CRON' ) && Settings::get( 'virtual_cron_job_disabled', false ) )
			return;

	    self::$backgroundProcess = new BackgroundProcess();

	    if ( ! Helper::processRuntimeController( 'cron_job', 30 ) )
		    return;

        if ( defined( 'DOING_CRON' ) )
        {
            add_action( 'init', function ()
            {
                set_time_limit( 0 );

                CronJob::runTasks();
            }, 100000 );
        }
		else if ( !self::isThisProcessBackgroundTask() )
        {
            add_action( 'init', function ()
            {
                self::$backgroundProcess->dispatch();
            }, 100000 );
        }
    }

    public static function isThisProcessBackgroundTask (): bool
    {
        $action = Request::get( 'action' );

        return $action === self::$backgroundProcess->getAction();
    }

	public static function runTasks()
	{
		PlannerService::sharePlanners();
		ScheduleService::shareQueuedSchedules();

		LicenseAdapter::checkLicenseAndDisableWebsiteIfNeed();
		LicenseAdapter::getNews();

		do_action( 'fsp_cron' );
	}

}
