<?php

namespace Drupal\external_entities\Plugin\ExternalEntities\FieldMapper;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\external_entities\Annotation\FieldMapper;
use Drupal\external_entities\FieldMapper\ConfigurableExpressionFieldMapperBase;

/**
 * A field mapper that implements External Entities' classic data path mapping
 * syntax -- simple slash-delimited paths through the raw data structure.
 *
 * - A mapping that starts with a plus (+) character signifies a mapping to a
 *   constant value. Everything after the plus character is taken as the
 *   constant.
 * - A mapping component that is only the asterisk (*) character signifies the
 *   part of a mapping that corresponds to a list of values for a multivalued
 *   field.
 *
 * @FieldMapper(
 *   id = "simple",
 *   label = @Translation("Simple"),
 *   description = @Translation("Maps entity fields to raw data using simple
 *   path expressions.")
 * )
 *
 * @package Drupal\external_entities\Plugin\ExternalEntities\FieldMapper
 */
class SimpleFieldMapper extends ConfigurableExpressionFieldMapperBase {

  /**
   * {@inheritdoc}
   */
  public function getFieldMappings() {
    $configuration = $this->getConfiguration();
    return $configuration['field_mappings'] ?? [];
  }

  /**
   * {@inheritdoc}
   */
  public function getConstantMappingPrefix(): ?string {
    return '+';
  }

