<?php

namespace Drupal\external_entities\Plugin\ExternalEntities\FieldMapper;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\external_entities\FieldMapper\ConfigurableExpressionFieldMapperBase;
use JsonPath\JsonObject;

/**
 * A field mapper that uses JSONPath expressions.
 *
 * Multi-valued fields should be mapped using JSONPath expressions that result
 * in an array of values being returned.
 *
 * Constants (fixed values) can be mapped by prefixing the mapping expression
 * with a '+' character.
 *
 * @FieldMapper(
 *   id = "jsonpath",
 *   label = @Translation("JSONPath"),
 *   description = @Translation("Maps fields based on JSONPath expressions.")
 * )
 */
class JsonPath extends ConfigurableExpressionFieldMapperBase {

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
  public function getConstantMappingPrefix() {
    return '+';
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form += parent::buildConfigurationForm($form, $form_state);

    $links = [
      Link::fromTextAndUrl(
        $this->t('JSONPath syntax and limitation'),
        Url::fromUri('https://github.com/Galbar/JsonPath-PHP#jsonpath-language')),

      Link::fromTextAndUrl(
        $this->t('JSONPath examples'),
        Url::fromUri('https://support.smartbear.com/alertsite/docs/monitors/api/endpoint/jsonpath.html')),
    ];

    $form['help']['jsonpath'] = [
      '#theme' => 'item_list',
      '#type' => 'ul',
      '#prefix' => '<p>' . $this->t('See these documentation links:') . '</p>',
      '#items' => array_map(function (Link $link) {
        return $link->toRenderable();
      }, $links),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function extractIdFromRawData(array $raw_data) {
    $id_property_mapping = $this->getFieldMapping('id', 'value');
    $jsonObject = new JsonObject($raw_data, TRUE);
    return $jsonObject->get($id_property_mapping);
  }

  /**
   * {@inheritdoc}
   */
  protected function extractFieldPropertyValuesFromRawData(FieldDefinitionInterface $field_definition, $property_name, array $raw_data, array &$context) {
    $mapping = $this->getFieldPropertyMapping($field_definition->getName(), $property_name);
    if (!$mapping) {
      return [];
    }

    if (empty($context['jsonpath_object'])) {
      $context['jsonpath_object'] = new JsonObject($raw_data);
    }

    return $context['jsonpath_object']->get($mapping) ?: [];
  }

  /**
   * {@inheritdoc}
   */
  protected function addFieldValuesToRawData(FieldDefinitionInterface $field_definition, $field_values, array &$raw_data, array &$context) {
    if (empty($context['jsonpath_object'])) {
      $context['jsonpath_object'] = new JsonObject($raw_data);
    }

    $property_mappings = $this->getFieldMapping($field_definition->getName());
    if (empty($property_mappings)) {
      return NULL;
    }

    $field_cardinality = $field_definition->getFieldStorageDefinition()->getCardinality();

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

      $json_value = $property_values[$property_name] ?? [];
      // Unwrap the singleton array if the cardinality of the field is 1.
      if ($field_cardinality === 1) {
        $json_value = array_shift($json_value);
      }

      if (!empty($json_value)) {
        $context['jsonpath_object']->set($mapping, $json_value);
      }
    }

    // $json_object carries state between the fields, so this is just
    // reconciling the output of mapping with the state building up in the
    // JSONPath interpreter.
    $raw_data = $context['jsonpath_object']->getValue();
  }

}
