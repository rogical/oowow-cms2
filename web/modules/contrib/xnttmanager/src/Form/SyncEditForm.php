<?php

namespace Drupal\xnttmanager\Form;

use Drupal\Core\Form\FormStateInterface;

/**
 * Class syncEditForm.
 *
 * Provides the edit form for external entity synchronization crons.
 */
class SyncEditForm extends SyncFormBase {

  /**
   * {@inheritdoc}
   */
  protected function actions(array $form, FormStateInterface $form_state) {
    $actions = parent::actions($form, $form_state);
    $actions['submit']['#value'] = $this->t(
      'Update synchronization cron'
    );
    return $actions;
  }

}
