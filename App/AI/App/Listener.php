<?php

namespace FSPoster\App\AI\App;

use Exception;
use FSPoster\App\AI\Api\OpenAi\Api;
use FSPoster\App\AI\Helpers\AIImageGeneratorResponse;
use FSPoster\App\AI\Helpers\AITextGeneratorResponse;
use FSPoster\App\Models\AILogs;
use FSPoster\App\Models\AITemplate;
use FSPoster\App\Providers\Core\RestRequest;
use FSPoster\App\Providers\Core\Settings;
use FSPoster\App\Providers\DB\Collection;
use FSPoster\App\Providers\Helpers\Date;
use FSPoster\App\Providers\Helpers\Helper;
use FSPoster\App\Providers\Helpers\WPPostThumbnail;
use FSPoster\App\Providers\Schedules\ScheduleObject;
use FSPoster\App\Providers\Schedules\ScheduleShareException;

class Listener
{

	public static function saveSocialNetworkSettings ( RestRequest $request, string $socialNetwork )
	{
		$mediaTypeToUpload = $request->param( 'media_type_to_upload', 'featured_image', RestRequest::TYPE_STRING );

		if( $mediaTypeToUpload === 'ai_image' )
		{
			$aiTempalteId = (int)$request->param( 'ai_template_id', 0, RestRequest::TYPE_INTEGER );

			if( $aiTempalteId <= 0 )
				throw new \Exception( fsp__('Please select AI template!') );

			Settings::set( sprintf('%s_ai_template_id', $socialNetwork ), $aiTempalteId );
		}
	}

	public static function getSocialNetworkSettings ( $data, $socialNetwork )
	{
		if( array_key_exists( 'media_type_to_upload', $data ) && $data['media_type_to_upload'] === 'ai_image' )
		{
			$data['ai_template_id'] = (int)Settings::get( sprintf('%s_ai_template_id', $socialNetwork ) );
		}

		return $data;
	}

	public static function getCustomPostData ( array $customPostData, Collection $channel, string $socialNetwork ): array
	{
		if( isset( $customPostData["upload_media_type"] ) && $customPostData["upload_media_type"] === 'ai_image' )
		{
			$customPostData["ai_template_id"] = (int)Settings::get( sprintf('%s_ai_template_id', $socialNetwork ) );
		}

		return $customPostData;
	}

	public static function addAIToShortCodes ( $shortCodes )
	{
		$shortCodes['ai_text_generator'] = [ self::class, 'aiTextGenerator' ];

		return $shortCodes;
	}

	public static function aiTextGenerator ( ScheduleObject $scheduleObj, $props = [] ): string
	{
		if( $scheduleObj->readOnlyMode )
			return '[AI_text_will_be_generated_while_posting]';

		if ( empty( $props['template_id'] ) )
			return '';

		$template = AITemplate::get( $props[ 'template_id' ] );
		if ( ! $template )
			return '';

		/* Ola bilecek sonsuz loopun qarshisini almag uchundu bu replace */
		$propmt =  str_replace( '{ai_text_generator', '{!ai_text_generator', $template->prompt );

		$renderedPrompt = $scheduleObj->replaceShortCodes( $propmt );

		$cached = AILogs::where( 'schedule_id', $scheduleObj->getSchedule()->id )
		                ->where( 'template_id', $template->id )
		                ->where( 'status', 'success' )
		                ->where( 'ai_model', $template->ai_model )
		                ->where( 'prompt', $renderedPrompt )
		                ->orderBy( 'created_at DESC' )
		                ->fetch();

		if ( $cached && isset( $cached->response ) )
			return $cached->response;

		/** @var AITextGeneratorResponse $aiResponse */
		$aiResponse = apply_filters( 'fsp_ai_text_generator', new AITextGeneratorResponse(), $template->provider, $renderedPrompt, $template );

		AILogs::insert( [
			'provider'          => $template->provider,
			'ai_model'          => $template->ai_model,
			'template_id'       => $template->id,
			'endpoint'          => $aiResponse->endpoint,
			'status'            => $aiResponse->status,
			'prompt'            => $renderedPrompt,
			'response'          => $aiResponse->response,
			'schedule_id'       => $scheduleObj->getSchedule()->id,
			'raw_response'      => $aiResponse->rawResponse,
			'body'              => $aiResponse->body,
			'blog_id'           => Helper::getBlogId(),
			'created_at'        => Date::dateTimeSQL(),
		] );

		if ( $aiResponse->status !== 'success' )
		{
			/* Ola bilecek sonsuz loopun qarshisini almag uchundu bu replace */
			$fallbackText = str_replace( '{ai_text_generator', '{!ai_text_generator', $template->fallback_text );

			return $scheduleObj->replaceShortCodes( $fallbackText );
		}

		return $aiResponse->aiGeneratedText;
	}

