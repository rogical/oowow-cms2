<?php

namespace Drupal\external_entities_test\Controller;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * An simple JSON controller.
 */
class ExternalEntitiesJsonController extends ControllerBase {

  /**
   * Get data.
   */
  protected function getData() {
    $data = $this->state()->get('external_entities_test.data');
    if (empty($data)) {
      $data = $this->setData([
        '2596b1ba-43bb-4440-9f0c-f1974f733336' => [
          'uuid' => '2596b1ba-43bb-4440-9f0c-f1974f733336',
          'title' => 'Simple title 1',
          'short_text' => 'Just a short string',
          'rich_text' => '<h2>Some HTML tags</h2>',
          'rich_text_2' => '<h2>Other HTML tags</h2>',
          'status' => TRUE,
          'refs' => [
            '2596b1ba-43bb-4440-9f0c-f1974f733310',
            '2596b1ba-43bb-4440-9f0c-f1974f733311'
          ],
        ],
        '2596b1ba-43bb-4440-9f0c-f1974f733337' => [
          'uuid' => '2596b1ba-43bb-4440-9f0c-f1974f733337',
          'title' => 'Simple title 2',
          'short_text' => 'Just another short string',
          'status' => FALSE,
        ],
      ]);
    }

    return $data;
  }

  /**
   * Set data.
   */
  protected function setData($data) {
    $this->state()->set('external_entities_test.data', $data);
    return $data;
  }

  /**
   * Reference data.
   */
  protected $reference_data = [
    '2596b1ba-43bb-4440-9f0c-f1974f733310' => [
      'uuid' => '2596b1ba-43bb-4440-9f0c-f1974f733310',
      'label' => 'Term 1',
    ],
    '2596b1ba-43bb-4440-9f0c-f1974f733311' => [
      'uuid' => '2596b1ba-43bb-4440-9f0c-f1974f733311',
      'label' => 'Term 2',
    ],
  ];

  /**
   * Returns a simple json file.
   */
  public function simple(Request $request) {
    // Set the default cache.
    $cache = new CacheableMetadata();
    $cache->addCacheTags([
      'external_entities_test',
      'external_entities_test_simple',
    ]);

    // Add the cache contexts for the request parameters.
    $cache->addCacheContexts([
      'url',
      'url.query_args',
    ]);

    $data = $this->getData();
    $response = new CacheableJsonResponse(array_values($data), 200);
    $response->addCacheableDependency($cache);
    return $response;
  }

  /**
   * Returns a simple json file.
   */
  public function simpleGet($uuid) {
    $data = $this->getData();
    if (!isset($data[$uuid])) {
      return new JsonResponse([], 404);
    }

    // Set the default cache.
    $cache = new CacheableMetadata();
    $cache->addCacheTags([
      'external_entities_test',
      'external_entities_test_simple',
      'external_entities_test_simple:' . $uuid,
    ]);

    // Add the cache contexts for the request parameters.
    $cache->addCacheContexts([
      'url',
    ]);

    $response = new CacheableJsonResponse($data[$uuid], 200);
    $response->addCacheableDependency($cache);
    return $response;
  }

  /**
   * Returns a simple json file.
   */
  public function simpleSet($uuid, Request $request) {
    $data = $this->getData();
    if (!isset($data[$uuid])) {
      return new JsonResponse([], 404);
    }

    $params = $this->getRequestContent($request);
    $data[$uuid] = array_merge($data[$uuid], $params);
    $this->setData($data);

    // Invalidate cache.
    Cache::invalidateTags([
      'external_entities_test',
      'external_entities_test_simple',
      'external_entities_test_simple:' . $uuid,
    ]);

    $response = new JsonResponse($data[$uuid], 200);
    return $response;
  }

  /**
   * Returns a ref json file.
   */
  public function ref(Request $request) {
    // Set the default cache.
    $cache = new CacheableMetadata();
    $cache->addCacheTags([
      'external_entities_test',
      'external_entities_test_ref',
    ]);

    // Add the cache contexts for the request parameters.
    $cache->addCacheContexts([
      'url',
      'url.query_args',
    ]);

    $response = new CacheableJsonResponse(array_values($this->reference_data), 200);
    $response->addCacheableDependency($cache);
    return $response;
  }

  /**
   * Returns a ref json file.
   */
  public function refGet(Request $request, $uuid) {
    if (!isset($this->reference_data[$uuid])) {
      return new JsonResponse([], 404);
    }

    // Set the default cache.
    $cache = new CacheableMetadata();
    $cache->addCacheTags([
      'external_entities_test',
      'external_entities_test_ref',
      'external_entities_test_ref:' . $uuid,
    ]);

    // Add the cache contexts for the request parameters.
    $cache->addCacheContexts([
      'url',
    ]);

    $response = new CacheableJsonResponse($this->reference_data[$uuid], 200);
    $response->addCacheableDependency($cache);
    return $response;
  }

  /**
   * Get the request content.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   API Request.
   *
   * @return array
   *   Request content.
   */
  public function getRequestContent(Request $request) {
    parse_str($request->getContent(), $content);
    return $content;
  }

}
