<?php

namespace Drupal\external_entities\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a field mapper annotation object.
 *
 * @see \Drupal\external_entities\StorageClient\FieldMapperManager
 * @see plugin_api
 *
 * @Annotation
 */
class FieldMapper extends Plugin {

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
