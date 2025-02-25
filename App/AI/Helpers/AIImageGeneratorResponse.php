<?php

namespace FSPoster\App\AI\Helpers;

class AIImageGeneratorResponse extends AIResponse
{
    /** @var int if status is success result will be used */
    public int $attachmentId;
}