<?php
/**
 * OpenAPI Documentation Generator for FS Poster REST API
 *
 * This file generates OpenAPI documentation for the FS Poster WordPress plugin's REST API endpoints.
 * Run this file to generate the OpenAPI specification in JSON format.
 *
 * @package FSPoster
 */

require_once __DIR__ . '/vendor/autoload.php';

use OpenApi\Annotations as OA;

/**
 * @OA\Info(
 *     title="FS Poster API",
 *     version="v1",
 *     description="REST API for FS Poster WordPress plugin for social media automation"
 * )
 *
 * @OA\Server(
 *     url="/wp-json/fs-poster/v1",
 *     description="WordPress REST API for FS Poster"
 * )
 *
 * @OA\SecurityScheme(
 *     securityScheme="wp_auth",
 *     type="http",
 *     scheme="basic",
 *     description="WordPress authentication"
 * )
 *
 * @OA\Tag(
 *     name="Settings",
 *     description="Plugin settings endpoints"
 * )
 * @OA\Tag(
 *     name="Metabox",
 *     description="Post metabox integration endpoints"
 * )
 * @OA\Tag(
 *     name="Planners",
 *     description="Post scheduling planner endpoints"
 * )
 * @OA\Tag(
 *     name="Social Networks",
 *     description="Social network integration endpoints"
 * )
 * @OA\Tag(
 *     name="AI",
 *     description="AI integration endpoints"
 * )
 */

/**
 * @OA\Schema(
 *     schema="Error",
 *     @OA\Property(property="error_msg", type="string", example="An error occurred")
 * )
 */

/**
 * @OA\Get(
 *     path="/settings/general",
 *     summary="Get general settings",
 *     description="Retrieves the general plugin settings",
 *     tags={"Settings"},
 *     security={{"wp_auth":{}}},
 *     @OA\Response(
 *         response=200,
 *         description="General settings",
 *         @OA\JsonContent(
 *             @OA\Property(property="allowed_post_types", type="array", @OA\Items(type="string")),
 *             @OA\Property(property="show_fs_poster_to", type="array", @OA\Items(type="string")),
 *             @OA\Property(property="virtual_cron_job_disabled", type="boolean")
 *         )
 *     ),
 *     @OA\Response(
 *         response=403,
 *         description="Access denied",
 *         @OA\JsonContent(ref="#/components/schemas/Error")
 *     )
 * )
 */

/**
 * @OA\Post(
 *     path="/settings/general",
 *     summary="Save general settings",
 *     description="Updates the general plugin settings",
 *     tags={"Settings"},
 *     security={{"wp_auth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             @OA\Property(property="allowed_post_types", type="array", @OA\Items(type="string")),
 *             @OA\Property(property="show_fs_poster_to", type="array", @OA\Items(type="string")),
 *             @OA\Property(property="virtual_cron_job_disabled", type="boolean")
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Settings saved successfully",
 *         @OA\JsonContent(type="array")
 *     ),
 *     @OA\Response(
 *         response=403,
 *         description="Access denied",
 *         @OA\JsonContent(ref="#/components/schemas/Error")
 *     )
 * )
 */

