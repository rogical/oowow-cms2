<?php

namespace Drupal\external_entities\FieldMapper;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\TypedData\DataDefinitionInterface;
use Drupal\Core\TypedData\DataReferenceInterface;
use Drupal\Core\TypedData\PrimitiveInterface;
use Drupal\external_entities\ExternalEntityInterface;
use Drupal\external_entities\ExternalEntityTypeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A base class for field mappers.
 */
abstract class FieldMapperBase extends PluginBase implements FieldMapperInterface {

  /**
   * The external entity type this field mapper is configured for.
   *
   * @var \Drupal\external_entities\ExternalEntityTypeInterface
   */
  protected $externalEntityType;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * Constructs a FieldMapperBase object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The identifier for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, EntityFieldManagerInterface $entity_field_manager) {
    if (!empty($configuration['_external_entity_type']) && $configuration['_external_entity_type'] instanceof ExternalEntityTypeInterface) {
      $this->externalEntityType = $configuration['_external_entity_type'];
      unset($configuration['_external_entity_type']);
    }
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityFieldManager = $entity_field_manager;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('entity_field.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getLabel() {
    $plugin_definition = $this->getPluginDefinition();
    return $plugin_definition['label'];
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    $plugin_definition = $this->getPluginDefinition();
    return isset($plugin_definition['description']) ? $plugin_definition['description'] : '';
  }

  /**
   * Get the external entity type being operated for.
   *
   * @return \Drupal\external_entities\ExternalEntityTypeInterface
   *   The external entity type definition.
   */
  protected function getExternalEntityType() {
    return $this->externalEntityType;
  }

  /**
   * Get the fields that can be mapped.
   *
   * Computed fields are unmappable, which automatically excludes inherited
   * annotation fields as well. The annotation field is excluded as well.
   *
   * @return \Drupal\Core\Field\FieldDefinitionInterface[]
   *   An associative array of field definitions, keyed by field name.
   */
  protected function getMappableFields() {
    $derived_entity_type = $this
      ->getExternalEntityType()
      ->getDerivedEntityType();

    $fields = [];
    if (!empty($derived_entity_type)) {
      $fields = $this
        ->entityFieldManager
        ->getFieldDefinitions($derived_entity_type->id(), $derived_entity_type->id());
    }

    return array_filter($fields, function (FieldDefinitionInterface $field) {
      return $field->getName() !== ExternalEntityInterface::ANNOTATION_FIELD && !$field->isComputed();
    });
  }

  /**
   * Get the field properties that can be mapped.
   *
   * Field properties that are marked read-only (which include computed ones)
   * are considered unmappable.
   *
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The field definition we want to extract mappable properties from.
   *
   * @return \Drupal\Core\TypedData\DataDefinitionInterface[]
   *   An array of property definitions of mappable properties, keyed by
   *   property name.
   */
  protected function getMappableFieldProperties(FieldDefinitionInterface $field_definition) {
    $properties = $field_definition
      ->getFieldStorageDefinition()
      ->getPropertyDefinitions();
    return array_filter($properties, function (DataDefinitionInterface $property) {
      $property_class = $property->getClass();

      return !$property->isReadOnly() &&
        (is_subclass_of($property_class, PrimitiveInterface::class) ||
         is_subclass_of($property_class, DataReferenceInterface::class));
    });
  }

}
