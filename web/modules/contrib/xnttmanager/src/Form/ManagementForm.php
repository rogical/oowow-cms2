<?php

namespace Drupal\xnttmanager\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Implements the SimpleForm form controller.
 *
 * This example demonstrates a simple form with a single text input element. We
 * extend FormBase which is the simplest form base class used in Drupal.
 *
 * Selection du type d'external entity
 * Bouton de test de chargement de toutes les entities
 * Bouton de test de chargement+sauveguarde de toutes les external entities
 * Generation de stats sur les entities synchronisées: nombre dispo, nouvelles,
 *   orphœlines
 *
 * @see \Drupal\Core\Form\FormBase
 */
class ManagementForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $xntt_type_list = get_external_entity_type_list();
    $form['xntt_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Select an external entity type'),
      '#options' => $xntt_type_list,
      '#required' => TRUE,
      '#sort_options' => TRUE,
    ];

    $form['xntt_load'] = [
      '#type' => 'markup',
      '#markup' => $this->t(
        'When batch processing, all external entities of the selected type are
        loaded to check if they load fine and a global report is generated.
        Other actions can also be performed by checking the checkboxes below.'
      ),
    ];

    $form['xntt_save'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Save external entities'),
      '#description' => $this->t('Check to save selected external entities during batch processing.'),
      '#default_value' => FALSE,
    ];

    $form['xntt_annotate'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Annotate external entities'),
      '#description' => $this->t('Check to add missing annotation to selected external entities during batch processing.'),
      '#default_value' => FALSE,
    ];

    $form['xntt_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('External Entity identifier'),
      '#description' => $this->t('Enter the identifier of an external entity to inspect.'),
      '#required' => FALSE,
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['process'] = [
      '#type' => 'submit',
      '#name' => 'process',
      '#value' => $this->t('Batch process external entities'),
    ];

    $form['actions']['inspect'] = [
      '#type' => 'submit',
      '#name' => 'inspect',
      '#value' => $this->t('Inspect'),
      '#submit' => [[$this, 'submitInspect']],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'xnttmanager_management_form';
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    if ($form_state->getValue('xntt_annotate')) {
      // Check if external entity type has an annotation.
      $entity_type = \Drupal::service('entity_type.manager')
        ->getStorage('external_entity_type')
        ->load($form_state->getValue('xntt_type'))
      ;
      if (empty($entity_type) || !$entity_type->isAnnotatable()) {
        $form_state->setErrorByName(
          'xntt_annotate',
          $this->t('The selected external entity type does not have annotations.')
        );
      }
    }
  }

  /**
   * Submit method for batch processing.
   *
   * @param array $form
   *   The render array of the currently built form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Object describing the current state of the form.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $params = [
      'xntt_type'  => $form_state->getValue('xntt_type'),
    ];

    $xntt_id = $form_state->getValue('xntt_id');
    if (!empty($xntt_id)) {
      $params['xntt_ids'] = [$xntt_id];
    }

    $actions = ['loading'];
    if ($form_state->getValue('xntt_save')) {
      $params['save'] = TRUE;
      $actions[] = 'saving';
    }

    if ($form_state->getValue('xntt_annotate')) {
      $params['annotate'] = TRUE;
      $actions[] = 'saving';
    }
    if (1 == count($actions)) {
      $title = 'External entity ' . $actions[0];
    }
    else {
      $title = ' and ' . array_pop($actions);
      $title = 'External entity ' . implode(', ', $actions) . $title;
    }

    $this->launchBatchProcessing(
      $form_state,
      $params,
      $title
    );
  }

  /**
   * Inspect external entity fields.
   *
   * @param array $form
   *   The render array of the currently built form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Object describing the current state of the form.
   */
  public function submitInspect(array &$form, FormStateInterface $form_state) {
    $xntt_type = $form_state->getValue('xntt_type');
    $xntt_id = $form_state->getValue('xntt_id');
    $url = Url::fromRoute(
      'xnttmanager.inspect',
      [
        'xntt_type' => $xntt_type,
        'xntt_id' => $xntt_id,
      ]
    );
    $form_state->setRedirectUrl($url);
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