/**
 * @OA\Get(
 *     path="/settings/auto-share",
 *     summary="Get auto share settings",
 *     description="Retrieves the auto share configuration",
 *     tags={"Settings"},
 *     security={{"wp_auth":{}}},
 *     @OA\Response(
 *         response=200,
 *         description="Auto share settings",
 *         @OA\JsonContent(
 *             @OA\Property(property="auto_share", type="boolean"),
 *             @OA\Property(property="allowed_post_types_options", type="array", @OA\Items(type="object")),
 *             @OA\Property(property="multiple_newlines_to_single", type="boolean"),
 *             @OA\Property(property="replace_wp_shortcodes", type="string"),
 *             @OA\Property(property="enable_auto_share_delay", type="boolean"),
 *             @OA\Property(property="auto_share_delay", type="object"),
 *             @OA\Property(property="enable_post_interval", type="boolean"),
 *             @OA\Property(property="post_interval", type="object"),
 *             @OA\Property(property="use_custom_url", type="boolean"),
 *             @OA\Property(property="custom_url", type="string"),
 *             @OA\Property(property="query_params", type="object"),
 *             @OA\Property(property="use_url_shortener", type="boolean"),
 *             @OA\Property(property="shortener_service", type="string"),
 *             @OA\Property(property="shortener_services", type="array", @OA\Items(type="object")),
 *             @OA\Property(property="url_short_access_token_bitly", type="string"),
 *             @OA\Property(property="url_short_api_url_yourls", type="string"),
 *             @OA\Property(property="url_short_api_token_yourls", type="string"),
 *             @OA\Property(property="url_short_api_url_polr", type="string"),
 *             @OA\Property(property="url_short_api_key_polr", type="string"),
 *             @OA\Property(property="url_short_api_url_shlink", type="string"),
 *             @OA\Property(property="url_short_api_key_shlink", type="string"),
 *             @OA\Property(property="url_short_domain_rebrandly", type="string"),
 *             @OA\Property(property="url_short_api_key_rebrandly", type="string"),
 *             @OA\Property(property="enable_og_tags", type="boolean"),
 *             @OA\Property(property="enable_twitter_tags", type="boolean"),
 *             @OA\Property(property="add_meta_tags_to", type="array", @OA\Items(type="string"))
 *         )
 *     ),
 *     @OA\Response(
 *         response=403,
 *         description="Access denied",
 *         @OA\JsonContent(ref="#/components/schemas/Error")
 *     )
 * )
 */

/**
 * @OA\Post(
 *     path="/settings/auto-share",
 *     summary="Save auto share settings",
 *     description="Updates the auto share configuration",
 *     tags={"Settings"},
 *     security={{"wp_auth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             @OA\Property(property="auto_share", type="boolean"),
 *             @OA\Property(property="multiple_newlines_to_single", type="boolean"),
 *             @OA\Property(property="replace_wp_shortcodes", type="string", enum={"off", "on"}),
 *             @OA\Property(property="enable_auto_share_delay", type="boolean"),
 *             @OA\Property(property="auto_share_delay", type="object"),
 *             @OA\Property(property="enable_post_interval", type="boolean"),
 *             @OA\Property(property="post_interval", type="object"),
 *             @OA\Property(property="use_custom_url", type="boolean"),
 *             @OA\Property(property="custom_url", type="string"),
 *             @OA\Property(property="query_params", type="object"),
 *             @OA\Property(property="use_url_shortener", type="boolean"),
 *             @OA\Property(property="shortener_service", type="string", enum={"tinyurl", "bitly", "yourls", "polr", "shlink", "rebrandly"}),
 *             @OA\Property(property="url_short_access_token_bitly", type="string"),
 *             @OA\Property(property="url_short_api_url_yourls", type="string"),
 *             @OA\Property(property="url_short_api_token_yourls", type="string"),
 *             @OA\Property(property="url_short_api_url_polr", type="string"),
 *             @OA\Property(property="url_short_api_key_polr", type="string"),
 *             @OA\Property(property="url_short_api_url_shlink", type="string"),
 *             @OA\Property(property="url_short_api_key_shlink", type="string"),
 *             @OA\Property(property="url_short_domain_rebrandly", type="string"),
 *             @OA\Property(property="url_short_api_key_rebrandly", type="string"),
 *             @OA\Property(property="enable_og_tags", type="boolean"),
 *             @OA\Property(property="enable_twitter_tags", type="boolean"),
 *             @OA\Property(property="add_meta_tags_to", type="array", @OA\Items(type="string"))
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Settings saved successfully",
 *         @OA\JsonContent(type="array")
 *     ),
 *     @OA\Response(
 *         response=403,
 *         description="Access denied",
 *         @OA\JsonContent(ref="#/components/schemas/Error")
 *     )
 * )
 */

/**
 * @OA\Get(
 *     path="/settings/ai",
 *     summary="Get AI settings",
 *     description="Retrieves the AI configuration for content generation",
 *     tags={"Settings", "AI"},
 *     security={{"wp_auth":{}}},
 *     @OA\Response(
 *         response=200,
 *         description="AI settings",
 *         @OA\JsonContent(
 *             @OA\Property(property="openai_api_key", type="string"),
 *             @OA\Property(property="ai_provider", type="string")
 *         )
 *     ),
 *     @OA\Response(
 *         response=403,
 *         description="Access denied",
 *         @OA\JsonContent(ref="#/components/schemas/Error")
 *     )
 * )
 */

