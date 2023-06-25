<?php

namespace Drupal\external_entities;

use Drupal\Core\Entity\ContentEntityStorageInterface;

/**
 * Defines an interface for external entity entity storage classes.
 */
interface ExternalEntityStorageInterface extends ContentEntityStorageInterface {

  /**
   * Get the field mapper.
   *
   * @return \Drupal\external_entities\FieldMapper\FieldMapperInterface
   *   The field mapper.
   */
  public function getFieldMapper();

  /**
   * Property indicating if a save to the external storage must be skipped.
   *
   * By default saving an external entity will trigger the storage client
   * to save the entities raw data to the external storage. This will be skipped
   * if this property is set on the external entity.
   *
   * This is used internally to trigger Drupal hooks relevant to external
   * entity saves, but without touching the storage.
   *
   * @code
   * $external_entity->BYPASS_STORAGE_CLIENT_SAVE_PROPERTY = TRUE;
   * // Save the external entity without triggering the storage client.
   * $external_entity->save();
   * @endcode
   *
   * @see \Drupal\external_entities\ExternalEntityStorage::doSaveFieldItems()
   *
   * @internal
   *
   * @var string
   */
  const BYPASS_STORAGE_CLIENT_SAVE_PROPERTY = 'BYPASS_STORAGE_CLIENT_SAVE';

  /**
   * Get the storage client.
   *
   * @return \Drupal\external_entities\StorageClient\ExternalEntityStorageClientInterface
   *   The external entity storage client.
   */
  public function getStorageClient();

  /**
   * Gets the external entity type.
   *
   * @return \Drupal\external_entities\ExternalEntityTypeInterface
   *   The external entity type.
   */
  public function getExternalEntityType();

}
