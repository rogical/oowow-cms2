<?php

namespace Drupal\external_entities\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\external_entities\ExternalEntityInterface;

/**
 * Form handler for the external entity create/edit forms.
 *
 * @internal
 */
class ExternalEntityForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    /** @var \Drupal\external_entities\ExternalEntityInterface $external_entity */
    $external_entity = $this->entity;

    if ($this->operation == 'edit') {
      $form['#title'] = $this->t('<em>Edit @type</em> @title', [
        '@type' => $external_entity->getExternalEntityType()->label(),
        '@title' => $external_entity->label(),
      ]);
    }

    if (!empty($form[ExternalEntityInterface::ANNOTATION_FIELD]['widget'][0]['inline_entity_form'])) {
      $form['#ief_element_submit'][] = [
        $this,
        'markBypassAnnotatedExternalEntitySave',
      ];
      $original_annotation = $form_state->get('original_annotation');
      if (!$original_annotation && $external_entity->getAnnotation()) {
        $form_state->set('original_annotation', clone $external_entity->getAnnotation());
      }
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\external_entities\ExternalEntityInterface $external_entity */
    $external_entity = $this->entity;

    // When saving an external entity with annotation through an inline entity
    // form, and because of how annotation fields are inherited, the original
    // external entity object already contains the new annotation values, while
    // we expect it to be the previous ones. We manually set the correct
    // original values back again.
    // @see external_entities_entity_insert()
    // @see external_entities_entity_update()
    // @see _external_entities_save_annotated_external_entity()
    if (!empty($form[ExternalEntityInterface::ANNOTATION_FIELD]['widget'][0]['inline_entity_form'])) {
      // The annotation has already been saved through the inline entity form.
      // Let's remap the annotation fields to make sure the most recent values
      // are mapped.
      $external_entity->mapAnnotationFields();

      // If an original external entity exists, we remap the annotation fields
      // with the values of the original annotation.
      if (!empty($external_entity->original)) {
        $external_entity->original->mapAnnotationFields($form_state->get('original_annotation'));
      }
    }

    $insert = $external_entity->isNew();
    $external_entity->save();
    $external_entity_link = $external_entity->toLink($this->t('View'))->toString();
    $context = [
      '@type' => $external_entity->getEntityType()->getLabel(),
      '%title' => $external_entity->label(),
      'link' => $external_entity_link,
    ];
    $t_args = [
      '@type' => $external_entity->getEntityType()->getLabel(),
      '%title' => $external_entity->toLink($external_entity->label())->toString(),
    ];

    if ($insert) {
      $this->logger('content')->notice('@type: added %title.', $context);
      $this->messenger()->addStatus($this->t('@type %title has been created.', $t_args));
    }
    else {
      $this->logger('content')->notice('@type: updated %title.', $context);
      $this->messenger()->addStatus($this->t('@type %title has been updated.', $t_args));
    }

    if ($external_entity->id()) {
      if ($external_entity->access('view')) {
        $form_state->setRedirect(
          'entity.' . $external_entity->getEntityTypeId() . '.canonical',
          [$external_entity->getEntityTypeId() => $external_entity->id()]
        );
      }
      else {
        $form_state->setRedirect('<front>');
      }
    }
    else {
      // In the unlikely case something went wrong on save, the external entity
      // will be rebuilt and external entity form redisplayed.
      $this->messenger()->addError($this->t('The @type could not be saved.'), [
        '@type' => $external_entity->getEntityType()->getSingularLabel(),
      ]);
      $form_state->setRebuild();
    }
  }

  /**
   * Mark the annotations so that saving them won't save its external entity.
   *
   * If the annotation entity form is embedded as an inline entity form, the
   * annotation will be saved before the external entity is saved.
   * In _external_entities_save_annotated_external_entity() the annotated
   * external entity is automatically saved when its annotation is saved.
   * This means that that the external entity will be saved twice:
   * - first it will be saved when the annotation is saved
   * - secondly it will be saved in the submit handler of the external entity
   *   form
   * To prevent this from happening we mark the annotation so that the annotated
   * external entity save doesn't happen on annotation save.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @see external_entities_entity_insert()
   * @see external_entities_entity_update()
   * @see _external_entities_save_annotated_external_entity()
   */
  public static function markBypassAnnotatedExternalEntitySave(array $form, FormStateInterface $form_state) {
    foreach ($form_state->get('inline_entity_form') as &$widget_state) {
      if (empty($widget_state['instance'])) {
        continue;
      }

      /** @var \Drupal\Core\Field\BaseFieldDefinition $field */
      $field = $widget_state['instance'];
      if ($field->getName() !== 'annotation') {
        continue;
      }

      $widget_state += ['entities' => [], 'delete' => []];
      foreach ($widget_state['entities'] as $entity_item) {
        if (!empty($entity_item['entity'])) {
          $entity_item['entity']->{EXTERNAL_ENTITIES_BYPASS_ANNOTATED_EXTERNAL_ENTITY_SAVE_PROPERTY} = TRUE;
        }
      }

      foreach ($widget_state['delete'] as $entity) {
        $entity->{EXTERNAL_ENTITIES_BYPASS_ANNOTATED_EXTERNAL_ENTITY_SAVE_PROPERTY} = TRUE;
      };
    }
  }

}
