<?php

namespace Drupal\external_entities\FieldMapper;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\TypedData\DataDefinitionInterface;
use Drupal\Core\TypedData\TypedDataManagerInterface;
use Drupal\external_entities\Plugin\PluginFormTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Convenient base class for expression field mappers configurable via a form.
 */
abstract class ConfigurableExpressionFieldMapperBase extends ExpressionFieldMapperBase implements ConfigurableInterface, PluginFormInterface {

  use PluginFormTrait;

  /**
   * Constructs a ConfigurableExpressionFieldMapperBase object.
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
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   The string translation service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, EntityFieldManagerInterface $entity_field_manager, TypedDataManagerInterface $typed_data_manager, TranslationInterface $string_translation) {
    $configuration += $this->defaultConfiguration();
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_type_manager, $entity_field_manager, $typed_data_manager);
    $this->setStringTranslation($string_translation);
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
      $container->get('typed_data_manager'),
      $container->get('string_translation')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration() {
    return $this->configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration) {
    $this->configuration = $configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [];
  }

  /**
   * Form constructor.
   *
   * @param array $form
   *   An associative array containing the initial structure of the plugin form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form. Calling code should pass on a subform
   *   state created through
   *   \Drupal\Core\Form\SubformState::createForSubform().
   *
   * @return array
   *   The form structure.
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['help'] = [
      '#type' => 'container',
    ];

    $constant_mapping_prefix = $this->getConstantMappingPrefix();
    if ($constant_mapping_prefix) {
      $constant_help_text = $this->t('To provide a constant value instead of a mapping to raw data, use the @constant_mapping_prefix character at the beginning of the entered value. A constant value cannot be applied to required field mappings.', [
        '@constant_mapping_prefix' => $this->getConstantMappingPrefix(),
      ]);
      $form['help']['constant'] = [
        '#markup' => "<p>{$constant_help_text}</p>",
        '#weight' => 100,
      ];
    }

    $form['field_mappings'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Field mappings'),
    ];

    $mappable_fields = $this->getMappableFields();
    foreach ($mappable_fields as $field_name => $field_definition) {
      $form['field_mappings'][$field_name] = $this->buildFieldMappingElement($field_definition);
    }

    return $form;
  }

  /**
   * Build a form element for configuring a field mapping.
   *
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The field definition.
   *
   * @return array
   *   The form element for configuring the field.
   */
  protected function buildFieldMappingElement(FieldDefinitionInterface $field_definition) {
    $element = [];

    $property_definitions = $this->getMappableFieldProperties($field_definition);
    foreach ($property_definitions as $property_name => $property_definition) {
      $element[$property_name] = $this->buildFieldPropertyMappingElement($field_definition, $property_name, $property_definition);
    }

    return $element;
  }

  /**
   * Build a form element for configuring a field property mapping.
   *
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The field definition.
   * @param string $property_name
   *   The property name.
   * @param \Drupal\Core\TypedData\DataDefinitionInterface $property_definition
   *   The property definition.
   *
   * @return array
   *   A renderable form element.
   */
  protected function buildFieldPropertyMappingElement(FieldDefinitionInterface $field_definition, $property_name, DataDefinitionInterface $property_definition) {
    $title = $field_definition->getLabel();;
    // Differentiate between different properties of the field if there are
    // multiple properties.
    if (count($this->getMappableFieldProperties($field_definition)) > 1) {
      $title .= ' » ' . $property_definition->getLabel();
    }

    $mapping = $this->getFieldPropertyMapping($field_definition->getName(), $property_name);
    $is_required_field = in_array($field_definition->getName(), $this->getRequiredFieldMappings(), TRUE);
    $is_main_property = $field_definition->getFieldStorageDefinition()->getMainPropertyName() === $property_name;

    $element = [
      '#title' => $title,
      '#type' => 'textfield',
      '#default_value' => $mapping,
      '#required' => $is_required_field && $is_main_property,
    ];

    return $element;
  }

  /**
   * Form validation handler.
   *
   * @param array $form
   *   An associative array containing the structure of the plugin form as built
   *   by static::buildConfigurationForm().
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form. Calling code should pass on a subform
   *   state created through
   *   \Drupal\Core\Form\SubformState::createForSubform().
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    // Remove empty mappings.
    $field_mappings = $form_state->getValue(['field_mappings'], []);
    $filtered_field_mappings = NestedArray::filter($field_mappings);
    $filtered_field_mappings = array_filter($filtered_field_mappings);
    $form_state->setValue(['field_mappings'], $filtered_field_mappings);

    // Make sure required field mappings are not mapped to a constant value.
    $mappable_fields = $this->getMappableFields();
    if (empty($mappable_fields)) {
      return;
    }

    foreach ($this->getRequiredFieldMappings() as $field_name) {
      if (!array_key_exists($field_name, $mappable_fields)) {
        continue;
      }

      $main_property_name = $mappable_fields[$field_name]->getFieldStorageDefinition()->getMainPropertyName();
      $mapping = $form_state->getValue([
        'field_mappings',
        $field_name,
        $main_property_name,
      ]);
      if ($this->isConstantValueMapping($mapping)) {
        $form_state->setErrorByName($field_name, $this->t('The @field_name field cannot be mapped to a constant value.', [
          '@field_name' => $mappable_fields[$field_name]->getLabel(),
        ]));
      }
    }
  }

}
