<?php

namespace Drupal\xnttmanager\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Controller for external entity field inspector.
 */
class XnttInspector extends ControllerBase {

  /**
   * {@inheritdoc}
   */
  protected function getModuleName() {
    return 'xnttmanager';
  }

  /**
   * Performs external entity field inspection and display a result table.
   *
   * @param string $xntt_type
   *   The external entity type machine name.
   * @param string $xntt_id
   *   An optional external entity identifier to load for example values. If
   *   empty, the first entity of the external entity type will be used.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
   *   If the parameters are invalid.
   */
  public function inspect($xntt_type, $xntt_id = '') {
    // Make sure we got an appropriate value.
    if (!preg_match('/^[\w\-]+$/', $xntt_type)) {
      throw new NotFoundHttpException();
    }

    $entity_type = \Drupal::entityTypeManager()
      ->getStorage('external_entity_type')
      ->load($xntt_type)
    ;
    if (empty($entity_type)) {
      throw new NotFoundHttpException();
    }
    $storage_client = $entity_type->getStorageClient();
    $field_mapper = $entity_type->getFieldMapper();
    $id_field = current(array_values($field_mapper->getFieldMapping('id')));
    // Check if the user provided an identifier.
    if (empty($xntt_id)) {
      // Nope, use the first entity.
      $xntt_id = (current($storage_client->query([], [], 0, 1)) ?? [$id_field => 0])[$id_field];
      $xntt_data = current($storage_client->loadMultiple([$xntt_id]));
      if (empty($xntt_data)) {
        $xntt_data = [];
        \Drupal::messenger()->addWarning($this->t(
          'No entity found for the given external entity type.'
        ));
      }
    }
    else {
      // Yep, try to load the corresponding entity.
      $xntt_data = current($storage_client->loadMultiple([$xntt_id]));
      if (empty($xntt_data)) {
        $xntt_data = [];
        \Drupal::messenger()->addWarning($this->t(
          'The given entity identifier was not found or its corresponding entity is empty.'
        ));
      }
    }
    // $xntt_data is an array supposed to contain raw field names with their
    // associated values for the selected entity (as an example).
    // Get external entity field mapping.
    // The $mapping array will contain raw field names as keys and an array of
    // their associated Drupal field names as values since a same raw field can
    // be mapped to more than one Drupal field.
    $mapping = [];
    $invalid_mapping = [];
    $dfield_mapping = $field_mapper->getFieldMappings();
    foreach ($dfield_mapping as $dfield => $values) {
      foreach (array_values($values) as $raw_field) {
        $mapping[$raw_field] = $mapping[$raw_field] ?? [];
        $mapping[$raw_field][] = $dfield;
      }
    }

    // Get the list of Drupal fields that have not been mapped.
    $field_manager = \Drupal::service('entity_field.manager');
    $free_dfields = $field_manager->getFieldDefinitions($xntt_type, $xntt_type);
    foreach ($free_dfields as $field => $def) {
      if (array_key_exists($field, $dfield_mapping)) {
        unset($free_dfields[$field]);
      }
    }

    $render_array['xntt_type'] = [
      '#markup' => $this->t(
        '<h2>&quot;@entity_type&quot; Inspection</h2>',
        ['@entity_type' => $entity_type->getLabel()]
      ),
    ];

    $rows = [];
    $row_i = 0;
    foreach ($xntt_data as $field => $value) {
      $rows[$row_i] = [
        'field' => $field,
        'mapping' => empty($mapping[$field])
          ? '-'
          : implode(', ', $mapping[$field])
        ,
        'value' => '(n/a)',
      ];
      if (is_scalar($value)) {
        if (is_string($value)) {
          // Check if the value is binary or too long to be displayed as is.
          $is_binary = preg_match('#[\x00-\x07\x0E-\x1F\x81\x8D\x8F\x90\x9D]#', $value);
          $only_regular_chars = preg_replace('#[\b\t\n\v\f\r\x20-\x7E]#', '', $value);
          // It is binary if it contains binary characters or if it contains more
          // than 13% of non-regular characters such as accents or special signs.
          if ($is_binary
            || (!empty($value) && strlen($only_regular_chars)/strlen($value) > 0.13)
          ) {
            $rows[$row_i]['value'] = $this->t('(binary data)');
          }
          elseif (80 < strlen($value)) {
            $rows[$row_i]['value'] = substr($value, 0, 80) . '(...)';
          }
          else {
            $rows[$row_i]['value'] = $value;
          }
        }
        else {
          $rows[$row_i]['value'] = $value;
        }
      }
      elseif (is_array($value)) {
        $array_data = print_r($value, TRUE);
        if (80 >= strlen($array_data)) {
          $rows[$row_i]['value'] = $array_data;
        }
        else {
          $rows[$row_i]['value'] = substr($array_data, 0, 80) . '(...)';
        }
      }
      elseif (is_object($value)) {
        $rows[$row_i]['value'] = '(' . get_class($value) . ' object)';
      }
      ++$row_i;
    }
    // Add Drupal fields mapped with incorrect raw field names.
    foreach ($mapping as $raw_field => $dfields) {
      if (!array_key_exists($raw_field, $xntt_data)) {
        foreach ($dfields as $dfield) {
          if (str_starts_with(trim($raw_field), '+')) {
            // Constant.
            $field_mapping = $this->t(
              '@field (mapped to constant value "@raw_field")',
              [
                '@field' => $dfield,
                '@raw_field' => substr(trim($raw_field), 1),
              ]
            );
          }
          else {
            // @todo: add CSS to highlight invalid mappings as errors.
            $field_mapping = $this->t(
              '@field (mapped to missing raw field "@raw_field")',
              [
                '@field' => $dfield,
                '@raw_field' => $raw_field,
              ]
            );
          }
          $rows[$row_i] = [
            'field' => '-',
            'mapping' => $field_mapping,
            'value' => '',
          ];
          ++$row_i;
        }
      }
    }

    // Add unmapped Drupal fields.
    foreach ($free_dfields as $dfield => $value) {
      $rows[$row_i] = [
        'field' => '-',
        'mapping' => $dfield,
        'value' => '',
      ];
      ++$row_i;
    }

    $url = Url::fromRoute("entity.$xntt_type.collection");
    $link = Link::createFromRoute($this->t('@path', ['@path' => $url->toString()]), "entity.$xntt_type.collection");
    $path_render = $link->toRenderable();
    $path_render['#prefix'] = $this->t("Entity list path: ");

    $render_array['inspection'] = [
      'path' => $path_render,
      'fields' => [
        '#theme' => 'table',
        '#header' => [
          $this->t('Raw Field Name'),
          $this->t('Mapped Drupal Field'),
          $this->t('Example Value'),
        ],
        '#rows' => $rows,
      ]
    ];

    return $render_array;
  }

}
