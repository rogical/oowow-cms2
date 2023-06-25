<?php

namespace Drupal\external_entities\FieldMapper;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

/**
 * Plugin type manager for field mappers.
 *
 * @see \Drupal\external_entities\FieldMapper\FieldMapperInterface
 */
class FieldMapperManager extends DefaultPluginManager {

  /**
   * Constructs a FieldMapperManager object.
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
      'Plugin/ExternalEntities/FieldMapper',
      $namespaces,
      $module_handler,
      '\Drupal\external_entities\FieldMapper\FieldMapperInterface',
      'Drupal\external_entities\Annotation\FieldMapper'
    );

    $this->alterInfo('external_entities_field_mapper_info');
    $this->setCacheBackend($cache_backend, 'external_entities_field_mapper', ['external_entities_field_mapper']);
  }

}