	public static function addAIToMediaList ( $mediaListToUpload, ScheduleObject $scheduleObj )
	{
		$scheduleData = $scheduleObj->getSchedule()->customization_data_obj;

		if( ! $scheduleObj->readOnlyMode && $scheduleData->upload_media && $scheduleData->upload_media_type === 'ai_image' && ! empty( $scheduleData->ai_template_id ) )
		{
			$template = AITemplate::get( $scheduleData->ai_template_id );
			if ( ! $template )
				return $mediaListToUpload;

			$renderedPrompt = $scheduleObj->replaceShortCodes( $template->prompt );

			$cached = AILogs::where( 'schedule_id', $scheduleObj->getSchedule()->id )
			                ->where( 'template_id', $template->id )
			                ->where( 'status', 'success' )
			                ->where( 'ai_model', $template->ai_model )
			                ->where( 'prompt', $renderedPrompt )
			                ->orderBy( 'created_at DESC' )
			                ->fetch();

			if ( $cached && isset( $cached->response ) )
				return [
					[
						'id'    =>  $cached->response ,
						'type'  =>  'image',
						'path'  =>  WPPostThumbnail::getOrCreateImagePath( $cached->response, $scheduleObj->readOnlyMode ),
						'url'   =>  wp_get_attachment_url( $cached->response ),
					],
				];

			/** @var AIImageGeneratorResponse $aiResponse */
			$aiResponse = apply_filters( 'fsp_ai_image_generator', new AIImageGeneratorResponse(), $template->provider, $renderedPrompt, $template );

			AILogs::insert( [
				'provider'      => $template->provider,
				'ai_model'      => $template->ai_model,
				'template_id'   => $template->id,
				'endpoint'      => $aiResponse->endpoint,
				'status'        => $aiResponse->status,
				'prompt'        => $renderedPrompt,
				'response'      => $aiResponse->response,
				'schedule_id'   => $scheduleObj->getSchedule()->id,
				'raw_response'  => $aiResponse->rawResponse,
				'body'          => $aiResponse->body,
				'blog_id'       => Helper::getBlogId(),
				'created_at'    => Date::dateTimeSQL(),
			] );

			if ( $aiResponse->status === 'success' )
				return [
					[
						'id'    =>  $aiResponse->attachmentId ,
						'type'  =>  'image',
						'path'  =>  WPPostThumbnail::getOrCreateImagePath( $aiResponse->attachmentId, $scheduleObj->readOnlyMode ),
						'url'   =>  wp_get_attachment_url( $aiResponse->attachmentId ),
					],
				];
		}

		return $mediaListToUpload;
	}

    /**
     * @throws Exception
     */
    public static function save(array $template, string $provider) : array
    {
        if($provider != 'openai') return $template;

        if( !(($template['type'] === 'text' && preg_match('/\b(gpt)\b/i', $template['ai_model'])) || ($template['type'] === 'image' && $template['ai_model'] === 'dall-e-3')) )
        {
            throw new Exception(fsp__('Model type and AI model doesn\'t match'));
        }

        if(preg_match('/\b(gpt)\b/i', $template['ai_model']))
        {
            if(!isset($template['config']['temperature']))
            {
                throw new Exception(fsp__('Please enter a temperature value'));
            }

            $temperature = $template['config']['temperature'];

            if(!is_numeric($temperature) || $temperature < 0 || $temperature > 2)
            {
                throw new Exception(fsp__('Temperature must be a a numeric value between 0 and 2'));
            }

            $template['config']['temperature'] = (float) $temperature;
        }

        if($template['ai_model'] === 'dall-e-3')
        {
            if(!isset($template['config']['size']) || !in_array($template['config']['size'], ['1024x1024', '1792x1024', '1024x1792']))
            {
                throw new Exception(fsp__('Please select an image size'));
            }

            if(!isset($template['config']['style']) || !in_array($template['config']['style'], ['vivid', 'natural']))
            {
                throw new Exception(fsp__('Please select an image style'));
            }
        }

        return $template;
    }

    /** @param AITemplate|Collection $template */
    public static function ShortCodeAITextGenerator(AITextGeneratorResponse $aiResponse, string $provider, string $renderedPrompt, Collection $template) : AITextGeneratorResponse
    {
        if($provider !== 'openai') return $aiResponse;

        $key = Settings::get('openai_key');

		if( empty( $key ) )
			throw new ScheduleShareException(fsp__('OpenAI API key is empty! Please enter the Setting > AI Settings and then enter your OpenAI API key.'));

        return (new Api($key))->generateText($renderedPrompt, $template);
    }

    /** @param AITemplate|Collection $template */
    public static function AIImageGenerator(AIImageGeneratorResponse $aiResponse, string $provider, string $renderedPrompt, Collection $template) : AIImageGeneratorResponse
    {
        if($provider !== 'openai') return $aiResponse;

        $key = Settings::get('openai_key');

        return (new Api($key))->generateImage($renderedPrompt, $template);
    }

}