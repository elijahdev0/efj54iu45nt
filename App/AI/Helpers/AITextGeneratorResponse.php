<?php

namespace FSPoster\App\AI\Helpers;

class AITextGeneratorResponse extends AIResponse
{
    /** @var string if status is success result will be used */
    public string $aiGeneratedText;
}