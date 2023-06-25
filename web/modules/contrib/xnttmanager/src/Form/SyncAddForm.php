<?php

namespace Drupal\xnttmanager\Form;

use Drupal\Core\Form\FormStateInterface;

/**
 * Class syncAddForm.
 *
 * Provides the add form for external entity synchronization crons.
 */
class SyncAddForm extends SyncFormBase {

  /**
   * {@inheritdoc}
   */
  protected function actions(array $form, FormStateInterface $form_state) {
    $actions = parent::actions($form, $form_state);
    $actions['submit']['#value'] = $this->t('Create a synchronization cron');
    return $actions;
  }

}
