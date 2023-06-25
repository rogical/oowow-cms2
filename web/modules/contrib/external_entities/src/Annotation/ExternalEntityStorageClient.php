<?php

namespace Drupal\external_entities\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines an external entity storage client annotation object.
 *
 * @see \Drupal\external_entities\StorageClient\ExternalEntityStorageClientManager
 * @see plugin_api
 *
 * @Annotation
 */
class ExternalEntityStorageClient extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The human-friendly name of the plugin.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $label;

  /**
   * A description of the plugin.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $description;

}
