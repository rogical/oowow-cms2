<?php

namespace Drupal\xnttmanager\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class syncFormBase.
 *
 * Contains the base form fields and methods for adding, editing and managing
 * external entity syncrhonization crons.
 */
class SyncFormBase extends EntityForm {

  /**
   * An entity query factory for the sync entity type.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $entityStorage;

  /**
   * Construct the syncFormBase.
   *
   * @param \Drupal\Core\Entity\EntityStorageInterface $entity_storage
   *   An entity query factory for the synchronization cron entity type.
   */
  public function __construct(EntityStorageInterface $entity_storage) {
    $this->entityStorage = $entity_storage;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $form = new static(
      $container->get('entity_type.manager')->getStorage('xnttsync')
    );
    $form->setMessenger($container->get('messenger'));
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Get anything we need from the base class.
    $form = parent::buildForm($form, $form_state);

    $xntt_type_list = get_external_entity_type_list();
    $content_type_list = get_content_entity_type_list();
    $sync_xntt_list = get_synchronized_external_entity_list();
    $sync = $this->entity;

    $form['xnttType'] = [
      '#type' => 'select',
      '#name' => 'xnttType',
      '#title' => $this->t('Select an external entity type'),
      '#options' => $xntt_type_list,
      '#required' => TRUE,
      '#sort_options' => TRUE,
      '#default_value' => $sync->id(),
      '#disabled' => !$sync->isNew(),
    ];

    $form['xntt_sync_settings'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'setting-wrapper'],
    ];

    $form['xntt_sync_settings']['contentTarget'] = [
      '#type' => 'select',
      '#name' => 'contentTarget',
      '#title' => $this->t('Select a target (local) content type'),
      '#options' => $content_type_list,
      '#required' => FALSE,
      '#empty_option' => $this->t('- Auto-create a new content type -'),
      '#empty_value' => '',
      '#sort_options' => TRUE,
      '#default_value' => $sync->get('contentTarget') ?? '',
    ];
    
    // @todo: add a radio to choose between modifying local Drupal content and
    // remote content through xntt save() feature.

    $form['xntt_sync_settings']['sync_supplement_fields'] = [
      '#type' => 'checkbox',
      '#title' => $this->t(
        'Add missing fields to content type before synchronization'
      ),
      '#description' => $this->t(
        'Implicit when a new content type is created. Fields will only be added when saving the corresponding cron or when manually launching a synchronization.'
      ),
      '#default_value' => TRUE,
      '#states' => [
        'disabled' => [
          'select[name="contentTarget"]' => ['value' => ''],
        ],
        'checked' => [
          'select[name="contentTarget"]' => ['value' => ''],
        ],
      ],
    ];

    $form['xntt_sync_settings']['syncAddMissing'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Add missing content'),
      '#default_value' => $sync->get('syncAddMissing') ?? 1,
    ];

    $form['xntt_sync_settings']['syncUpdateExisting'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Update existing content'),
      '#default_value' => $sync->get('syncUpdateExisting') ?? 1,
    ];

    $form['xntt_sync_settings']['syncRemoveOrphans'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Remove orphaned content'),
      '#default_value' => $sync->get('syncRemoveOrphans') ?? 1,
    ];

    $frequency = $this->formatFrequency((int)$sync->get('frequency'));
    $form['xntt_sync_settings']['frequency'] = [
      '#type' => 'textfield',
      '#title' => $this->t(
        'Synchronization frequency (number followed by either "d" for days, "h" for hours or "m" for minutes)'
      ),
      '#size' => 10,
      '#maxlength' => 10,
      '#pattern' => '\d+[dhms]?',
      '#required' => TRUE,
      '#default_value' => $frequency,
      '#description' => $this->t(
        'Please note that this frequency depends on the Drupal cron frequency and cannot be more frequent. If no time unit is specified, seconds are assumed.'
      ),
    ];

