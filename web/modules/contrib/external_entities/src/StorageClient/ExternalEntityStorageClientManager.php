<?php

namespace Drupal\external_entities\StorageClient;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

/**
 * ExternalEntityStorageClient plugin manager.
 */
class ExternalEntityStorageClientManager extends DefaultPluginManager {

  /**
   * Constructs an ExternalEntityStorageClientManager object.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct(
      'Plugin/ExternalEntities/StorageClient',
      $namespaces,
      $module_handler,
      'Drupal\external_entities\StorageClient\ExternalEntityStorageClientInterface',
      'Drupal\external_entities\Annotation\ExternalEntityStorageClient'
    );
    $this->alterInfo('external_entity_storage_client_info');
    $this->setCacheBackend($cache_backend, 'external_entity_storage_client', ['external_entity_storage_client']);
  }

}
