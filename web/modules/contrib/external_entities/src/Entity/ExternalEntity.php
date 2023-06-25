<?php

namespace Drupal\external_entities\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\external_entities\Event\ExternalEntitiesEvents;
use Drupal\external_entities\Event\ExternalEntityExtractRawDataEvent;
use Drupal\external_entities\ExternalEntityInterface;
use Drupal\external_entities\ExternalEntityTypeInterface;

/**
 * Defines the external entity class.
 *
 * @see external_entities_entity_type_build()
 */
class ExternalEntity extends ContentEntityBase implements ExternalEntityInterface {

  /**
   * {@inheritdoc}
   */
  public function getExternalEntityType() {
    return $this
      ->entityTypeManager()
      ->getStorage('external_entity_type')
      ->load($this->getEntityTypeId());
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    return self::defaultBaseFieldDefinitions();
  }

  /**
   * Provides the default base field definitions for external entities.
   *
   * @return \Drupal\Core\Field\FieldDefinitionInterface[]
   *   An array of base field definitions for the entity type, keyed by field
   *   name.
   */
  public static function defaultBaseFieldDefinitions() {
    $fields = [];

    $fields['id'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('ID'))
      ->setReadOnly(TRUE);

    $fields['uuid'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('UUID'))
      ->setReadOnly(TRUE);

    $fields['title'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Title'))
      ->setRequired(TRUE)
      ->setDisplayOptions('view', [
        'type' => 'string',
        'label' => 'hidden',
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -5,
      ])
      ->setDisplayConfigurable('form', TRUE);

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public static function bundleFieldDefinitions(EntityTypeInterface $entity_type, $bundle, array $base_field_definitions) {
    $fields = parent::bundleFieldDefinitions($entity_type, $bundle, $base_field_definitions);

    /* @var \Drupal\external_entities\ExternalEntityTypeInterface $external_entity_type */
    $external_entity_type = \Drupal::entityTypeManager()
      ->getStorage('external_entity_type')
      ->load($entity_type->id());
    if ($external_entity_type && $external_entity_type->isAnnotatable()) {
      // Add the annotation reference field.
      $fields[ExternalEntityInterface::ANNOTATION_FIELD] = BaseFieldDefinition::create('entity_reference')
        ->setLabel(t('Annotation'))
        ->setDescription(t('The annotation entity.'))
        ->setSetting('target_type', $external_entity_type->getAnnotationEntityTypeId())
        ->setSetting('handler', 'default')
        ->setSetting('handler_settings', [
          'target_bundles' => [$external_entity_type->getAnnotationBundleId()],
        ])
        ->setDisplayOptions('form', [
          'type' => 'entity_reference_autocomplete',
          'weight' => 5,
          'settings' => [
            'match_operator' => 'CONTAINS',
            'size' => '60',
            'placeholder' => '',
          ],
        ])
        ->setDisplayConfigurable('form', TRUE)
        ->setDisplayOptions('view', [
          'label' => t('Annotation'),
          'type' => 'entity_reference_label',
          'weight' => 0,
        ])
        ->setDisplayConfigurable('view', TRUE);

      // Have the external entity inherit its annotation fields.
      if ($external_entity_type->inheritsAnnotationFields()) {
        $inherited_fields = static::getInheritedAnnotationFields($external_entity_type);
        $field_prefix = ExternalEntityInterface::ANNOTATION_FIELD_PREFIX;
        foreach ($inherited_fields as $field) {
          $field_definition = BaseFieldDefinition::createFromFieldStorageDefinition($field->getFieldStorageDefinition())
            ->setName($field_prefix . $field->getName())
            ->setReadOnly(TRUE)
            ->setComputed(TRUE)
            ->setLabel($field->getLabel())
            ->setDisplayConfigurable('view', $field->isDisplayConfigurable('view'));
          $fields[$field_prefix . $field->getName()] = $field_definition;
        }
      }
    }

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function toRawData() {
    // Not using $this->>toArray() here because we don't want computed values.
    $entity_values = [];
    foreach ($this->getFields(FALSE) as $name => $property) {
      $entity_values[$name] = $property->getValue();
    }

    $raw_data = $this
      ->getExternalEntityType()
      ->getFieldMapper()
      ->createRawDataFromEntityValues($entity_values);

    // Allow other modules to perform custom extraction logic.
    $event = new ExternalEntityExtractRawDataEvent($this, $raw_data);
    \Drupal::service('event_dispatcher')->dispatch($event, ExternalEntitiesEvents::EXTRACT_RAW_DATA);

    return $event->getRawData();
  }

  /**
   * {@inheritdoc}
   */
  public function getAnnotation() {
    $external_entity_type = $this->getExternalEntityType();
    if ($external_entity_type->isAnnotatable()) {
      $properties = [
        $external_entity_type->getAnnotationFieldName() => $this->id(),
      ];

      $bundle_key = $this
        ->entityTypeManager()
        ->getDefinition($external_entity_type->getAnnotationEntityTypeId())
        ->getKey('bundle');
      if ($bundle_key) {
        $properties[$bundle_key] = $external_entity_type->getAnnotationBundleId();
      }

      $annotation = $this->entityTypeManager()
        ->getStorage($external_entity_type->getAnnotationEntityTypeId())
        ->loadByProperties($properties);
      if (!empty($annotation)) {
        return array_shift($annotation);
      }
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function mapAnnotationFields(ContentEntityInterface $annotation = NULL) {
    $external_entity_type = $this->getExternalEntityType();
    if ($external_entity_type->isAnnotatable()) {
      if (!$annotation) {
        $annotation = $this->getAnnotation();
      }

      if ($annotation) {
        $this->set(ExternalEntityInterface::ANNOTATION_FIELD, $annotation->id());
        if ($external_entity_type->inheritsAnnotationFields()) {
          $inherited_fields = static::getInheritedAnnotationFields($external_entity_type);
          $field_prefix = ExternalEntityInterface::ANNOTATION_FIELD_PREFIX;
          foreach ($inherited_fields as $field_name => $inherited_field) {
            $value = $annotation->get($field_name)->getValue();
            $this->set($field_prefix . $field_name, $value);
          }
        }
      }
    }

    return $this;
  }

  /**
   * Gets the fields that can be inherited by the external entity.
   *
   * @param \Drupal\external_entities\ExternalEntityTypeInterface $type
   *   The type of the external entity.
   *
   * @return \Drupal\Core\Field\FieldDefinitionInterface[]
   *   An array of field definitions, keyed by field name.
   *
   * @see \Drupal\Core\Entity\EntityManagerInterface::getFieldDefinitions()
   */
  public static function getInheritedAnnotationFields(ExternalEntityTypeInterface $type) {
    $inherited_fields = [];

    $field_definitions = \Drupal::service('entity_field.manager')->getFieldDefinitions($type->getAnnotationEntityTypeId(), $type->getAnnotationBundleId());
    foreach ($field_definitions as $field_name => $field_definition) {
      if ($field_name !== $type->getAnnotationFieldName()) {
        $inherited_fields[$field_name] = $field_definition;
      }
    }

    return $inherited_fields;
  }

  /**
   * @inheritDoc
   */
  public function getCacheMaxAge() {
    return $this->getExternalEntityType()->getPersistentCacheMaxAge();
  }

}
