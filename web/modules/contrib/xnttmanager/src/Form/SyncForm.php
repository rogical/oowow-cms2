<?php

namespace Drupal\xnttmanager\Form;

use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Form\FormStateInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\NodeType;


/**
 * Class syncAddForm.
 *
 * Provides a generic synchronization form for external entities.
 * This form can run a synchronization as well as manage existing
 * synchronization crons.
 */
class SyncForm extends SyncFormBase {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $sync_xntt_list = get_synchronized_external_entity_list();
    $sync = $this->entity;
    
    // Check if an external entity type has already been selected.
    $selected_xntt_type = $form_state->getValue('xnttType');
    if (!empty($selected_xntt_type)) {
      // An entity type has been selected.
      // Check if we don't have a sync entity loaded or if the loaded type does
      // not correspond to the selected type
      // Check if we have a sync entity to load for that type.
      if (array_key_exists($selected_xntt_type, $sync_xntt_list)) {
        // Load corresponding sync cron entity.
        $sync = \Drupal::service('entity_type.manager')
          ->getStorage('xnttsync')
          ->load($selected_xntt_type)
        ;
        $this->setEntity($sync);
        // Update inputs.
        $ui = $form_state->getUserInput();
        foreach ($sync->toArray() as $property => $value) {
          if (array_key_exists($property, $ui)) {
            // @todo: for checkboxes, we need to return an empty value and not
            // '0', which is the value for FALSE in object boolean properties.
            // Here we return NULL for empty values. It works as long as we
            // don't have fields that can have '0' as value.
            $ui[$property] = empty($value) ? NULL : $value;
          }
        }
        // Special case of frequency.
        $ui['frequency'] = $this->formatFrequency($ui['frequency']);
        // Set new inputs to update form field displayed values.
        $form_state->setUserInput($ui);
      }
      elseif (!empty($sync->id())) {
        // Nothing to load but previous sync object remains.
        // Recreate an empty entity from form inputs.
        $sync = $this->buildEntity($form, $form_state);
        $this->setEntity($sync);
      }
    }

