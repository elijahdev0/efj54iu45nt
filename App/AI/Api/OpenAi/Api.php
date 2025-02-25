<?php

namespace FSPoster\App\AI\Api\OpenAi;

use Exception;
use FSPoster\App\AI\Helpers\AIHelper;
use FSPoster\App\AI\Helpers\AIImageGeneratorResponse;
use FSPoster\App\AI\Helpers\AITextGeneratorResponse;
use FSPoster\App\Models\AITemplate;
use FSPoster\App\Providers\DB\Collection;
use FSPoster\App\Providers\Helpers\GuzzleClient;

class Api
{
    private string $key;
    private const OPEN_AI_API_URL = 'https://api.openai.com/';

    public function __construct( string $key )
    {
        $this->key = $key;
    }

    private function getClient () : GuzzleClient
    {
        return new GuzzleClient([
            'base_uri'  => self::OPEN_AI_API_URL,
            'headers'   => [
                'Authorization' => 'Bearer ' . $this->key,
            ]
        ]);
    }

    /** @param Collection|AITemplate $template */
    public function generateText(string $renderedPrompt, Collection $template) : AITextGeneratorResponse
    {
        $body = [
            'model'       => $template->ai_model,
            'messages'    => [
                [
                    'role'    => 'user',
                    'content' => $renderedPrompt
                ]
            ],
            'temperature' => $template->config_obj->temperature
        ];

        $aiResponse = new AITextGeneratorResponse();

        $aiResponse->model      = $template->ai_model;
        $aiResponse->provider   = 'openai';
        $aiResponse->endpoint   = 'v1/chat/completions';
        $aiResponse->templateId = $template->id;
        $aiResponse->prompt     = $renderedPrompt;
        $aiResponse->body       = json_encode($body);

        try
        {
            $response = (string) $this->getClient()->post( 'v1/chat/completions', [
                'json' => $body
            ] )->getBody();

            $aiResponse->rawResponse = $response;

            $response = json_decode($response, true);

            if( ! isset( $response['choices']['0']['message']['content'] ) )
            {
                $aiResponse->status = 'fail';
            }
            else
            {
                $aiResponse->status = 'success';
                $aiResponse->response = trim( $response['choices']['0']['message']['content'], '"' );
                $aiResponse->aiGeneratedText = trim( $response['choices']['0']['message']['content'], '"' );
            }
        }
        catch ( Exception $e )
        {
            $aiResponse->rawResponse = $e->getMessage();
            $aiResponse->status = 'fail';
        }

        return $aiResponse;
    }

    /** @param Collection|AITemplate $template */
    public function generateImage(string $renderedPrompt, Collection $template) : AIImageGeneratorResponse
    {
        $body = [
            'model'       => $template->ai_model,
            'prompt'      => $renderedPrompt,
            'n'           => 1,
            'size'        => $template->config_obj->size,
            'style'       => $template->config_obj->style
        ];

        $aiResponse = new AIImageGeneratorResponse();

        $aiResponse->model      = $template->ai_model;
        $aiResponse->provider   = 'openai';
        $aiResponse->endpoint   = 'v1/images/generations';
        $aiResponse->templateId = $template->id;
        $aiResponse->prompt     = $renderedPrompt;
        $aiResponse->body       = json_encode($body);

        try
        {
            $response = (string) $this->getClient()->post( 'v1/images/generations', [
                'json' => $body
            ] )->getBody();

            $aiResponse->rawResponse = $response;

            $response = json_decode($response, true);

            if(!isset($response['data'][0]['url']))
            {
                $aiResponse->status = 'fail';
            }
            else
            {
                $id = AIHelper::createImageAttachmentFromUrl($template->provider, $response['data'][0]['url']);
                $aiResponse->status = 'success';
                $aiResponse->response = $id;
                $aiResponse->attachmentId = $id;
            }
        }
        catch ( Exception $e )
        {
            $aiResponse->rawResponse = $e->getMessage();
            $aiResponse->status = 'fail';
        }

        return $aiResponse;
    }

}