/**
 * @OA\Post(
 *     path="/settings/ai",
 *     summary="Save AI settings",
 *     description="Updates the AI configuration for content generation",
 *     tags={"Settings", "AI"},
 *     security={{"wp_auth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             @OA\Property(property="openai_api_key", type="string"),
 *             @OA\Property(property="ai_provider", type="string")
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Settings saved successfully",
 *         @OA\JsonContent(type="array")
 *     ),
 *     @OA\Response(
 *         response=403,
 *         description="Access denied",
 *         @OA\JsonContent(ref="#/components/schemas/Error")
 *     )
 * )
 */

/**
 * @OA\Get(
 *     path="/metabox",
 *     summary="Get metabox data for a post",
 *     description="Retrieves the FS Poster metabox data for a specific WordPress post",
 *     tags={"Metabox"},
 *     security={{"wp_auth":{}}},
 *     @OA\Parameter(
 *         name="wp_post_id",
 *         in="query",
 *         required=true,
 *         description="WordPress post ID",
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Metabox data",
 *         @OA\JsonContent(
 *             type="array",
 *             @OA\Items(
 *                 @OA\Property(property="channel_id", type="integer"),
 *                 @OA\Property(property="custom_post_data", type="object")
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=400,
 *         description="Invalid post ID",
 *         @OA\JsonContent(type="array")
 *     )
 * )
 */

/**
 * @OA\Post(
 *     path="/metabox/auto-share-status",
 *     summary="Set auto share status for a post",
 *     description="Enables or disables auto sharing for a specific WordPress post",
 *     tags={"Metabox"},
 *     security={{"wp_auth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             @OA\Property(property="wp_post_id", type="integer", description="WordPress post ID"),
 *             @OA\Property(property="auto_share", type="boolean", description="Auto share status")
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Status updated successfully",
 *         @OA\JsonContent(type="array")
 *     )
 * )
 */

/**
 * @OA\Post(
 *     path="/planners",
 *     summary="Save planner",
 *     description="Creates or updates a posting planner",
 *     tags={"Planners"},
 *     security={{"wp_auth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             @OA\Property(property="id", type="integer", nullable=true, description="Planner ID (null for new)"),
 *             @OA\Property(property="title", type="string", description="Planner title"),
 *             @OA\Property(property="channels", type="array", @OA\Items(type="integer"), description="Channel IDs"),
 *             @OA\Property(property="customization_data", type="object", description="Customization data"),
 *             @OA\Property(property="share_type", type="string", enum={"interval", "weekly"}, description="Share type"),
 *             @OA\Property(property="post_type", type="string", description="WordPress post type"),
 *             @OA\Property(property="selected_posts", type="array", @OA\Items(type="integer"), description="Selected post IDs"),
 *             @OA\Property(property="post_filters", type="object", description="Post filters"),
 *             @OA\Property(property="start_at", type="integer", description="Start timestamp"),
 *             @OA\Property(property="schedule_interval", type="object", description="Schedule interval"),
 *             @OA\Property(property="weekly", type="object", description="Weekly schedule"),
 *             @OA\Property(property="sort_by", type="string", enum={"random", "old_to_new", "new_to_old"}, description="Sort order"),
 *             @OA\Property(property="repeating", type="boolean", description="Enable repeating")
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Planner saved successfully",
 *         @OA\JsonContent(
 *             @OA\Property(property="id", type="integer"),
 *             @OA\Property(property="status", type="string", example="success")
 *         )
 *     ),
 *     @OA\Response(
 *         response=400,
 *         description="Invalid request parameters",
 *         @OA\JsonContent(ref="#/components/schemas/Error")
 *     )
 * )
 */

/**
 * @OA\Post(
 *     path="/social-networks/wordpress/channel",
 *     summary="Add WordPress channel",
 *     description="Creates a new WordPress channel for cross-posting",
 *     tags={"Social Networks"},
 *     security={{"wp_auth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             @OA\Property(property="name", type="string", description="Channel name"),
 *             @OA\Property(property="site_url", type="string", description="WordPress site URL"),
 *             @OA\Property(property="username", type="string", description="WordPress username"),
 *             @OA\Property(property="password", type="string", description="WordPress password"),
 *             @OA\Property(property="proxy", type="string", nullable=true, description="Proxy configuration")
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Channel added successfully",
 *         @OA\JsonContent(
 *             @OA\Property(property="id", type="integer"),
 *             @OA\Property(property="status", type="string", example="success")
 *         )
 *     ),
 *     @OA\Response(
 *         response=400,
 *         description="Invalid request parameters",
 *         @OA\JsonContent(ref="#/components/schemas/Error")
 *     )
 * )
 */