    // Return the form.
    return $form;
  }

  /**
   * Returns a string representing a frequency with its best fitting time unit.
   *
   * @param int $frequency
   *   The frequency in seconds.
   *
   * @return string
   *   The formated frequency or an empty string if no frequency was provided.
   */
  public function formatFrequency(?int $frequency) :string {
    if (!empty($frequency)) {
      // Reformat frequency according to the time unit that best fit.
      if (0 === ($frequency % 86400)) {
        $frequency = ($frequency/86400) . 'd';
      }
      elseif (0 === ($frequency % 3600)) {
        $frequency = ($frequency/3600) . 'h';
      }
      elseif (0 === ($frequency % 60)) {
        $frequency = ($frequency/60) . 'm';
      }
      else {
        $frequency .= 's';
      }
    }
    else {
      $frequency = '';
    }
    return $frequency;
  }

  /**
   * Checks if an external entity synchronization cron already exists.
   *
   * @param string $entity_id
   *   The external entity content type machine name.
   * @param array $element
   *   The form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return bool
   *   TRUE if a synchronization cron already exists for the given external
   *   entity type, FALSE otherwise.
   */
  public function exists(
    $entity_id,
    array $element,
    FormStateInterface $form_state
  ) {
    // Use the query factory to build a new sync entity query.
    $query = $this->entityStorage->getQuery();

    // Query the entity ID to see if its in use.
    $result = $query
      ->condition('id', $element['#field_prefix'] . $entity_id)
      ->execute()
    ;

    // We don't need to return the ID, only if it exists or not.
    return (bool) $result;
  }

  /**
   * {@inheritdoc}
   */
  protected function actions(array $form, FormStateInterface $form_state) {
    // Get the basic actins from the base class.
    $actions = parent::actions($form, $form_state);

    // Change the submit button text.
    $actions['submit']['#value'] = $this->t('Save');

    // Return the result.
    return $actions;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
    
    // Make sure a sync cron does not already exist for the given type if new.
    $sync = $this->getEntity();
    if ($sync->isNew()) {
      $xntt_type = $form_state->getValue('xnttType');
      $sync_cron = \Drupal::service('entity_type.manager')
        ->getStorage('xnttsync')
        ->load($xntt_type)
      ;
      if (!empty($sync_cron)) {
        $form_state->setErrorByName(
          'xnttType',
          $this->t(
            'A synchronization cron already exists for the external entity type %type. Edit the existing one.',
            ['%type' => $xntt_type,]
          )
        );
      }
    }

    // Generate a label.
    $xntt_type_list = get_external_entity_type_list();
    $form_state->setValue(
      'label',
      (empty($xntt_type_list[$form_state->getValue('xnttType')])
        ? ''
        : $xntt_type_list[$form_state->getValue('xnttType')] . ' '
      )
      . $this->t('Synchronization Cron')
    );
    
    // Compute frequency.
    $frequency = $form_state->getValue('frequency');
    if (!empty($frequency)) {
      $frequency = strtolower(trim($frequency));
      if (!preg_match('/^\d+[dhms]?$/', $frequency)) {
        $form_state->setErrorByName(
          'frequency',
          $this->t('Invalid frequency format. The frequency must be specified by an integer followed by one of the following time units without spaces: "s" for seconds, "m" for minutes, "h" for hours and "d" for days.')
        );
      }
      else {
        // Append 's' if no unit was specified.
        if (!preg_match('/[dhms]$/', $frequency)) {
          $frequency .= 's';
        }
        switch (substr($frequency, -1)) {
          case 'd':
              $frequency = substr($frequency, 0, -1) * 86400;
            break;

          case 'h':
              $frequency = substr($frequency, 0, -1) * 3600;
            break;

          case 'm':
              $frequency = substr($frequency, 0, -1) * 60;
            break;

          case 's':
              $frequency = substr($frequency, 0, -1);
            break;
          
          default:
            // We should never get here (unless preg_match above is changed).
            $frequency = 0;
        }
        if (0 === $frequency) {
          $form_state->setErrorByName(
            'frequency',
            $this->t('Invalid frequency: the frequency must be strictly positive.')
          );
        }
        else {
          // Update value.
          $form_state->setValue('frequency', $frequency);
        }
      }
    }

    // Clear in-use flag.
    $form_state->setValue('inUse', FALSE);
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $sync = $this->getEntity();
    $status = $sync->save();
    $url = $sync->toUrl();
    $edit_link = Link::fromTextAndUrl($this->t('Edit'), $url)->toString();

    if ($status == SAVED_UPDATED) {
      $this->messenger()->addMessage(
        $this->t(
          '%label has been updated.',
          ['%label' => $sync->label()]
        )
      );
      $this->logger('xnttmanager')->notice(
        '%label has been updated.',
        ['%label' => $sync->label(), 'link' => $edit_link]
      );
    }
    else {
      $this->messenger()->addMessage(
        $this->t('%label has been added.',
        ['%label' => $sync->label()])
      );
      $this->logger('xnttmanager')->notice(
        '%label has been added.',
        ['%label' => $sync->label(), 'link' => $edit_link]
      );
    }

    // Redirect the user back to the listing route after the save operation.
    $form_state->setRedirect('entity.xnttsync.list');
  }

}
