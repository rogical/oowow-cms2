<?php

namespace Drupal\Tests\external_entities\Functional;

use Drupal\filter\Entity\FilterFormat;

/**
 * Tests creation of a simple external entity.
 *
 * @group ExternalEntities
 */
class SimpleExternalEntityTest extends ExternalEntitiesBrowserTestBase {

  /**
   * A user with administration permissions.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $account;

  /**
   * The entity storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $storage;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    global $base_url;
    $this->storage = $this->container->get('entity_type.manager')->getStorage('external_entity_type');

    /** @var \Drupal\external_entities\Entity\ExternalEntityType $ref */
    $ref = $this->container->get('entity_type.manager')->getStorage('external_entity_type')->create([
      'id' => 'ref',
      'label' => 'Ref',
      'label_plural' => 'Refs',
    ]);

    $ref->setStorageClientId('rest');
    $ref->setStorageClientConfig([
      'endpoint' => $base_url . '/external-entities-test/ref',
      'response_format' => 'json',
    ]);

    $ref->setFieldMapperId('simple');
    $ref->setFieldMapperConfig([
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
    $ref->save();
    $full_html_format = FilterFormat::create([
      'format' => 'full_html',
      'name' => 'Full HTML',
      'weight' => 1,
      'filters' => [],
    ]);
    $full_html_format->save();
    /** @var \Drupal\external_entities\Entity\ExternalEntityType $type */
    $type = $this->container->get('entity_type.manager')->getStorage('external_entity_type')->create([
      'id' => 'simple_external_entity',
      'label' => 'Simple external entity',
      'label_plural' => 'Simple external entities',
    ]);

    $type->setStorageClientId('rest');
    $type->setStorageClientConfig([
      'endpoint' => $base_url . '/external-entities-test/simple',
      'response_format' => 'json',
    ]);

    $type->setFieldMapperId('simple');
    $type->save();

    // Add fields.
    $this->createField('simple_external_entity', 'plain_text', 'string');
    $this->createField('simple_external_entity', 'fixed_string', 'string');
    $this->createField('simple_external_entity', 'a_boolean', 'boolean');
    $this->createField('simple_external_entity', 'a_rich_text', 'text');
    $this->createField('simple_external_entity', 'a_plain_text', 'text');
    $this->createReferenceField('simple_external_entity', 'ref', 'ref', 'ref', TRUE);

    $type->setFieldMapperConfig([
      'field_mappings' => [
        'id' => [
          'value' => 'uuid',
        ],
        'uuid' => [
          'value' => 'uuid',
        ],
        'title' => [
          'value' => 'title',
        ],
        'plain_text' => [
          'value' => 'short_text',
        ],
        'fixed_string' => [
          'value' => '+A fixed string',
        ],
        'a_rich_text' => [
          'value' => 'rich_text',
          'format' => '+full_html',
        ],
        'a_plain_text' => [
          'value' => 'rich_text_2',
          'format' => '+plain_text',
        ],
        'a_boolean' => [
          'value' => 'status',
        ],
        'ref' => [
          'target_id' => 'refs/*',
        ],
      ],
    ]);
    $type->save();

    // Create the user with all needed permissions.
    $this->account = $this->drupalCreateUser([
      'administer external entity types',
      'view simple_external_entity external entity',
      'view simple_external_entity external entity collection',
    ]);
    $this->drupalLogin($this->account);
  }

  /**
   * Tests creation of a rule and then triggering its execution.
   */
  public function testSimpleExternalEntity() {
    /** @var \Drupal\Tests\WebAssert $assert */
    $assert = $this->assertSession();

    $this->drupalGet('admin/structure/external-entity-types');
    $assert->pageTextContains('Simple external entity');

    $this->drupalGet('simple-external-entity');
    $assert->pageTextContains('Simple title 1');
    $assert->pageTextContains('Simple title 2');

    $this->drupalGet('simple-external-entity/2596b1ba-43bb-4440-9f0c-f1974f733336');
    $assert->pageTextContains('Simple title 1');
    $assert->pageTextContains('Just a short string');
    $assert->pageTextContains('A fixed string');
    $assert->pageTextContains('Term 1');
    $assert->pageTextContains('Term 2');
    $assert->pageTextContains('On');
    $assert->pageTextContains('Some HTML tags');
    $assert->pageTextNotContains('<h2>Some HTML tags</h2>');
    $assert->pageTextContains('<h2>Other HTML tags</h2>');

    $this->drupalGet('simple-external-entity/2596b1ba-43bb-4440-9f0c-f1974f733337');
    $assert->pageTextContains('Simple title 2');
    $assert->pageTextContains('Just another short string');
    $assert->pageTextContains('A fixed string');
    $assert->pageTextContains('Off');

    $this->drupalGet('simple-external-entity/2596b1ba-43bb-4440-9f0c-f1974f733336/edit');
    $this->fillField('a_boolean', FALSE);
    $this->pressButton('edit-submit');

    $this->drupalGet('simple-external-entity/2596b1ba-43bb-4440-9f0c-f1974f733336');
    $assert->pageTextContains('Simple title 1');
    $assert->pageTextContains('Just a short string');
    $assert->pageTextContains('A fixed string');
    $assert->pageTextContains('Term 1');
    $assert->pageTextContains('Term 2');
    $assert->pageTextContains('Off');
  }

}
