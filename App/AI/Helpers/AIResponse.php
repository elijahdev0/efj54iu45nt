<?php

namespace FSPoster\App\AI\Helpers;

class AIResponse
{
    public string $provider;
    public string $model;
    /** @var string Response as json or any other relevant format */
    public string $rawResponse;
    /** @var ?string used for caching, you may return the result you want to save or you can handle it your way */
    public ?string $response = null;
    /** @var string Body as json or any other relevant format */
    public string $body;
    public string $endpoint;
    public string $templateId;
    /** @var string success|fail|... */
    public string $status;
    public string $prompt;
}