    $form = parent::buildForm($form, $form_state);
    // No frequency required for immediate synchronization (no cron creation).
    $form['xntt_sync_settings']['frequency']['#required'] = FALSE;
    $form['xntt_sync_settings']['frequency']['#description'] = $this->t(
      'This field is required if you want to create a cron. Please note that this frequency depends on the Drupal cron frequency and cannot be more frequent.'
    );
    // Ajax callback to update form according to (non)existing sync cron.
    $form['xnttType']['#ajax'] = [
      'callback' => '::updateFormCallback',
      'wrapper' => 'setting-wrapper',
    ];
    $form['xnttType']['#disabled'] = FALSE;

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    switch ($form_state->getTriggeringElement()['#name']) {
      case 'sync_add':
      case 'sync_update':
        if (empty($form_state->getValue('frequency'))) {
          $form_state->setErrorByName(
            'frequency',
            $this->t('A frequency is required to create or update syncrhonization crons.')
          );
        }
        break;

      default:
    }
  }

  /**
   * Callback for the select element.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   The form structure.
   */
  public function updateFormCallback(array $form, FormStateInterface $form_state) {
    return $form['xntt_sync_settings'];
  }

  /**
   * {@inheritdoc}
   */
  protected function actions(array $form, FormStateInterface $form_state) {
    $actions = parent::actions($form, $form_state);
    $sync = $this->entity;
    $sync_xntt_list = get_synchronized_external_entity_list();
    $values_with_sync = [];
    foreach (array_keys($sync_xntt_list) as $x) {
      $values_with_sync[] = 'or';
      $values_with_sync[] = ['value' => $x];
    }
    if (empty($values_with_sync)) {
      $values_with_sync = ['value' => '/'];
    } else {
      array_shift($values_with_sync);
    }

    // Remove original button.
    unset($actions['submit']);

    $actions['sync_now'] = [
      '#type' => 'submit',
      '#name' => 'sync_now',
      '#value' => $this->t('Synchronize now'),
      '#button_type' => 'primary',
      '#submit' => [
        '::submitForm',
        '::performSync',
      ],
    ];
    $actions['sync_add'] = [
      '#type' => 'submit',
      '#name' => 'sync_add',
      '#value' => $this->t('Create a cron'),
      '#button_type' => 'primary',
      '#submit' => [
        '::submitForm',
        '::save',
      ],
      '#states' => [
        'invisible' => [
          'select[name="xnttType"]' => $values_with_sync,
        ],
      ],
    ];
    $actions['sync_update'] = [
      '#type' => 'submit',
      '#name' => 'sync_update',
      '#value' => $this->t('Update cron'),
      '#button_type' => 'primary',
      '#submit' => [
        '::submitForm',
        '::save',
      ],
      '#states' => [
        'visible' => [
          'select[name="xnttType"]' => $values_with_sync,
        ],
      ],
    ];

    // Check for missing content and orphaned content.
    $actions['sync_stats'] = [
      '#type' => 'submit',
      '#name' => 'sync_stats',
      '#value' => $this->t('Get statistics'),
      '#button_type' => 'primary',
      '#submit' => [
        '::submitForm',
        '::performStats',
      ],
    ];
    return $actions;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Get external entity type.
    $xntt_type = $form_state->getValue('xnttType');
    $xntt = \Drupal::service('entity_type.manager')
      ->getStorage('external_entity_type')
      ->load($xntt_type)
    ;

    // Get content type and bundle to use.
    list($content_type, $bundle_name) = explode(
      '/',
      $form_state->getValue('contentTarget')
    );
    // Check if an existing bundle was set.
    if (empty($content_type)) {
      $content_type = 'node';
      $form_state->setValue('sync_supplement_fields', TRUE);
    }
    $bundles_info = \Drupal::service('entity_type.bundle.info')
      ->getAllBundleInfo()
    ;
    // Generate a new bundle name if needed.
    if (empty($bundle_name)) {
      $type_suffix = 1;
      $bundle_name = $content_type . '_' . $xntt_type;
      while (array_key_exists($bundle_name, $bundles_info[$content_type])) {
        $bundle_name = $content_type . '_' . $xntt_type . $type_suffix++;
      }
      $form_state->setValue('sync_supplement_fields', TRUE);
    }
    if (!array_key_exists($bundle_name, $bundles_info[$content_type])) {
      $bundle_label = $xntt->getLabel() . ' Synchronized Content';
      // Create new bundle.
      $bundle = NodeType::create([
        'type' => $bundle_name,
        'label' => $bundle_label,
        'name' => $bundle_label,
        'description' =>
          'Synchronized content for external entity type '
          . $xntt->getLabel()
        ,
        'revision' => FALSE,
      ]);
      $bundle->save();
      $this->logger('xnttmanager')->notice(
        'New content type auto-created: '
        . $bundle_name
      );
      $this->messenger()->addMessage(
        $this->t(
          'A new content type has been created: %content_type (%machine_name)',
          [
            '%content_type' => $bundle_label,
            '%machine_name' => $bundle_name,
          ]
        )
      );
    }
    // We need the new value for next processes.
    $form_state->setValue('contentTarget', $content_type . '/' . $bundle_name);

    // Check for xnttid field and missing fields.
    $field_manager = \Drupal::service('entity_field.manager');
    $xntt_fields = $field_manager->getFieldDefinitions($xntt_type, $xntt_type);
    $local_fields = $field_manager->getFieldDefinitions(
      $content_type,
      $bundle_name
    );
    
    // If xnttid field is missing, add it.
    if (!array_key_exists('xnttid', $local_fields)) {
      // @todo: use an entity reference type instead of a string type.
      FieldStorageConfig::create([
        'field_name' => 'xnttid',
        'entity_type' => $content_type,
        'type' => 'string',
      ])->save();
      $id_field_config = [
        'field_name' => 'xnttid',
        'entity_type' => $content_type,
        'bundle' => $bundle_name,
        'label' => 'External Entity Identifier',
        'description' => 'For synchronization internal use. Do not remove or edit.',
        'required' => FALSE,
        'esttings' => [
           'max_length' => 255,
           'is_ascii' => FALSE,
           'case_sensitive' => FALSE,
         ],
      ];
      FieldConfig::create($id_field_config)->save();
      $this->logger('xnttmanager')->notice(
        'Missing identifier field added to '
        . $bundle_name
        . ': xnttid'
      );
      $this->messenger->addMessage(
        $this->t(
          'Added missing identifier field "xnttid" to content type %bundle_name',
          [
            '%bundle_name' => $bundle_name,
          ]
        )
      );
    }
    // Other missing fields.
    if (!empty($form_state->getValue('sync_supplement_fields'))) {
      // Add missing fields.
      foreach ($xntt_fields as $field_name => $field_def) {
        // Skip base fields.
        if ($field_def instanceof BaseFieldDefinition) {
          continue;
        }
        if (!array_key_exists($field_name, $local_fields)) {
          // Add a cloned field.
          FieldStorageConfig::create([
            'field_name' => $field_name,
            'entity_type' => $content_type,
            'type' => $field_def->getType(),
          ])->save();
          $clone_field = $field_def->createDuplicate();
          $clone_field->set('dependencies', []);
          $clone_field->set('id', NULL);
          $clone_field->set('entity_type', $content_type);
          $clone_field->set('bundle', $bundle_name);
          $clone_field->save();
          $this->logger('xnttmanager')->notice(
            'Missing field added to '
            . $bundle_name
            . ': '
            . $field_name
            . '(cloned from '
            . $xntt_type
            . ')'
          );

          $this->messenger->addMessage(
            $this->t(
              'New field added (to content type %bundle_name): %field_name (%field_machine_name)',
              [
                '%bundle_name' => $bundle_name,
                '%field_name' => $clone_field->get('label'),
                '%field_machine_name' => $field_name,
              ]
            )
          );
        }
      }
    }
    // Call parent to initialize sync entity.
    parent::submitForm($form, $form_state);
  }

  /**
   * Launch batch-processing for content synchronization.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function performSync(array $form, FormStateInterface $form_state) {
    $params = [
      'xntt_type'            => $form_state->getValue('xnttType'),
      'content_type'         => $form_state->getValue('contentTarget'),
      'sync_add_missing'     => $form_state->getValue('syncAddMissing'),
      'sync_update_existing' => $form_state->getValue('syncUpdateExisting'),
      'sync_remove_orphans'  => $form_state->getValue('syncRemoveOrphans'),
      'sync'                 => TRUE,
    ];
    $title = 'Synchronize';
    $this->launchBatchProcessing(
      $form_state,
      $params,
      $title
    );
  }

  /**
   * Launch batch-processing for statistics computation.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function performStats(array $form, FormStateInterface $form_state) {
    $params = [
      'xntt_type'  => $form_state->getValue('xnttType'),
      'content_type'  => $form_state->getValue('contentTarget'),
      'sync_stats' => TRUE,
    ];
    $title = 'Synchronization statistics';
    $this->launchBatchProcessing(
      $form_state,
      $params,
      $title
    );
  }

  /**
   * Batch-process all external entities of the given type.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Object describing the current state of the form.
   * @param array $params
   *   The batch parameters.
   * @param string $title
   *   The batch title.
   */
  public function launchBatchProcessing(
    FormStateInterface $form_state,
    array $params,
    string $title
  ) {
    $operations = [
      [
        'bulkExternalEntitiesProcess',
        [$params],
      ],
    ];
    $batch = [
      'title' => $this->t($title),
      'operations' => $operations,
      'finished' => 'bulkExternalEntitiesFinished',
      'progressive' => TRUE,
    ];
    batch_set($batch);
  }

}
