<?php

namespace FSPoster\App\SocialNetworks\Blogger\Api;

class PostingData
{

	public string $kind;
	public string $blogId;
	public string $title;
	public string $content;
	public string $authorId;
	public array $labels;
	public bool $isDraft;

}