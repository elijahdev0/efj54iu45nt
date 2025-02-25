# FSPoster AI App Bootstrap Documentation

[Linked Table of Contents](#linked-table-of-contents)

## Linked Table of Contents

* [1. Introduction](#1-introduction)
* [2. `Bootstrap::init()` Method](#2-bootstrapinit-method)
    * [2.1 Route Registration](#21-route-registration)
    * [2.2 Action and Filter Registration](#22-action-and-filter-registration)


## 1. Introduction

This document details the functionality of the `FSPoster\App\AI\App\Bootstrap` class, specifically the `init()` method, which is responsible for initializing the AI application's routes, actions, and filters within the FSPoster framework.


## 2. `Bootstrap::init()` Method

The `Bootstrap::init()` method is the core initialization function for the AI application.  It sets up the necessary routes for handling AI-related requests and registers various actions and filters to integrate the AI functionality with the FSPoster system.

### 2.1 Route Registration

This section uses the `FSPoster\App\Providers\Core\Route` class to define RESTful API endpoints for managing AI templates.  The routes are defined using the `Route::post()` and `Route::get()` methods.

| Method | Route                      | Callback                                      | Description                                                                |
|--------|-----------------------------|----------------------------------------------|----------------------------------------------------------------------------|
| `POST`  | `/ai/templates`            | `[Controller::class, 'saveTemplate']`       | Saves a new AI template.                                                  |
| `GET`   | `/ai/templates/{id}`       | `[Controller::class, 'get']`               | Retrieves a specific AI template by ID.                                   |
| `GET`   | `/ai/templates`            | `[Controller::class, 'listTemplates']`      | Retrieves a list of all AI templates.                                      |
| `POST`  | `/ai/templates/delete`     | `[Controller::class, 'deleteTemplates']`    | Deletes AI templates (the exact criteria for deletion is not specified here). |


### 2.2 Action and Filter Registration

This section utilizes the `add_action()` and `add_filter()` WordPress functions to integrate the AI functionality into various points within the FSPoster plugin.  These hooks allow the AI component to interact with other parts of the system, such as settings management, media uploading, and shortcode processing.

The following table outlines the registered actions and filters:

| Function      | Hook Name                             | Callback                                   | Priority | Arguments | Description                                                                                                    |
|---------------|--------------------------------------|--------------------------------------------|----------|-----------|----------------------------------------------------------------------------------------------------------------|
| `add_action` | `fsp_save_settings`                   | `[Listener::class, 'saveSocialNetworkSettings']` | 10       | 2         | Saves social network settings related to AI.                                                                    |
| `add_filter` | `fsp_get_settings`                    | `[Listener::class, 'getSocialNetworkSettings']` | 10       | 2         | Retrieves social network settings related to AI.                                                                  |
| `add_filter` | `fsp_channel_custom_post_data`       | `[Listener::class, 'getCustomPostData']`     | 99       | 3         | Adds custom post data for AI-related channels (specific data added is not described here).                     |
| `add_filter` | `fsp_add_short_code`                  | `[Listener::class, 'addAIToShortCodes']`     |          |           | Adds AI-related shortcodes.                                                                                  |
| `add_filter` | `fsp_schedule_media_list_to_upload` | `[Listener::class, 'addAIToMediaList']`      | 10       | 2         | Adds AI-generated media to the upload queue.                                                                |
| `add_filter` | `fsp_ai_save_template`                | `[Listener::class, 'save']`                | 10       | 2         | Saves an AI template (likely called after template creation/update).                                        |
| `add_filter` | `fsp_ai_text_generator`              | `[Listener::class, 'ShortCodeAITextGenerator']`| 10       | 4         | Generates text using AI based on shortcode parameters. The algorithm used is not specified in this document. |
| `add_filter` | `fsp_ai_image_generator`             | `[Listener::class, 'AIImageGenerator']`    | 10       | 4         | Generates images using AI based on provided parameters. The algorithm used is not specified in this document. |


This comprehensive initialization ensures that the AI features are seamlessly integrated into the FSPoster application.  Further details on the individual callback functions within the `Controller` and `Listener` classes would require separate documentation.
