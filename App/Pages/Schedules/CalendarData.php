<?php

namespace FSPoster\App\Pages\Schedules;

class CalendarData
{

	public string $content = '';

	/**
	 * @var array $mediaList Array list of media. Each media should be an associative array with the following keys:
	 *                       - 'id' (int) The id of the media (WP id);
	 *                       - 'type' (string) The type of the media (image or video);
	 *                       - 'url' (string) The URL of the media;
	 *                       - 'path' (string) The path of the media file.
	 */
	public array $mediaList = [];

}