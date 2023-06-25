<?php

namespace Drupal\external_entities\FieldMapper;

/**
 * Interface for expression-based per-field field mappers.
 *
 * To be used for field mappers that map data to fields via expressions, such
 * as JSONPath or XPath.
 */
interface ExpressionFieldMapperInterface extends FieldMapperInterface {

  /**
   * Gets all configured field mappings.
   *
   * @return array
   *   An associative array of field mappings, keyed by the field name. Each
   *   value is an associative array of mapping expressions, keyed by the
   *   property name.
   */
  public function getFieldMappings();

  /**
   * Gets the mapping for a field.
   *
   * @param string $field_name
   *   The field name.
   * @param string $property_name
   *   (optional) The property name.
   *
   * @return string|array|null
   *   If called without a property name, all the property mappings of the
   *   field are returned as an associative array of mappings, keyed by the
   *   property name. If called with a property name, the mapping of that
   *   property is returned as a string. NULL is returned if no mapping could
   *   be found for the given field and/or property.
   */
  public function getFieldMapping($field_name, $property_name = NULL);

  /**
   * Gets the mapping for a field property.
   *
   * @param string $field_name
   *   The field name.
   * @param string $property_name
   *   The property name.
   *
   * @return string|null
   *   The mapping expression or NULL if none found.
   */
  public function getFieldPropertyMapping($field_name, $property_name);

  /**
   * Get the constant mapping prefix.
   *
   * If a constant (fixed value) is mapped instead of a dynamic value, the
   * expression is prefixed with a string.
   *
   * @return string|null
   *   The constant mapping prefix. If NULL, the field mapper doesn't support
   *   constant mapping.
   */
  public function getConstantMappingPrefix();

}
