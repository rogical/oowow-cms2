<?php

namespace Drupal\Tests\external_entities\Kernel;

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\external_entities\Entity\ExternalEntity;
use Drupal\external_entities\ExternalEntityTypeInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * Test integration with IEF.
 *
 * @group ExternalEntities
 * @requires module inline_entity_form
 */
class IefIntegrationTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'external_entities',
    'inline_entity_form',
    'node',
    'user',
    'system',
    'field',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('node');
    $this->installConfig(['system']);
    $this->setUpCurrentUser([], [], TRUE);
    $this->storage = $this->container->get('entity_type.manager')->getStorage('external_entity_type');

    $annotation = NodeType::create([
      'type' => 'annotation',
      'name' => 'annotation',
    ]);
    $annotation->save();

    $type = $this->container->get('entity_type.manager')->getStorage('external_entity_type')->create([
      'id' => 'simple_external_entity',
      'label' => 'Simple external entity',
      'label_plural' => 'Simple external entities',
      'annotation_entity_type_id' => 'node',
      'annotation_bundle_id' => 'annotation',
      'annotation_field_name' => 'external_entity',
    ]);
    assert($type instanceof ExternalEntityTypeInterface);

    $type->setStorageClientId('rest');
    $type->setStorageClientConfig([
      'endpoint' => 'http://test.tld',
      'response_format' => 'json',
    ]);

    $type->setFieldMapperId('simple');
    $type->setFieldMapperConfig([
      'field_mappings' => [
        'id' => [
          'value' => 'uuid',
        ],
        'uuid' => [
          'value' => 'uuid',
        ],
        'title' => [
          'value' => 'label',
        ],
      ],
    ]);
    $type->save();

    // Create storage.
    $field_storage = FieldStorageConfig::create([
      'field_name' => 'external_entity',
      'entity_type' => 'node',
      'type' => 'entity_reference',
      'cardinality' => 1,
      'settings' => [
        'target_type' => 'simple_external_entity',
      ],
    ]);
    $field_storage->save();

    // Create instance.
    $field_config = FieldConfig::create([
      'field_storage' => $field_storage,
      'entity_type' => 'node',
      'bundle' => 'annotation',
      'label' => 'external_entity',
      'settings' => [
        'handler' => 'default',
        'handler_settings' => [
          'target_bundles' => [
            'simple_extenral_entity' => 'simple_external_entity',
          ],
        ],
      ],
    ]);
    $field_config->save();

    $form_display = EntityFormDisplay::create([
      'targetEntityType' => 'simple_external_entity',
      'bundle' => 'simple_external_entity',
      'mode' => 'default',
      'status' => TRUE,
    ]);

    $form_display->setComponent('annotation', [
      'type' => 'inline_entity_form_simple',
      'settings' => [
        'form_mode' => 'default',
      ],
    ])->save();
  }

  /**
   * Test loading form display.
   *
   * Test if external entity form loads with no annotation for entity
   * and ief enabled.
   */
  public function testEntityFormRendering() {
    $external_entity = ExternalEntity::create([
      'type' => 'simple_external_entity',
      'uuid' => '2596b1ba-43bb-4440-9f0c-f1974f733337',
      'id' => '2596b1ba-43bb-4440-9f0c-f1974f733337',
      'title' => 'Just another short string',
    ]);
    $form = $this->container->get('entity.form_builder')->getForm($external_entity, 'default');
    $this->assertArrayHasKey('annotation', $form);
  }

}
