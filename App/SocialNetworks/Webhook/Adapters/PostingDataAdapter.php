<?php

namespace FSPoster\App\SocialNetworks\Webhook\Adapters;

use FSPoster\App\Providers\Schedules\ScheduleObject;
use FSPoster\App\Providers\Schedules\ScheduleShareException;
use FSPoster\App\SocialNetworks\Webhook\Api\PostingData;

class PostingDataAdapter
{

	private ScheduleObject $scheduleObj;

	public function __construct( ScheduleObject $scheduleObj )
	{
		$this->scheduleObj = $scheduleObj;
	}

	/**
	 * @return PostingData
	 */
	public function getPostingData (): PostingData
	{
		$postingData = new PostingData();

		$postingData->url = $this->getPostingDataUrl();
		$postingData->method = $this->getPostingDataMethod();
		$postingData->contentType = $this->getPostingDataContentType();
		$postingData->headers = $this->getPostingDataHeaders();
		$postingData->formData = $this->getPostingDataFormData();
		$postingData->jsonData = $this->getPostingDataJsonData();

		return apply_filters( 'fsp_schedule_posting_data', $postingData, $this->scheduleObj );
	}

	public function getPostingDataUrl()
	{
		$scheduleData = $this->scheduleObj->getSchedule()->customization_data_obj;

		if( ! $this->scheduleObj->readOnlyMode && empty( $scheduleData->url ) )
			throw new ScheduleShareException( fsp__('URL can not be empty!') );

		return $this->scheduleObj->replaceShortCodes( $scheduleData->url ?? '' );
	}

	public function getPostingDataMethod()
	{
		$scheduleData = $this->scheduleObj->getSchedule()->customization_data_obj;

		return $scheduleData->method ?? 'GET';
	}

	public function getPostingDataContentType()
	{
		$scheduleData = $this->scheduleObj->getSchedule()->customization_data_obj;

		return $scheduleData->content_type ?? 'form';
	}

	public function getPostingDataHeaders()
	{
		$scheduleData = $this->scheduleObj->getSchedule()->customization_data_obj;

		$headers = $scheduleData->headers ?? [];
		foreach ( $headers AS &$headerValue )
		{
			$headerValue = $this->scheduleObj->replaceShortCodes( $headerValue );
		}

		return $headers;
	}

	public function getPostingDataFormData()
	{
		$scheduleData = $this->scheduleObj->getSchedule()->customization_data_obj;

		$data = $scheduleData->form_data ?? [];
		foreach ( $data AS &$dataValue )
		{
			$dataValue = $this->scheduleObj->replaceShortCodes( $dataValue );
		}

		return $data;
	}

	public function getPostingDataJsonData()
	{
		$scheduleData = $this->scheduleObj->getSchedule()->customization_data_obj;

		$data = json_decode( $scheduleData->json_data ?? '[]', true );
		foreach ( $data AS &$dataValue )
		{
			$dataValue = $this->scheduleObj->replaceShortCodes( $dataValue );
		}

		return json_encode( $data );
	}

}