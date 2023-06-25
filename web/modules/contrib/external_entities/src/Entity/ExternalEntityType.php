<?php

namespace Drupal\external_entities\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\external_entities\ExternalEntityTypeInterface;
use Drupal\external_entities\FieldMapper\FieldMapperManager;
use Drupal\external_entities\StorageClient\ExternalEntityStorageClientManager;

/**
 * Defines the external_entity_type entity.
 *
 * @ConfigEntityType(
 *   id = "external_entity_type",
 *   label = @Translation("External entity type"),
 *   handlers = {
 *     "list_builder" = "Drupal\external_entities\ExternalEntityTypeListBuilder",
 *     "form" = {
 *       "add" = "Drupal\external_entities\Form\ExternalEntityTypeForm",
 *       "edit" = "Drupal\external_entities\Form\ExternalEntityTypeForm",
 *       "delete" = "Drupal\external_entities\Form\ExternalEntityTypeDeleteForm",
 *     }
 *   },
 *   config_prefix = "external_entity_type",
 *   admin_permission = "administer external entity types",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *   },
 *   links = {
 *     "edit-form" = "/admin/structure/external-entity-types/{external_entity_type}",
 *     "delete-form" = "/admin/structure/external-entity-types/{external_entity_type}/delete",
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "label_plural",
 *     "description",
 *     "generate_aliases",
 *     "read_only",
 *     "field_mapper_id",
 *     "field_mapper_config",
 *     "storage_client_id",
 *     "storage_client_config",
 *     "persistent_cache_max_age",
 *     "annotation_entity_type_id",
 *     "annotation_bundle_id",
 *     "annotation_field_name",
 *     "inherits_annotation_fields"
 *  }
 * )
 */
class ExternalEntityType extends ConfigEntityBase implements ExternalEntityTypeInterface {

  /**
   * Indicates that entities of this external entity type should not be cached.
   */
  const CACHE_DISABLED = 0;

  /**
   * The external entity type ID.
   *
   * @var string
   */
  protected $id;

  /**
   * The human-readable name of the external entity type.
   *
   * @var string
   */
  protected $label;

  /**
   * The plural human-readable name of the external entity type.
   *
   * @var string
   */
  protected $label_plural;

  /**
   * The external entity type description.
   *
   * @var string
   */
  protected $description;

  /**
   * Whether or not entity types of this external entity type are read only.
   *
   * @var bool
   */
  protected $read_only;

  /**
   * Whether or not to automatically generate aliases for this external entity type.
   *
   * @var bool
   */
  protected $generate_aliases;

  /**
   * The ID of the field mapper plugin.
   *
   * @var string
   */
  protected $field_mapper_id;

  /**
   * The field mapper plugin configuration.
   *
   * @var array
   */
  protected $field_mapper_config = [];

  /**
   * The field mapper plugin instance.
   *
   * @var \Drupal\external_entities\FieldMapper\FieldMapperInterface
   */
  protected $fieldMapperPlugin;

  /**
   * The ID of the storage client plugin.
   *
   * @var string
   */
  protected $storage_client_id;

  /**
   * The storage client plugin configuration.
   *
   * @var array
   */
  protected $storage_client_config = [];

  /**
   * The storage client plugin instance.
   *
   * @var \Drupal\external_entities\StorageClient\ExternalEntityStorageClientInterface
   */
  protected $storageClientPlugin;

  /**
   * Max age entities of this external entity type may be persistently cached.
   *
   * @var int
   */
  protected $persistent_cache_max_age = self::CACHE_DISABLED;

  /**
   * The annotations entity type id.
   *
   * @var string
   */
  protected $annotation_entity_type_id;

  /**
   * The annotations bundle id.
   *
   * @var string
   */
  protected $annotation_bundle_id;

  /**
   * The field this external entity is referenced from by the annotation entity.
   *
   * @var string
   */
  protected $annotation_field_name;

  /**
   * Local cache for the annotation field.
   *
   * @var array
   *
   * @see ExternalEntityType::getAnnotationField()
   */
  protected $annotationField;

