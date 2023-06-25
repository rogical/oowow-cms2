<?php

namespace Drupal\external_entities;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Cache\MemoryCache\MemoryCacheInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\ContentEntityStorageBase;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\external_entities\Event\ExternalEntitiesEvents;
use Drupal\external_entities\Event\ExternalEntityMapRawDataEvent;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Defines the storage handler class for external entities.
 *
 * This extends the base storage class, adding required special handling for
 * e entities.
 */
class ExternalEntityStorage extends ContentEntityStorageBase implements ExternalEntityStorageInterface {

  /**
   * Entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The field mapper manager.
   *
   * @var \Drupal\Component\Plugin\PluginManagerInterface
   */
  protected $fieldMapperManager;

  /**
   * Field mapper instance.
   *
   * @var \Drupal\external_entities\FieldMapper\FieldMapperInterface
   */
  protected $fieldMapper;

  /**
   * The external storage client manager.
   *
   * @var \Drupal\Component\Plugin\PluginManagerInterface
   */
  protected $storageClientManager;

  /**
   * Storage client instance.
   *
   * @var \Drupal\external_entities\StorageClient\ExternalEntityStorageClientInterface
   */
  protected $storageClient;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('entity_field.manager'),
      $container->get('cache.entity'),
      $container->get('entity.memory_cache'),
      $container->get('entity_type.bundle.info'),
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.external_entities.field_mapper'),
      $container->get('plugin.manager.external_entities.storage_client'),
      $container->get('datetime.time'),
      $container->get('event_dispatcher'),
      $container->get('date.formatter')
    );
  }

  /**
   * Constructs a new ExternalEntityStorage object.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   The cache backend to be used.
   * @param \Drupal\Core\Cache\MemoryCache\MemoryCacheInterface|null $memory_cache
   *   The memory cache backend.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle info.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Component\Plugin\PluginManagerInterface $field_mapper_manager
   *   The field mapper manager.
   * @param \Drupal\Component\Plugin\PluginManagerInterface $storage_client_manager
   *   The storage client manager.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   */
  public function __construct(
    EntityTypeInterface $entity_type,
    EntityFieldManagerInterface $entity_field_manager,
    CacheBackendInterface $cache,
    MemoryCacheInterface $memory_cache,
    EntityTypeBundleInfoInterface $entity_type_bundle_info,
    EntityTypeManagerInterface $entity_type_manager,
    PluginManagerInterface $field_mapper_manager,
    PluginManagerInterface $storage_client_manager,
    TimeInterface $time,
    EventDispatcherInterface $event_dispatcher,
    DateFormatterInterface $date_formatter
  ) {
    parent::__construct($entity_type, $entity_field_manager, $cache, $memory_cache, $entity_type_bundle_info);
    $this->entityTypeManager = $entity_type_manager;
    $this->fieldMapperManager = $field_mapper_manager;
    $this->storageClientManager = $storage_client_manager;
    $this->time = $time;
    $this->entityFieldManager = $entity_field_manager;
    $this->eventDispatcher = $event_dispatcher;
    $this->dateFormatter = $date_formatter;
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldMapper() {
    if (!$this->fieldMapper) {
      $this->fieldMapper = $this
        ->getExternalEntityType()
        ->getFieldMapper();
    }
    return $this->fieldMapper;
  }

  /**
   * {@inheritdoc}
   */
  public function getStorageClient() {
    if (!$this->storageClient) {
      $this->storageClient = $this
        ->getExternalEntityType()
        ->getStorageClient();
    }
    return $this->storageClient;
  }

  /**
   * Acts on entities before they are deleted and before hooks are invoked.
   *
   * Used before the entities are deleted and before invoking the delete hook.
   *
   * @param \Drupal\Core\Entity\EntityInterface[] $entities
   *   An array of entities.
   *
   * @throws EntityStorageException
   */
  public function preDelete(array $entities) {
    if ($this->getExternalEntityType()->isReadOnly()) {
      throw new EntityStorageException($this->t('Can not delete read-only external entities.'));
    }
  }

  /**
   * Gets the entity type definition.
   *
   * @return \Drupal\external_entities\ExternalEntityTypeInterface
   *   Entity type definition.
   */
  public function getEntityType() {
    /* @var \Drupal\external_entities\ExternalEntityTypeInterface $entity_type */
    $entity_type = $this->entityType;
    return $entity_type;
  }

  /**
   * {@inheritdoc}
   */
  protected function doDelete($entities) {
    // Do the actual delete.
    foreach ($entities as $entity) {
      $this->getStorageClient()->delete($entity);
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function doLoadMultiple(array $ids = NULL) {
    // Attempt to load entities from the persistent cache. This will remove IDs
    // that were loaded from $ids.
    $entities_from_cache = $this->getFromPersistentCache($ids);

    // Load any remaining entities from the external storage.
    if ($entities_from_storage = $this->getFromExternalStorage($ids)) {
      $this->invokeStorageLoadHook($entities_from_storage);
      $this->setPersistentCache($entities_from_storage);
    }

    $entities = $entities_from_cache + $entities_from_storage;

    // Map annotation fields to annotatable external entities.
    foreach ($entities as $external_entity) {
      /* @var \Drupal\external_entities\ExternalEntityInterface $external_entity */
      if ($external_entity->getExternalEntityType()->isAnnotatable()) {
        $external_entity->mapAnnotationFields();
      }
    }

    return $entities;
  }

  /**
   * Gets entities from the external storage.
   *
   * @param array|null $ids
   *   If not empty, return entities that match these IDs. Return no entities
   *   when NULL.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface[]
   *   Array of entities from the storage.
   */
  protected function getFromExternalStorage(array $ids = NULL) {
    $entities = [];

    if (!empty($ids)) {
      // Sanitize IDs. Before feeding ID array into buildQuery, check whether
      // it is empty as this would load all entities.
      $ids = $this->cleanIds($ids);
    }

    if ($ids === NULL || $ids) {
      $data = $this
        ->getStorageClient()
        ->loadMultiple($ids);

      // Map the data into entity objects and according fields.
      if ($data) {
        $entities = $this->mapFromRawStorageData($data);
      }
    }

    return $entities;
  }

  /**
   * {@inheritdoc}
   */
  protected function cleanIds(array $ids, $entity_key = 'id') {
    // getFieldStorageDefinitions() is used instead of
    // getActiveFieldStorageDefinitions() because the latter fails to return
    // all definitions in the event an external entity is not cached locally.
    $definitions = $this->entityFieldManager->getFieldStorageDefinitions($this->entityTypeId);
    $field_name = $this->entityType->getKey($entity_key);
    if ($field_name && $definitions[$field_name]->getType() == 'integer') {
      $ids = array_filter($ids, function ($id) {
        return is_numeric($id) && $id == (int) $id;
      });
      $ids = array_map('intval', $ids);
    }
    return $ids;
  }

  /**
   * Maps from storage data to entity objects, and attaches fields.
   *
   * @param array $data
   *   Associative array of storage results, keyed on the entity ID.
   *
   * @return \Drupal\external_entities\ExternalEntityInterface[]
   *   An array of entity objects implementing the ExternalEntityInterface.
   */
  protected function mapFromRawStorageData(array $data) {
    if (!$data) {
      return [];
    }

    $values = [];
    foreach ($data as $id => $raw_data) {
      if (empty($raw_data)) {
        continue;
      }
      $entity_values = $this->getFieldMapper()->extractEntityValuesFromRawData($raw_data);
      if (!empty($entity_values)) {
        $values[$id] = $entity_values;
      }
    }

    $entities = [];
    foreach ($values as $id => $entity_values) {
      if (empty($data[$id])) {
        continue;
      }
      // Allow other modules to perform custom mapping logic.
      $event = new ExternalEntityMapRawDataEvent($data[$id], $entity_values);
      $this->eventDispatcher->dispatch($event, ExternalEntitiesEvents::MAP_RAW_DATA);

      try {
        $entities[$id] = $this->doCreate($event->getEntityValues());
      } catch (EntityStorageException $exception) {
        watchdog_exception('external_entities', $exception);
      }
      $entities[$id]->enforceIsNew(FALSE);
    }

    return $entities;
  }

  /**
   * {@inheritdoc}
   */
  protected function setPersistentCache($entities) {
    if (!$this->entityType->isPersistentlyCacheable()) {
      return;
    }

    $cache_tags = [
      $this->entityTypeId . '_values',
      'entity_field_info',
    ];

    foreach ($entities as $id => $entity) {
      $max_age = $this->getExternalEntityType()->getPersistentCacheMaxAge();
      $entity_cache_tags = Cache::mergeTags($cache_tags, [$this->entityTypeId . ':' . $entity->id()]);
      $expire = $max_age === Cache::PERMANENT
        ? Cache::PERMANENT
        : $this->time->getRequestTime() + $max_age;
      $this->cacheBackend->set($this->buildCacheId($id), $entity, $expire, $entity_cache_tags);
    }
  }

  /**
   * Acts on an entity before the presave hook is invoked.
   *
   * Used before the entity is saved and before invoking the presave hook.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity object.
   *
   * @throws EntityStorageException
   */
  public function preSave(EntityInterface $entity) {
    $external_entity_type = $this->getExternalEntityType();
    if ($external_entity_type->isReadOnly() && !$external_entity_type->isAnnotatable()) {
      throw new EntityStorageException($this->t('Can not save read-only external entities.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function doSave($id, EntityInterface $entity) {
    /* @var \Drupal\external_entities\ExternalEntityInterface $entity */
    $result = FALSE;

    $external_entity_type = $this->getExternalEntityType();
    if (!$external_entity_type->isReadOnly()) {
      $result = parent::doSave($id, $entity);
    }

    if ($external_entity_type->isAnnotatable()) {
      $referenced_entities = $entity
        ->get(ExternalEntityInterface::ANNOTATION_FIELD)
        ->referencedEntities();
      if ($referenced_entities) {
        $annotation = array_shift($referenced_entities);

        $referenced_external_entities = $annotation
          ->get($external_entity_type->getAnnotationFieldName())
          ->referencedEntities();
        $referenced_external_entity = array_shift($referenced_external_entities);
        if (empty($referenced_external_entity)
          || $entity->getEntityTypeId() !== $referenced_external_entity->getEntityTypeId()
          || $entity->id() !== $referenced_external_entity->id()) {
          $annotation->set($external_entity_type->getAnnotationFieldName(), $entity->id());
          $annotation->{EXTERNAL_ENTITIES_BYPASS_ANNOTATED_EXTERNAL_ENTITY_SAVE_PROPERTY} = TRUE;
          $annotation->save();
        }
      }
    }

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  protected function getQueryServiceName() {
    return 'entity.query.external';
  }

  /**
   * {@inheritdoc}
   */
  protected function has($id, EntityInterface $entity) {
    return !$entity->isNew();
  }

  /**
   * {@inheritdoc}
   */
  protected function doDeleteFieldItems($entities) {
  }

  /**
   * {@inheritdoc}
   */
  protected function doDeleteRevisionFieldItems(ContentEntityInterface $revision) {
  }

  /**
   * {@inheritdoc}
   */
  public function loadMultipleRevisions(array $revision_ids) {
    return $this->doLoadMultiple($revision_ids);
  }

  /**
   * {@inheritdoc}
   */
  protected function doLoadRevisionFieldItems($revision_id) {
  }

  /**
   * {@inheritdoc}
   */
  protected function doLoadMultipleRevisionsFieldItems($revision_ids) {
  }

  /**
   * {@inheritdoc}
   */
  protected function doSaveFieldItems(ContentEntityInterface $entity, array $names = []) {
    if (!empty($entity->{ExternalEntityStorageInterface::BYPASS_STORAGE_CLIENT_SAVE_PROPERTY})) {
      return;
    }

    $id = $this->getStorageClient()->save($entity);
    if ($id && $entity->isNew()) {
      $entity->{$this->idKey} = $id;
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function readFieldItemsToPurge(FieldDefinitionInterface $field_definition, $batch_size) {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  protected function purgeFieldItems(ContentEntityInterface $entity, FieldDefinitionInterface $field_definition) {
  }

  /**
   * {@inheritdoc}
   */
  public function countFieldData($storage_definition, $as_bool = FALSE) {
    return $as_bool ? 0 : FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function hasData() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getExternalEntityType() {
    return $this->entityTypeManager
      ->getStorage('external_entity_type')
      ->load($this->getEntityTypeId());
  }

}