  /**
   * {@inheritdoc}
   */
  protected function getRequiredFieldMappings(): array {
    return [
      'id',
      'title',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form += parent::buildConfigurationForm($form, $form_state);

    $form['field_mappings']['uuid']['value']['#description'] = $this->t('It is advised to map this field');
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function extractIdFromRawData(array $raw_data) {
    $id_field_name = 'id';
    $id_field_property = 'value';

    $id_property_mapping = $this->getFieldPropertyMapping($id_field_name, $id_field_property);
    $mapping_keys = explode('/', $id_property_mapping);

    if (empty($id_property_mapping)) {
      // Should not happen if form validation is working properly, but could
      // happen if bad data was migrated from an older version.
      return NULL;
    }

    $field_definitions = $this->getMappableFields();
    $ids = $this->recursiveMapFieldFromRawData(
      $raw_data,
      $mapping_keys,
      [],
      $field_definitions[$id_field_name],
      0,
      $id_field_property
    );
    return reset($ids);
  }

  /**
   * {@inheritdoc}
   */
  protected function extractFieldPropertyValuesFromRawData(FieldDefinitionInterface $field_definition,
                                                                                    $property_name,
                                                           array                    $raw_data,
                                                           array                    &$context): array {
    $mapping = $this->getFieldPropertyMapping($field_definition->getName(), $property_name);

    if (!$mapping) {
      return [];
    }
    $mapping_keys = explode('/', $mapping);

    return $this->recursiveMapFieldFromRawData(
      $raw_data,
      $mapping_keys,
      [],
      $field_definition,
      0,
      $property_name
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function addFieldValuesToRawData(FieldDefinitionInterface $field_definition,
                                             array                    $field_values,
                                             array                    &$raw_data,
                                             array                    &$context) {

    $property_mappings = $this->getFieldMapping($field_definition->getName());
    if (empty($property_mappings)) {
      return NULL;
    }

    // Convert [delta][property] structure to [property][delta] structure, so
    // that each property can be set in the raw data all at once in one setter
    // operation.
    $property_values = [];
    foreach ($field_values as $delta => $field_value) {
      foreach ($field_value as $property_name => $property_value) {
        $property_values[$property_name][$delta] = $property_value;
      }
    }

    foreach ($property_mappings as $property_name => $mapping) {
      // Skip constant values.
      if ($this->isConstantValueMapping($mapping)) {
        continue;
      }

      $data_value = $property_values[$property_name] ?? [];

      $delta_index = 0;
      if (!empty($data_value)) {
        foreach ($data_value as $value) {
          $qualified_mapping = str_replace('*', $delta_index, $mapping);
          $mapping_keys = explode('/', $qualified_mapping);
          NestedArray::setValue($raw_data, $mapping_keys, $value);
          $delta_index++;
        }
      }
    }
  }

  /**
   * Populates the values of a field from raw values by recursively navigating
   * mapping keys.
   *
   * @param array $raw_data
   *   The raw values to map into the output.
   * @param array $remaining_mapping_keys
   *   All the mapping keys that remain to be examined recursively.
   * @param array $seen_mapping_keys
   *   All the mapping keys that have been examined so far.
   * @param FieldDefinitionInterface $field_definition
   *   The definition for the field being mapped.
   * @param int $field_delta
   *   The current delta within the field being populated.
   * @param string $field_property_name
   *   The machine name of the field property being populated.
   *
   * @return array
   *   The list of property values or an empty array.
   */
  protected function recursiveMapFieldFromRawData(array                    $raw_data,
                                                  array                    $remaining_mapping_keys,
                                                  array                    $seen_mapping_keys,
                                                  FieldDefinitionInterface $field_definition,
                                                  int                      $field_delta,
                                                  string                   $field_property_name): array {
    $current_mapping_key = array_shift($remaining_mapping_keys);

    // Case 1: End of recursion -- set the field property value.
    if ($current_mapping_key === NULL) {
      return $this->extractRawData(
        $raw_data,
        $seen_mapping_keys,
        $field_definition,
        $field_delta,
        $field_property_name
      );
    }
    // Case 2: Iterate and recurse over a list of values
    elseif ($current_mapping_key == '*') {
      return $this->recursiveMapListFieldFromRawData(
        $raw_data,
        $remaining_mapping_keys,
        $seen_mapping_keys,
        $field_definition,
        $field_property_name
      );
    }
    // Case 3: Recurse into a single-valued key of the raw data
    else {
      $new_seen_mapping_keys =
        array_merge($seen_mapping_keys, [$current_mapping_key]);

      return $this->recursiveMapFieldFromRawData(
        $raw_data,
        $remaining_mapping_keys,
        $new_seen_mapping_keys,
        $field_definition,
        $field_delta,
        $field_property_name
      );
    }
  }

  /**
   * Extracts a value at the specified location in raw data and then uses it to
   * populate the specified property of the indicated delta of the specified
   * field.
   *
   * @param array $raw_data
   *   The raw values from which to obtain the field value.
   * @param array $raw_keys
   *   The nested array keys of the raw data that designate the location in the
   *   raw data where the value is to be extracted.
   * @param FieldDefinitionInterface $field_definition
   *   The definition for the field being mapped.
   * @param int $field_delta
   *   The current delta within the field being populated.
   * @param string $field_property_name
   *   The machine name of the field property being populated.
   *
   * @return array
   *   List of property values.
   */
  protected function extractRawData(array                    $raw_data,
                                    array                    $raw_keys,
                                    FieldDefinitionInterface $field_definition,
                                    int                      $field_delta,
                                    string                   $field_property_name): array {
    $property_value = NestedArray::getValue($raw_data, $raw_keys);
    return is_array($property_value) ? $property_value : [$property_value];
  }

  /**
   * Extracts a list of values from the raw data, then iterates and recurses on
   * each one to populate the equivalent field within the entity field values.
   *
   * @param array $raw_data
   *   The raw values from which to obtain the list values.
   * @param array $remaining_mapping_keys
   *   All the mapping keys that remain to be examined recursively.
   * @param array $seen_mapping_keys
   *   All the mapping keys that have been examined so far.
   * @param FieldDefinitionInterface $field_definition
   *   The definition for the field being mapped.
   * @param string $field_property_name
   *   The machine name of the field property being populated.
   */
  protected function recursiveMapListFieldFromRawData(array                    $raw_data,
                                                      array                    $remaining_mapping_keys,
                                                      array                    $seen_mapping_keys,
                                                      FieldDefinitionInterface $field_definition,
                                                      string                   $field_property_name): array {
    $raw_values = NestedArray::getValue($raw_data, $seen_mapping_keys);
    $field_values = [];

    if (!is_array($raw_values)) {
      $raw_values = [$raw_values];
    }

    foreach (range(0, count($raw_values)) as $delta_index) {
      $new_seen_mapping_keys = array_merge($seen_mapping_keys, [$delta_index]);

      $values = $this->recursiveMapFieldFromRawData(
        $raw_data,
        $remaining_mapping_keys,
        $new_seen_mapping_keys,
        $field_definition,
        $delta_index,
        $field_property_name
      );
      $field_values[] = reset($values);
    }
    return $field_values;
  }

}