  /**
   * Indicates if the external entity inherits the annotation entity fields.
   *
   * @var bool
   */
  protected $inherits_annotation_fields = FALSE;

  /**
   * {@inheritdoc}
   */
  public function getLabel() {
    return $this->label;
  }

  /**
   * {@inheritdoc}
   */
  public function getPluralLabel() {
    return $this->label_plural;
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->description;
  }

  /**
   * {@inheritdoc}
   */
  public function isReadOnly() {
    return $this->read_only;
  }

  /**
   * {@inheritdoc}
   */
  public function automaticallyGenerateAliases() {
    return $this->generate_aliases;
  }

  /**
   * {@inheritdoc}
   */
  public function hasValidFieldMapper() {
    $field_mapper_plugin_definition = \Drupal::service('plugin.manager.external_entities.field_mapper')
      ->getDefinition($this->getFieldMapperId(), FALSE);
    return !empty($field_mapper_plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldMapperId() {
    return $this->field_mapper_id;
  }

  /**
   * {@inheritdoc}
   */
  public function setFieldMapperId($field_mapper_id) {
    $this->field_mapper_id = $field_mapper_id;
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldMapper() {
    if (!$this->fieldMapperPlugin) {
      $field_mapper_plugin_manager =
        \Drupal::service('plugin.manager.external_entities.field_mapper');
      assert($field_mapper_plugin_manager instanceof FieldMapperManager);

      $config = $this->getFieldMapperConfig();

      // Allow the mapper to call back into the entity type (e.g., to fetch
      // additional data like field lists from the remote service).
      $config['_external_entity_type'] = $this;

      $this->fieldMapperPlugin =
        $field_mapper_plugin_manager->createInstance(
          $this->getFieldMapperId(),
          $config
        );
    }

    return $this->fieldMapperPlugin;
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldMapperConfig() {
    return $this->field_mapper_config ?: [];
  }

  /**
   * {@inheritdoc}
   */
  public function setFieldMapperConfig(array $field_mapper_config) {
    $this->field_mapper_config = $field_mapper_config;
    $this->getFieldMapper()->setConfiguration($field_mapper_config);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function hasValidStorageClient() {
    $storage_client_plugin_definition = \Drupal::service('plugin.manager.external_entities.storage_client')->getDefinition($this->getStorageClientId(), FALSE);
    return !empty($storage_client_plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public function getStorageClientId() {
    return $this->storage_client_id;
  }

  /**
   * {@inheritdoc}
   */
  public function setStorageClientId($storage_client_id) {
    $this->storage_client_id = $storage_client_id;
  }

  /**
   * {@inheritdoc}
   */
  public function getStorageClient() {
    if (!$this->storageClientPlugin) {
      $storage_client_plugin_manager = \Drupal::service('plugin.manager.external_entities.storage_client');
      assert($storage_client_plugin_manager instanceof ExternalEntityStorageClientManager);

      $config = $this->getStorageClientConfig();

      // Allow the storage client to call back into the entity type (e.g., to
      // fetch additional data or settings).
      $config['_external_entity_type'] = $this;

      $this->storageClientPlugin =
        $storage_client_plugin_manager->createInstance(
          $this->getStorageClientId(),
          $config
        );
    }

    return $this->storageClientPlugin;
  }

  /**
   * {@inheritdoc}
   */
  public function getStorageClientConfig() {
    return $this->storage_client_config ?: [];
  }

  /**
   * {@inheritdoc}
   */
  public function setStorageClientConfig(array $storage_client_config) {
    $this->storage_client_config = $storage_client_config;
    $this->getStorageClient()->setConfiguration($storage_client_config);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getPersistentCacheMaxAge() {
    return $this->persistent_cache_max_age;
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(EntityStorageInterface $storage, $update = TRUE) {
    parent::postSave($storage, $update);

    // Clear the entity type definitions cache so changes flow through to the
    // related entity types.
    $this->entityTypeManager()->clearCachedDefinitions();

    // Clear the router cache to prevent RouteNotFoundException errors caused
    // by the Field UI module.
    \Drupal::service('router.builder')->rebuild();

    // Rebuild local actions so that the 'Add field' action on the 'Manage
    // fields' tab appears.
    \Drupal::service('plugin.manager.menu.local_action')->clearCachedDefinitions();

    // Clear the static and persistent cache.
    $storage->resetCache();
    if ($this->entityTypeManager()->hasDefinition($this->id())) {
      $this
        ->entityTypeManager()
        ->getStorage($this->id())
        ->resetCache();
    }

    $edit_link = $this->toLink(t('Edit entity type'), 'edit-form')->toString();
    if ($update) {
      $this->logger($this->id())->notice(
        'Entity type %label has been updated.',
        ['%label' => $this->label(), 'link' => $edit_link]
      );
    }
    else {
      // Notify storage to create the database schema.
      $entity_type = $this->entityTypeManager()->getDefinition($this->id());
      \Drupal::service('entity_type.listener')
        ->onEntityTypeCreate($entity_type);

      $this->logger($this->id())->notice(
        'Entity type %label has been added.',
        ['%label' => $this->label(), 'link' => $edit_link]
      );
    }

  }

  /**
   * {@inheritdoc}
   */
  public static function postDelete(EntityStorageInterface $storage, array $entities) {
    parent::postDelete($storage, $entities);
    \Drupal::service('entity_type.manager')->clearCachedDefinitions();
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivedEntityTypeId() {
    return $this->id();
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivedEntityType() {
    return $this->entityTypeManager()->getDefinition($this->getDerivedEntityTypeId(), FALSE);
  }

  /**
   * {@inheritdoc}
   */
  public function isAnnotatable() {
    return $this->getAnnotationEntityTypeId()
      && $this->getAnnotationBundleId()
      && $this->getAnnotationFieldName();
  }

  /**
   * {@inheritdoc}
   */
  public function getAnnotationEntityTypeId() {
    return $this->annotation_entity_type_id;
  }

  /**
   * {@inheritdoc}
   */
  public function getAnnotationBundleId() {
    return $this->annotation_bundle_id ?: $this->getAnnotationEntityTypeId();
  }

  /**
   * {@inheritdoc}
   */
  public function getAnnotationFieldName() {
    return $this->annotation_field_name;
  }

  /**
   * Returns the entity field manager.
   *
   * @return \Drupal\Core\Entity\EntityFieldManagerInterface
   *   The entity field manager.
   */
  protected function entityFieldManager() {
    return \Drupal::service('entity_field.manager');
  }

  /**
   * {@inheritdoc}
   */
  public function getAnnotationField() {
    if (!isset($this->annotationField) && $this->isAnnotatable()) {
      $field_definitions = $this->entityFieldManager()->getFieldDefinitions($this->getAnnotationEntityTypeId(), $this->getAnnotationBundleId());
      $annotation_field_name = $this->getAnnotationFieldName();
      if (!empty($field_definitions[$annotation_field_name])) {
        $this->annotationField = $field_definitions[$annotation_field_name];
      }
    }

    return $this->annotationField;
  }

  /**
   * {@inheritdoc}
   */
  public function inheritsAnnotationFields() {
    return (bool) $this->inherits_annotation_fields;
  }

  /**
   * {@inheritdoc}
   */
  public function getBasePath() {
    return str_replace('_', '-', strtolower($this->id));
  }

  /**
   * Gets the logger for a specific channel.
   *
   * @param string $channel
   *   The name of the channel.
   *
   * @return \Psr\Log\LoggerInterface
   *   The logger for this channel.
   */
  protected function logger($channel) {
    return \Drupal::getContainer()->get('logger.factory')->get($channel);
  }

  /**
   * {@inheritdoc}
   */
  public function __sleep() {
    // Prevent some properties from being serialized.
    return array_diff(parent::__sleep(), [
      'fieldMapperPlugin',
      'storageClientPlugin',
      'annotationField',
    ]);
  }

}
