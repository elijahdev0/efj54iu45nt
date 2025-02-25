<?php

namespace FSPoster\App\Providers\License;

class ActivateLicenseRequest
{

	public $licenseType;
	public $licenseCode;
	public $siteUrl;
	public $pluginVersion;
	public $email;
	public $receiveEmails;
	public $statisticData;

	public function toArray()
	{
		return [
			'license_type'      => $this->licenseType,
			'license_code'      => $this->licenseCode,
			'plugin_version'    => $this->pluginVersion,
			'statistic_data'    => $this->statisticData,
			'receive_emails'    => $this->receiveEmails,
			'email'             => $this->email,
			'site_url'          => $this->siteUrl
		];
	}

}
