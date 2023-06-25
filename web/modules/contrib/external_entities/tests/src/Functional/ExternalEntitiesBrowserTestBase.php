<?php

namespace Drupal\Tests\external_entities\Functional;

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Tests\BrowserTestBase;

/**
 * Has some additional helper methods to make test code more readable.
 */
abstract class ExternalEntitiesBrowserTestBase extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'external_entities',
    'external_entities_test',
    'filter',
  ];

  /**
   * {@inheritdoc}
   */
  protected $profile = 'minimal';

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Finds link with specified locator.
   *
   * @param string $locator
   *   Link id, title, text or image alt.
   *
   * @return \Behat\Mink\Element\NodeElement|null
   *   The link node element.
   */
  public function findLink($locator) {
    return $this->getSession()->getPage()->findLink($locator);
  }

  /**
   * Clicks a link identified via partial href using xpath.
   *
   * As the Rules UI pages become more complex, with multiple links and buttons
   * containing the same text, it may get difficult to use clickLink('text', N)
   * where N is the index position on the page, as the index of a given link
   * varies depending on other rules. It is clearer to read and more
   * future-proof to find the link via a known url fragment.
   *
   * @param string $href
   *   The href, or a unique part of it.
   */
  public function clickLinkByHref($href) {
    $this->getSession()->getPage()->find('xpath', './/a[contains(@href, "' . $href . '")]')->click();
  }

  /**
   * Finds field (input, textarea, select) with specified locator.
   *
   * @param string $locator
   *   Input id, name or label.
   *
   * @return \Behat\Mink\Element\NodeElement|null
   *   The input field element.
   */
  public function findField($locator) {
    return $this->getSession()->getPage()->findField($locator);
  }

  /**
   * Finds button with specified locator.
   *
   * @param string $locator
   *   Button id, value or alt.
   *
   * @return \Behat\Mink\Element\NodeElement|null
   *   The button node element.
   */
  public function findButton($locator) {
    return $this->getSession()->getPage()->findButton($locator);
  }

  /**
   * Presses button with specified locator.
   *
   * @param string $locator
   *   Button id, value or alt.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   */
  public function pressButton($locator) {
    $this->getSession()->getPage()->pressButton($locator);
  }

  /**
   * Fills in field (input, textarea, select) with specified locator.
   *
   * @param string $locator
   *   Input id, name or label.
   * @param string $value
   *   Value.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   *
   * @see \Behat\Mink\Element\NodeElement::setValue
   */
  public function fillField($locator, $value) {
    $this->getSession()->getPage()->fillField($locator, $value);
  }

  /**
   * Fills in field (input, textarea, select) with specified locator.
   *
   * @param string $locator
   *   Input id, name or label.
   * @param string $value
   *   Value.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   */
  public function selectFieldOption($locator, $value) {
    $this->getSession()->getPage()->selectFieldOption($locator, $value);
  }

  /**
   * Create a field.
   */
  public function createField($bundle, $field_name, $field_type, $multiple = FALSE, $required = FALSE) {
    $field_storage = FieldStorageConfig::create([
      'field_name' => $field_name,
      'entity_type' => $bundle,
      'type' => $field_type,
      'cardinality' => $multiple ? FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED : 1,
    ]);
    $field_storage->save();

    $field_config = FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => $bundle,
      'required' => $required,
      'label' => $field_name,
    ]);
    $field_config->save();

    $this->setFieldDisplay($bundle, $field_name);
    $this->setFieldFormDisplay($bundle, $field_name);
  }

  /**
   * Create reference document field.
   */
  protected function createReferenceField($bundle, $field_name, $target_type, $target_bundle, $multiple = FALSE, $required = FALSE) {
    $field_type = 'entity_reference';

    // Create storage.
    $field_storage = FieldStorageConfig::create([
      'field_name' => $field_name,
      'entity_type' => $bundle,
      'type' => $field_type,
      'cardinality' => $multiple ? FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED : 1,
      'settings' => [
        'target_type' => $target_type,
      ],
    ]);
    $field_storage->save();

    // Create instance.
    $field_config = FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => $bundle,
      'label' => $field_name,
      'required' => $required,
      'settings' => [
        'handler' => 'default:' . $target_type,
        'handler_settings' => [
          'target_bundles' => [
            $target_bundle => $target_bundle,
          ],
        ],
      ],
    ]);
    $field_config->save();

    $this->setFieldDisplay($bundle, $field_name);
    $this->setFieldFormDisplay($bundle, $field_name);
  }

  /**
   * Make field visible on page.
   */
  protected function setFieldDisplay($bundle, $field_name) {
    $storage = \Drupal::entityTypeManager()->getStorage('entity_view_display');

    /** @var \Drupal\Core\Entity\Entity\EntityViewDisplay $view_display */
    $view_display = $storage->load($bundle . '.' . $bundle . '.default');

    if (empty($view_display)) {
      $view_display = EntityViewDisplay::create([
        'targetEntityType' => $bundle,
        'bundle' => $bundle,
        'mode' => 'default',
        'status' => TRUE,
      ]);
    }

    // Make sure it's active.
    if (!$view_display->status()) {
      $view_display->setStatus(TRUE);
    }

    $view_display->setComponent($field_name, [
      'settings' => [],
    ])->save();
  }

  /**
   * Make field visible on form.
   */
  protected function setFieldFormDisplay($bundle, $field_name) {
    $storage = \Drupal::entityTypeManager()->getStorage('entity_form_display');

    /** @var \Drupal\Core\Entity\Entity\EntityFormDisplay $form_display */
    $form_display = $storage->load($bundle . '.' . $bundle . '.default');

    if (empty($form_display)) {
      $form_display = EntityFormDisplay::create([
        'targetEntityType' => $bundle,
        'bundle' => $bundle,
        'mode' => 'default',
        'status' => TRUE,
      ]);
    }

    // Make sure it's active.
    if (!$form_display->status()) {
      $form_display->setStatus(TRUE);
    }

    $form_display->setComponent($field_name, [
      'type' => 'string_textfield',
      'settings' => [],
    ])->save();
  }

  /**
   * Gets the field definition of a field.
   */
  protected function getFieldDefinition($bundle, $field_name) {
    $definitions = $this->getFieldDefinitions($bundle);
    return $definitions[$field_name] ?? NULL;
  }

  /**
   * Gets the definitions of the fields that are candidate for display.
   */
  protected function getFieldDefinitions($bundle) {
    if (!isset($this->fieldDefinitions)) {
      $this->fieldDefinitions[$bundle] = \Drupal::service('entity_field.manager')->getFieldDefinitions($bundle, $bundle);
    }

    return $this->fieldDefinitions[$bundle];
  }

}
