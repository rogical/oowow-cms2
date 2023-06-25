<?php

namespace Drupal\external_entities\FieldMapper;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;

/**
 * Interface for field mapper plugins.
 *
 * Field mappers control how raw data is mapped into and out of entity objects.
 */
interface FieldMapperInterface extends PluginInspectionInterface, ContainerFactoryPluginInterface {

  /**
   * Returns the administrative label for this field mapper plugin.
   *
   * @return string
   *   The field mappers administrative label.
   */
  public function getLabel();

  /**
   * Returns the administrative description for this field mapper plugin.
   *
   * @return string
   *   The field mappers administrative description.
   */
  public function getDescription();

  /**
   * Extract the unique ID from the raw data.
   *
   * @param array $raw_data
   *   The raw data from the external service.
   *
   * @return string|int|null
   *   The unique ID the item is represented by or NULL if none could be found.
   */
  public function extractIdFromRawData(array $raw_data);

  /**
   * Extracts entity values from raw data.
   *
   * @param array $raw_data
   *   The raw data as received by the external service.
   *
   * @return array
   *   An array of values to set on the external entity object, keyed by
   *   property name. See an example of the structure below. This is directly
   *   passed on to the entity object constructor.
   *
   * @see \Drupal\Core\Entity\EntityBase::__construct()
   *
   * @code
   *   <?php
   *     [
   *       'field1' => [
   *         0 => [
   *           'property1' => ...,
   *           'property2' => ...,
   *           ...
   *         ],
   *         1 => [
   *           'property1' => ...,
   *           'property2' => ...,
   *           ...
   *         ],
   *     ],
   *     [
   *       'field2' => [
   *         0 => [
   *           'property1' => ...,
   *           'property2' => ...,
   *           ...
   *         ],
   *         1 => [
   *           'property1' => ...,
   *           'property2' => ...,
   *           ...
   *         ],
   *     ],
   *   ?>
   * @endcode
   */
  public function extractEntityValuesFromRawData(array $raw_data);

  /**
   * Creates raw data from the given entity values.
   *
   * @param array $entity_values
   *   An associative array of entity values to be mapped to raw data. This
   *   value must have a structure similar to the one below.
   *
   * @code
   *   <?php
   *     [
   *       'field1' => [
   *         0 => [
   *           'property1' => ...,
   *           'property2' => ...,
   *           ...
   *         ],
   *         1 => [
   *           'property1' => ...,
   *           'property2' => ...,
   *           ...
   *         ],
   *     ],
   *     [
   *       'field2' => [
   *         0 => [
   *           'property1' => ...,
   *           'property2' => ...,
   *           ...
   *         ],
   *         1 => [
   *           'property1' => ...,
   *           'property2' => ...,
   *           ...
   *         ],
   *     ],
   *   ?>
   * @endcode
   *
   * @return array
   *   The raw data ready to be sent to the external service.
   */
  public function createRawDataFromEntityValues(array $entity_values);

}
