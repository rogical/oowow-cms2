<?php

namespace Drupal\external_entities\Event;

use Symfony\Contracts\EventDispatcher\Event;

/**
 * Defines an external entity raw data mapping event.
 */
class ExternalEntityMapRawDataEvent extends Event {

  /**
   * The raw data.
   *
   * @var array
   */
  protected $rawData;

  /**
   * The entity values.
   *
   * @var array
   */
  protected $entityValues;

  /**
   * Constructs a map raw data event object.
   *
   * @param array $raw_data
   *   The raw data being mapped.
   * @param array $entity_values
   *   The mapped entity values.
   */
  public function __construct(array $raw_data, array $entity_values) {
    $this->rawData = $raw_data;
    $this->entityValues = $entity_values;
  }

  /**
   * Get the raw data being mapped.
   *
   * @return array
   *   The raw data.
   */
  public function getRawData() {
    return $this->rawData;
  }

  /**
   * Get the mapped entity values.
   *
   * @return array
   *   The entity values.
   */
  public function getEntityValues() {
    return $this->entityValues;
  }

  /**
   * Set the entity values.
   *
   * @param array $entity_values
   *   The entity values.
   */
  public function setEntityValues(array $entity_values) {
    $this->entityValues = $entity_values;
  }

}
