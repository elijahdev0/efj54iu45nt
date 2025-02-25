# FSPoster OpenAI API Client Documentation

## Table of Contents

* [1. Introduction](#1-introduction)
* [2. Class `Api`](#2-class-api)
    * [2.1 Constructor `__construct()`](#21-constructor-construct)
    * [2.2 Method `getClient()`](#22-method-getclient)
    * [2.3 Method `generateText()`](#23-method-generatetext)
    * [2.4 Method `generateImage()`](#24-method-generateimage)


## 1. Introduction

This document provides internal code documentation for the `FSPoster\App\AI\Api\OpenAi\Api` class, a PHP client for interacting with the OpenAI API.  This class handles both text and image generation requests.

## 2. Class `Api`

This class acts as an interface to the OpenAI API. It manages API key authentication and handles requests for text and image generation.

### 2.1 Constructor `__construct()`

```php
public function __construct(string $key)
{
    $this->key = $key;
}
```

This constructor initializes the API key.  The `$key` parameter is a string containing the OpenAI API key.  This key is stored in the `$this->key` property for use in subsequent requests.

### 2.2 Method `getClient()`

```php
private function getClient(): GuzzleClient
{
    return new GuzzleClient([
        'base_uri' => self::OPEN_AI_API_URL,
        'headers' => [
            'Authorization' => 'Bearer ' . $this->key,
        ]
    ]);
}
```

This method creates and returns a `GuzzleClient` instance configured to communicate with the OpenAI API. It sets the base URI to `https://api.openai.com/` and includes the API key in the Authorization header using Bearer token authentication.

### 2.3 Method `generateText()`

```php
/** @param Collection|AITemplate $template */
public function generateText(string $renderedPrompt, Collection $template): AITextGeneratorResponse
{
    // Construct the request body
    $body = [
        'model' => $template->ai_model,
        'messages' => [
            [
                'role' => 'user',
                'content' => $renderedPrompt
            ]
        ],
        'temperature' => $template->config_obj->temperature
    ];

    // Initialize the response object
    $aiResponse = new AITextGeneratorResponse();
    // ... (setting response properties) ...

    try {
        // Send the request to OpenAI API
        $response = (string)$this->getClient()->post('v1/chat/completions', ['json' => $body])->getBody();

        // ... (processing and setting response properties) ...
        
        // Decode JSON response and check for success
        $response = json_decode($response, true);
        if (!isset($response['choices']['0']['message']['content'])) {
            $aiResponse->status = 'fail';
        } else {
            $aiResponse->status = 'success';
            $aiResponse->response = trim($response['choices']['0']['message']['content'], '"');
            $aiResponse->aiGeneratedText = trim($response['choices']['0']['message']['content'], '"');
        }
    } catch (Exception $e) {
        // Handle exceptions
        $aiResponse->rawResponse = $e->getMessage();
        $aiResponse->status = 'fail';
    }

    return $aiResponse;
}
```

This method generates text using the OpenAI API.  It takes a rendered prompt and an `AITemplate` object as input.

The algorithm works as follows:

1. **Construct the request body:** It builds a JSON payload containing the model to use (`$template->ai_model`), the user's prompt (`$renderedPrompt`), and the temperature parameter (`$template->config_obj->temperature`) which controls the randomness of the generated text.

2. **Send the request:**  It uses the `GuzzleClient` to send a POST request to `/v1/chat/completions` endpoint of the OpenAI API with the constructed JSON body.

3. **Process the response:** The response is decoded from JSON.  The method checks if a response content exists in the expected structure (`choices[0][message][content]`). If the response is successful, the content is extracted, trimmed of any surrounding double quotes, and stored. If an error occurs, the status is set to 'fail', and the exception message is stored.

4. **Return the response:**  An `AITextGeneratorResponse` object containing the raw response, status, and generated text is returned.


### 2.4 Method `generateImage()`

```php
/** @param Collection|AITemplate $template */
public function generateImage(string $renderedPrompt, Collection $template): AIImageGeneratorResponse
{
    // Construct the request body
    $body = [
        'model' => $template->ai_model,
        'prompt' => $renderedPrompt,
        'n' => 1,
        'size' => $template->config_obj->size,
        'style' => $template->config_obj->style
    ];

    // Initialize the response object
    $aiResponse = new AIImageGeneratorResponse();
    // ... (setting response properties) ...

    try {
        // Send the request to OpenAI API
        $response = (string)$this->getClient()->post('v1/images/generations', ['json' => $body])->getBody();

        // ... (processing and setting response properties) ...

        // Decode JSON response and check for success
        $response = json_decode($response, true);
        if (!isset($response['data'][0]['url'])) {
            $aiResponse->status = 'fail';
        } else {
            // Generate and save image attachment
            $id = AIHelper::createImageAttachmentFromUrl($template->provider, $response['data'][0]['url']);
            $aiResponse->status = 'success';
            $aiResponse->response = $id;
            $aiResponse->attachmentId = $id;
        }
    } catch (Exception $e) {
        // Handle exceptions
        $aiResponse->rawResponse = $e->getMessage();
        $aiResponse->status = 'fail';
    }

    return $aiResponse;
}
```

This method generates an image using the OpenAI API. It takes a rendered prompt and an `AITemplate` object as input.

The algorithm is similar to `generateText()`:

1. **Construct the request body:** A JSON payload is created containing the model, prompt, number of images (n=1), size, and style from the `AITemplate` object.

2. **Send the request:** A POST request is sent to the `/v1/images/generations` endpoint.

3. **Process the response:**  The JSON response is decoded. It checks if the image URL (`data[0][url]`) is present. If successful,  `AIHelper::createImageAttachmentFromUrl()` is called to create and save the image attachment, and the attachment ID is stored in the response.

4. **Return the response:** An `AIImageGeneratorResponse` object is returned, containing the status, raw response, and the attachment ID if successful.

