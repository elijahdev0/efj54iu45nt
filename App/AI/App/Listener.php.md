# FSPoster AI Listener Class Documentation

## Table of Contents

* [1. Introduction](#1-introduction)
* [2. Class `Listener`](#2-class-listener)
    * [2.1 `saveSocialNetworkSettings()`](#21-savesocialnetworksettings)
    * [2.2 `getSocialNetworkSettings()`](#22-getsocialnetworksettings)
    * [2.3 `getCustomPostData()`](#23-getcustompostdata)
    * [2.4 `addAIToShortCodes()`](#24-addaitoshortcodes)
    * [2.5 `aiTextGenerator()`](#25-aitextgenerator)
        * [2.5.1 Algorithm](#251-algorithm)
    * [2.6 `addAIToMediaList()`](#26-addaitomedialist)
        * [2.6.1 Algorithm](#261-algorithm)
    * [2.7 `save()`](#27-save)
        * [2.7.1 Input Validation](#271-input-validation)
    * [2.8 `ShortCodeAITextGenerator()`](#28-shortcodeaitextgenerator)
    * [2.9 `AIImageGenerator()`](#29-aiimagegenerator)


<br>

## 1. Introduction

This document provides internal code documentation for the `FSPoster\App\AI\App\Listener` class.  This class handles various aspects of AI integration within the FSPoster application, including saving settings, retrieving data, generating AI text and images, and integrating with shortcodes.


<br>

## 2. Class `Listener`

The `Listener` class contains several static methods that act as event listeners or utility functions for AI-related tasks.


<br>

### 2.1 `saveSocialNetworkSettings()`

This function saves AI template ID settings for a given social network.

| Parameter | Type          | Description                                      |
|-----------|---------------|--------------------------------------------------|
| `$request` | `RestRequest` | The REST request object containing parameters.     |
| `$socialNetwork` | `string`      | The name of the social network.                 |


<br>

### 2.2 `getSocialNetworkSettings()`

This function retrieves AI template ID settings for a given social network.

| Parameter | Type     | Description                                      |
|-----------|----------|--------------------------------------------------|
| `$data`    | `array`  | The data array to add AI template ID to.           |
| `$socialNetwork` | `string` | The name of the social network.                 |


<br>

### 2.3 `getCustomPostData()`

This function adds the AI template ID to custom post data if the upload media type is 'ai_image'.

| Parameter          | Type             | Description                                               |
|----------------------|------------------|-----------------------------------------------------------|
| `$customPostData`   | `array`          | Array of custom post data.                               |
| `$channel`          | `Collection`     | Channel object (likely containing social network info).   |
| `$socialNetwork`    | `string`         | The name of the social network.                            |


<br>

### 2.4 `addAIToShortCodes()`

This function adds the `ai_text_generator` shortcode to the list of available shortcodes.

| Parameter      | Type        | Description                               |
|-----------------|-------------|-------------------------------------------|
| `$shortCodes` | `array`     | Array of existing shortcodes.           |


<br>

### 2.5 `aiTextGenerator()`

This function generates AI text using a specified template.

| Parameter      | Type                | Description                                          |
|-----------------|---------------------|------------------------------------------------------|
| `$scheduleObj` | `ScheduleObject`    | Object representing the scheduling information.       |
| `$props`        | `array`             | Array of properties, including the template ID.        |

#### 2.5.1 Algorithm

1. **Read-only Mode Check:** If the schedule is in read-only mode, a placeholder text is returned.
2. **Template ID Check:** If the template ID is missing, an empty string is returned.
3. **Template Retrieval:** The AI template is retrieved using the provided ID. If not found, an empty string is returned.
4. **Prompt Preparation:** The prompt is retrieved from the template and any occurrences of `{ai_text_generator` are replaced with `{!ai_text_generator` to prevent infinite loops. Shortcodes within the prompt are then replaced using `$scheduleObj->replaceShortCodes()`.
5. **Cache Check:** The function checks the `AILogs` database table for a cached successful response using the schedule ID, template ID, AI model, and rendered prompt. If a cached response exists, it is returned.
6. **AI Text Generation:** If no cached response is found, the `apply_filters()` hook `fsp_ai_text_generator` is used to generate text. This allows external plugins/functions to handle the AI text generation.  The default behavior is handled by the `Api` class.
7. **Log Insertion:**  The response (successful or not) is logged in the `AILogs` database table.
8. **Fallback Text:** If the AI request fails, the template's fallback text is used, shortcodes are replaced and returned.
9. **Response Return:** If the AI request is successful, the generated AI text is returned.


<br>

### 2.6 `addAIToMediaList()`

This function adds an AI-generated image to the media list for upload.

| Parameter      | Type                | Description                                          |
|-----------------|---------------------|------------------------------------------------------|
| `$mediaListToUpload` | `array`             | Array of media to upload.                             |
| `$scheduleObj` | `ScheduleObject`    | Object representing the scheduling information.       |

#### 2.6.1 Algorithm

1. **Read-only Mode and Data Check:** Checks if the schedule is not in read-only mode, if AI image upload is enabled, and if an AI template ID is set.
2. **Template Retrieval:** Retrieves the AI template using the template ID. If the template is not found, the original media list is returned.
3. **Prompt Preparation:** Replaces shortcodes within the template prompt using `$scheduleObj->replaceShortCodes()`.
4. **Cache Check:** Checks `AILogs` for a cached image. If found, the image data is returned.
5. **AI Image Generation:** If not cached, `apply_filters()` hook `fsp_ai_image_generator` is used. The default behavior utilizes the `Api` class.
6. **Log Insertion:** Logs the AI image generation attempt (successful or not) in `AILogs`.
7. **Response Return:** Returns the AI-generated image data (if successful) or the original media list (if unsuccessful).


<br>

### 2.7 `save()`

This function validates and sanitizes AI template data before saving.

| Parameter | Type     | Description                               |
|-----------|----------|-------------------------------------------|
| `$template` | `array`  | The AI template data.                    |
| `$provider` | `string` | The AI provider (e.g., 'openai').        |

#### 2.7.1 Input Validation

This function performs several validation checks:

* **Provider Check:** It verifies if the provider is 'openai'. If not, the template is returned without modification.
* **Model Type and AI Model Matching:** It ensures that the `type` and `ai_model` fields are compatible.  For example, 'gpt' models must have `type` set to 'text', and 'dall-e-3' must have `type` set to 'image'.
* **Temperature Validation (for GPT models):**  If the `ai_model` contains 'gpt', it checks if the `temperature` config parameter is set, numeric, and between 0 and 2.
* **DALL-E 3 Validation:** If the `ai_model` is 'dall-e-3', it validates that the `size` and `style` config parameters are set to valid values.


<br>

### 2.8 `ShortCodeAITextGenerator()`

This function acts as a callback for the `fsp_ai_text_generator` filter, handling OpenAI text generation.  It fetches the OpenAI API key and utilizes the `Api` class to generate the text.


| Parameter          | Type                     | Description                                                  |
|----------------------|--------------------------|--------------------------------------------------------------|
| `$aiResponse`       | `AITextGeneratorResponse` | The response object.                                           |
| `$provider`         | `string`                 | The AI provider (should be 'openai').                       |
| `$renderedPrompt`   | `string`                 | The rendered prompt for AI generation.                       |
| `$template`         | `Collection`             | The AI template object.                                        |


<br>

### 2.9 `AIImageGenerator()`

This function acts as a callback for the `fsp_ai_image_generator` filter, handling OpenAI image generation. It fetches the OpenAI API key and uses the `Api` class to generate the image.


| Parameter          | Type                     | Description                                                  |
|----------------------|--------------------------|--------------------------------------------------------------|
| `$aiResponse`       | `AIImageGeneratorResponse` | The response object.                                           |
| `$provider`         | `string`                 | The AI provider (should be 'openai').                       |
| `$renderedPrompt`   | `string`                 | The rendered prompt for AI generation.                       |
| `$template`         | `Collection`             | The AI template object.                                        |

