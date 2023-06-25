<?php

namespace Drupal\external_entities\FieldMapper;

use Drupal\Core\TypedData\Plugin\DataType\DateTimeIso8601;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\TypedData\PrimitiveInterface;
use Drupal\Core\TypedData\Type\DateTimeInterface;
use Drupal\Core\TypedData\TypedDataManagerInterface;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItem;
use Drupal\external_entities\Plugin\PluginFormTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Convenient base class for expression field mappers.
 */
abstract class ExpressionFieldMapperBase extends FieldMapperBase implements ExpressionFieldMapperInterface {

  use PluginFormTrait;

  /**
   * The typed data manager.
   *
   * @var \Drupal\Core\TypedData\TypedDataManagerInterface
   */
  protected $typedDataManager;

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
   * @param \Drupal\Core\TypedData\TypedDataManagerInterface $typed_data_manager
   *   The typed data manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, EntityFieldManagerInterface $entity_field_manager, TypedDataManagerInterface $typed_data_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_type_manager, $entity_field_manager);
    $this->typedDataManager = $typed_data_manager;
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
      $container->get('entity_field.manager'),
      $container->get('typed_data_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldMapping($field_name, $property_name = NULL) {
    $field_mappings = $this->getFieldMappings();

    if (empty($field_mappings[$field_name])) {
      return NULL;
    }

    if (empty($property_name)) {
      return $field_mappings[$field_name];
    }

    return $field_mappings[$field_name][$property_name] ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldPropertyMapping($field_name, $property_name) {
    return $this->getFieldMapping($field_name, $property_name);
  }

  /**
   * Get the fields for which a mapping is required.
   *
   * @return string[]
   *   An array of field names.
   */
  protected function getRequiredFieldMappings() {
    $fields = [
      'id',
      'uuid',
      'title',
    ];

    return $fields;
  }

  /**
   * Check if the mapping provides a constant value.
   *
   * @param string $mapping
   *   The mapping value to check.
   *
   * @return bool
   *   TRUE if the mapping provides a constant value, FALSE otherwise.
   */
  protected function isConstantValueMapping($mapping) {
    return $this->getConstantMappingPrefix()
      && is_string($mapping)
      && strpos($mapping, $this->getConstantMappingPrefix()) === 0;
  }

  /**
   * Get the mapped constant value.
   *
   * @param string $mapping
   *   The mapping value from which to extract a constant.
   *
   * @return string|null
   *   Either the constant value; or NULL if the mapping does not specify a
   *   constant value.
   */
  protected function getMappedConstantValue($mapping) {
    return $this->isConstantValueMapping($mapping)
      ? substr($mapping, strlen($this->getConstantMappingPrefix()))
      : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function extractEntityValuesFromRawData(array $raw_data) {
    $entity_values = [];

    $field_definitions = $this->getMappableFields();
    $context = [];

    foreach ($field_definitions as $field_name => $field_definition) {
      $field_values = $this->extractFieldValuesFromRawData($field_definition, $raw_data, $context);
      if (!empty($field_values)) {
        $entity_values[$field_name] = $field_values;
      }
    }

    return $entity_values;
  }

  /**
   * Extracts field values from raw data for a given field.
   *
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   Definition of the field we are extracting for.
   * @param array $raw_data
   *   The raw data to extract the field values from.
   * @param array &$context
   *   Any contextual data that needs to be maintained during the whole
   *   extraction process for an external entity.
   *
   * @return array
   *   An array of field values, or NULL if none.
   */
  protected function extractFieldValuesFromRawData(FieldDefinitionInterface $field_definition, array $raw_data, array &$context) {
    $property_mappings = $this->getFieldMapping($field_definition->getName());
    if (empty($property_mappings)) {
      return NULL;
    }


    $properties_values = [];
    foreach ($property_mappings as $property_name => $mapping) {
      if ($this->isConstantValueMapping($mapping)) {
        continue;
      }

      // Extract values.
      $value = $this->extractFieldPropertyValuesFromRawData($field_definition, $property_name, $raw_data, $context);

      // Make sure to not set references to empty arrays.
      if ($property_name === 'target_id' && (empty($value) || empty($value[0]))) {
        continue;
      }
      $properties_values[$property_name] = $value;
    }

    // We have now collected all the mappable property values. Let's merge them
    // together so they represent a single field values array.
    $field_values = [];
    foreach ($properties_values as $property_name => $property_values) {
      foreach ($property_values as $delta => $property_value) {
        $field_values[$delta][$property_name] = $property_value;
      }
    }

    // Now that we have the field values, and the amount of deltas is known, we
    // can add in the constant mapped values.
    foreach ($property_mappings as $property_name => $mapping) {
      if (!$this->isConstantValueMapping($mapping)) {
        continue;
      }

      if (!empty($field_values)) {
        foreach ($field_values as &$field_value) {
          $field_value[$property_name] = $this->getMappedConstantValue($mapping);
        }
      }
      else {
        $field_values = [
          [$property_name => $this->getMappedConstantValue($mapping)]
        ];
      }
    }

    // Depending on the property type, its value might need some more massaging.
    foreach ($field_values as &$properties_values) {
      foreach ($properties_values as $property_name => &$property_value) {
        $property_value = $this->processPropertyValue($field_definition, $property_name, $property_value);
      }
    }

    return $field_values;
  }

  /**
   * Extracts field property values from raw data for a given field property.
   *
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   Definition of the field we are extracting for.
   * @param string $property_name
   *   Name of the property we are extracting.
   * @param array $raw_data
   *   The raw data to extract the property values from.
   * @param array &$context
   *   Any contextual data that needs to be maintained during the whole
   *   extraction process for an external entity.
   *
   * @return array
   *   An array of property values, empty array if none.
   */
  abstract protected function extractFieldPropertyValuesFromRawData(FieldDefinitionInterface $field_definition, $property_name, array $raw_data, array &$context);

  /**
   * Processes a property value.
   *
   * Provides conversions for special data types and makes sure a property is in
   * the correct PHP value as expected by the data type.
   *
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   Definition of the field we are extracting for.
   * @param string $property_name
   *   Name of the property we are extracting.
   * @param mixed $property_value
   *   The value that needs to be processed.
   *
   * @return mixed
   *   The processed value.
   */
  protected function processPropertyValue(FieldDefinitionInterface $field_definition, $property_name, $property_value) {
    // Create the typed data instance.
    $property_definition = $field_definition
      ->getFieldStorageDefinition()
      ->getPropertyDefinition($property_name);
    $typed_data = $this->typedDataManager->create($property_definition);

    // Provide rudimentary support for datetime-based fields by making sure
    // they are in the format as expected by Drupal.
    if (in_array(DateTimeInterface::class, class_implements($typed_data, TRUE))) {
      $timestamp = $property_value !== NULL && !is_numeric($property_value)
        ? strtotime($property_value)
        : $property_value;

      if (is_numeric($timestamp)) {
        if (get_class($typed_data) === 'Drupal\Core\TypedData\Plugin\DataType\DateTimeIso8601') {
          assert($typed_data instanceof DateTimeIso8601);
          $datetime_type = $field_definition->getFieldStorageDefinition()->getSetting('datetime_type');

          if ($datetime_type === DateTimeItem::DATETIME_TYPE_DATE) {
            $storage_format = DateTimeItemInterface::DATE_STORAGE_FORMAT;
          }
          else {
            $storage_format = DateTimeItemInterface::DATETIME_STORAGE_FORMAT;
          }

          // Use setValue so timezone is not set.
          $typed_data->setValue(gmdate($storage_format, $timestamp));
        }
        else {
          $typed_data->setDateTime(DrupalDateTime::createFromTimestamp($timestamp));
        }
      }
    }
    else {
      $typed_data->setValue($property_value);
    }

    // Convert the property value to the correct PHP type as expected by this
    // specific property type.
    if ($typed_data instanceof PrimitiveInterface) {
      $property_value = $typed_data->getCastedValue();
    }

    return $property_value;
  }

  /**
   * {@inheritdoc}
   */
  public function createRawDataFromEntityValues(array $entity_values) {
    $raw_data = [];

    $mappable_fields = $this->getMappableFields();
    $context = [];

    foreach ($entity_values as $field_name => $field_values) {
      $this->addFieldValuesToRawData($mappable_fields[$field_name], $field_values, $raw_data, $context);
    }

    return $raw_data;
  }

  /**
   * Adds field values to a raw data array for a given field.
   *
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition for the field being mapped.
   * @param array $field_values
   *   The field values to add to the raw data.
   * @param array $raw_data
   *   The raw data array being built.
   * @param array &$context
   *   Any contextual data that needs to be maintained during the whole
   *   creation process for raw data.
   *
   * @return array
   *   The (complete) raw data that eventually will be sent to the external
   *   service.
   */
  abstract protected function addFieldValuesToRawData(FieldDefinitionInterface $field_definition, array $field_values, array &$raw_data, array &$context);

}
