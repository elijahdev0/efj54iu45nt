<?php

namespace FSPoster\App\Providers\Core;

class LocalizationService
{

	public static function loadTextdomain ()
	{
		load_plugin_textdomain( FSP_PLUGIN_SLUG, false, FSP_PLUGIN_SLUG . '/languages' );
	}

	public static function getAllStrings ()
	{
		global $l10n;

		// Check if the domain is loaded
		if ( ! isset( $l10n[FSP_PLUGIN_SLUG] ) )
			return [];

		$moObject = $l10n[FSP_PLUGIN_SLUG];
		$translations = $moObject->entries;
		$translationStrings = [];

		foreach ( $translations AS $entry )
		{
			// Original string
			$original = $entry->singular;
			// Translated string (if exists)
			$translated = ! empty( $entry->translations ) ? $entry->translations[0] : $original;
			$translationStrings[$original] = $translated;
		}

		return $translationStrings;
	}

}