<?php

namespace FSPoster\App\AI\Helpers;

use Exception;
use FSPoster\App\Providers\Helpers\Helper;

class AIHelper
{
    /**
     * @throws Exception
     */
    public static function createImageAttachmentFromUrl(string $provider, string $url) : int
    {
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');

        $tmpFile = download_url($url);
        $mime = Helper::mimeContentType($tmpFile);

        $filename = $provider . '-' . Helper::generateUUID() . '.' . explode('/', $mime)[1];

        if (is_wp_error($tmpFile))
        {
            throw new Exception($tmpFile->get_error_message());
        }

        $attached = media_handle_sideload(array('name' => $filename, 'tmp_name' => $tmpFile));

        if (is_wp_error($attached))
        {
            throw new Exception($attached->get_error_message());
        }

        if(file_exists($tmpFile))
        {
            unlink($tmpFile);
        }
        
        add_post_meta($attached, 'fsp_ai_generated_image', 1, true);

        return $attached;
    }
}