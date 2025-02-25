<?php

namespace FSPoster\App\SocialNetworks\Telegram\Api;

class PostingData
{

    public string $message;
	public array $uploadMedia;
	public bool $addReadMoreBtn;
	public string $readMoreBtnUrl;
	public string $readMoreBtnText;
	public bool $silent;

}