/**
 * @OA\Get(
 *     path="/social-networks/wordpress/settings",
 *     summary="Get WordPress settings",
 *     description="Retrieves the WordPress integration settings",
 *     tags={"Social Networks"},
 *     security={{"wp_auth":{}}},
 *     @OA\Response(
 *         response=200,
 *         description="WordPress settings",
 *         @OA\JsonContent(
 *             @OA\Property(property="media_type_to_upload", type="string"),
 *             @OA\Property(property="ai_template_id", type="integer", nullable=true)
 *         )
 *     )
 * )
 */

/**
 * @OA\Post(
 *     path="/social-networks/wordpress/settings",
 *     summary="Save WordPress settings",
 *     description="Updates the WordPress integration settings",
 *     tags={"Social Networks"},
 *     security={{"wp_auth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             @OA\Property(property="media_type_to_upload", type="string", enum={"featured_image", "ai_image"}),
 *             @OA\Property(property="ai_template_id", type="integer", nullable=true)
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Settings saved successfully",
 *         @OA\JsonContent(
 *             @OA\Property(property="status", type="string", example="success")
 *         )
 *     ),
 *     @OA\Response(
 *         response=400,
 *         description="Invalid request parameters",
 *         @OA\JsonContent(ref="#/components/schemas/Error")
 *     )
 * )
 */

/**
 * @OA\Get(
 *     path="/post-types",
 *     summary="Get post types",
 *     description="Retrieves available WordPress post types",
 *     tags={"General"},
 *     security={{"wp_auth":{}}},
 *     @OA\Response(
 *         response=200,
 *         description="List of post types",
 *         @OA\JsonContent(type="array", @OA\Items(type="object"))
 *     )
 * )
 */

/**
 * @OA\Get(
 *     path="/taxonomies",
 *     summary="Get taxonomies",
 *     description="Retrieves available WordPress taxonomies",
 *     tags={"General"},
 *     security={{"wp_auth":{}}},
 *     @OA\Response(
 *         response=200,
 *         description="List of taxonomies",
 *         @OA\JsonContent(type="array", @OA\Items(type="object"))
 *     )
 * )
 */

/**
 * @OA\Get(
 *     path="/terms",
 *     summary="Get terms",
 *     description="Retrieves terms for a specific taxonomy",
 *     tags={"General"},
 *     security={{"wp_auth":{}}},
 *     @OA\Parameter(
 *         name="taxonomy",
 *         in="query",
 *         required=true,
 *         description="Taxonomy slug",
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="List of terms",
 *         @OA\JsonContent(type="array", @OA\Items(type="object"))
 *     )
 * )
 */

/**
 * @OA\Post(
 *     path="/settings/logger/start",
 *     summary="Start logger",
 *     description="Starts the plugin logger for debugging",
 *     tags={"Settings"},
 *     security={{"wp_auth":{}}},
 *     @OA\Response(
 *         response=200,
 *         description="Logger started successfully",
 *         @OA\JsonContent(
 *             @OA\Property(property="started_at", type="integer")
 *         )
 *     ),
 *     @OA\Response(
 *         response=403,
 *         description="Access denied or demo version",
 *         @OA\JsonContent(ref="#/components/schemas/Error")
 *     )
 * )
 */

/**
 * @OA\Post(
 *     path="/settings/logger/stop",
 *     summary="Stop logger",
 *     description="Stops the plugin logger",
 *     tags={"Settings"},
 *     security={{"wp_auth":{}}},
 *     @OA\Response(
 *         response=200,
 *         description="Logger stopped successfully",
 *         @OA\JsonContent(type="array")
 *     ),
 *     @OA\Response(
 *         response=403,
 *         description="Access denied or demo version",
 *         @OA\JsonContent(ref="#/components/schemas/Error")
 *     )
 * )
 */

// Generate the OpenAPI specification
$openapi = \OpenApi\Generator::scan([__FILE__]);
header('Content-Type: application/json');
echo $openapi->toJson(); 