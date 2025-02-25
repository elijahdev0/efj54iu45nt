# FSPoster AI App Controller Documentation

[TOC]

## 1. Introduction

This document provides internal code documentation for the `FSPoster\App\AI\App\Controller` class.  This class handles requests related to AI templates, providing functionalities for saving, retrieving, listing, and deleting templates.

## 2. Class: `Controller`

This class contains static methods for managing AI templates.  All methods interact with the `AITemplate` model to perform database operations.  Error handling is implemented using exceptions which are thrown when necessary.

## 3. Methods

### 3.1 `saveTemplate(RestRequest $request): array`

This method saves or updates an AI template.

**Parameters:**

| Parameter       | Type             | Description                                                                     | Required | Default Value | Validation                                     |
|-----------------|------------------|---------------------------------------------------------------------------------|----------|----------------|-------------------------------------------------|
| `$request`      | `RestRequest`    | Object containing request parameters.                                          | Yes       |                |                                                 |
| `id`            | string           | ID of the template (for updates).                                              | No        | ""             | String                                         |
| `title`         | string           | Title of the template.                                                        | Yes       |                | String, required, localized error message.   |
| `provider`      | string           | Name of the AI provider (e.g., "OpenAI").                                     | Yes       |                | String, required, localized error message.   |
| `prompt`        | string           | Prompt for the AI model.                                                       | Yes       |                | String, required, localized error message.   |
| `fallbackText`  | string           | Fallback text if the AI request fails.                                         | No        | ""             | String                                         |
| `model`         | string           | Specific AI model to use.                                                      | Yes       |                | String, required, localized error message.   |
| `type`          | string           | Type of template ('image' or 'text').                                         | Yes       |                | String, required, localized error message, allowed values: ['image', 'text'] |
| `config`        | array            | Configuration settings for the template.                                        | No        | []             | Array                                          |


**Algorithm:**

1. **Retrieve parameters:** The method retrieves parameters from the `$request` object, performing validation and throwing exceptions if required parameters are missing or invalid.
2. **Apply filter:** Uses `apply_filters` hook `fsp_ai_save_template` allowing for modification of the template data before saving.
3. **Save or Update:** If the template `id` is empty, it inserts a new template using `AITemplate::insert()`. Otherwise, it updates the existing template using `AITemplate::where()->update()`.  The `config` array is JSON encoded before saving.
4. **Return:** Returns an empty array.


**Error Handling:**

The method throws exceptions if required parameters are missing or invalid.


### 3.2 `get(RestRequest $request): array`

This method retrieves a single AI template by its ID.

**Parameters:**

| Parameter       | Type             | Description                                    | Required | Default Value | Validation     |
|-----------------|------------------|------------------------------------------------|----------|----------------|-----------------|
| `$request`      | `RestRequest`    | Object containing request parameters.             | Yes       |                |                 |
| `id`            | integer          | ID of the template to retrieve.                 | No        | 0              | Integer         |


**Algorithm:**

1. **Retrieve ID:** Gets the template ID from the request parameters.
2. **Retrieve Template:** Retrieves the template from the database using `AITemplate::get($id)`.
3. **Error Handling:** If the template is not found, it throws an exception.
4. **Format Response:** Decodes the JSON encoded `config` and removes `created_by` and `blog_id` from the response.
5. **Return:** Returns an array containing the template data.

**Error Handling:** Throws an exception if the template is not found.


### 3.3 `listTemplates(RestRequest $request): array`

This method retrieves a list of AI templates, optionally filtered by type.

**Parameters:**

| Parameter | Type             | Description                                           | Required | Default Value | Validation                                   |
|-----------|------------------|-------------------------------------------------------|----------|----------------|-----------------------------------------------|
| `$request`| `RestRequest`    | Object containing request parameters.                     | Yes       |                |                                               |
| `type`    | string           | Optional filter for template type ('image' or 'text'). | No        | ""             | String, allowed values: ['image', 'text']     |


**Algorithm:**

1. **Retrieve type:** Gets the optional `type` parameter from the request.
2. **Fetch Templates:** Fetches templates from the database using `AITemplate::fetchAll()`.  If a `type` is provided, it filters the query accordingly.
3. **Format Response:**  Iterates over the fetched templates, converts the `config` from JSON to an array, casts the `id` to integer, and removes `blog_id` and `created_by` from each template before returning.
4. **Return:** Returns an array containing the list of templates.

**Error Handling:** No explicit error handling, but potential database errors could occur.


### 3.4 `deleteTemplates(RestRequest $request): array`

This method deletes multiple AI templates based on provided IDs.

**Parameters:**

| Parameter       | Type             | Description                                                | Required | Default Value | Validation                                      |
|-----------------|------------------|------------------------------------------------------------|----------|----------------|--------------------------------------------------|
| `$request`      | `RestRequest`    | Object containing request parameters.                        | Yes       |                |                                                  |
| `ids`           | array            | Array of IDs representing templates to be deleted.           | Yes       |                | Array, required, localized error message.          |


**Algorithm:**

1. **Retrieve IDs:** Gets the array of IDs from the request parameters.
2. **Delete Templates:** Deletes the templates from the database using `AITemplate::where()->delete()`.
3. **Return:** Returns an empty array.

**Error Handling:** The method throws an exception if the `ids` parameter is missing or invalid.


## 4.  Dependencies

* `FSPoster\App\Models\AITemplate`:  Model for interacting with the AI templates database table.
* `FSPoster\App\Providers\Core\RestRequest`:  Handles request parameter retrieval and validation.
* `FSPoster\App\Providers\Helpers\Helper`: Helper functions, specifically `getBlogId()`.


## 5.  Future Considerations

The `listTemplates` method currently doesn't include pagination.  Consider adding pagination in the future for performance optimization with large datasets.  Adding more robust error handling for database operations might also be beneficial.
