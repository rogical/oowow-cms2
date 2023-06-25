<?php

namespace Drupal\external_entities;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityHandlerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a generic access control handler for external entities.
 */
class ExternalEntityAccessControlHandler extends EntityAccessControlHandler implements EntityHandlerInterface {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs an ExternalEntityAccessControlHandler object.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   */
  public function __construct(EntityTypeInterface $entity_type, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($entity_type);
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    $result = parent::checkAccess($entity, $operation, $account);

    if ($result->isNeutral()) {
      $external_entity_type = $this->getExternalEntityType();
      if (!in_array($operation, ['view label', 'view']) && $external_entity_type->isReadOnly() && !$external_entity_type->isAnnotatable()) {
        $result = AccessResult::forbidden()
          ->addCacheableDependency($entity)
          ->addCacheableDependency($external_entity_type);
      }
      else {
        $result = AccessResult::allowedIfHasPermission($account, "{$operation} {$entity->getEntityTypeId()} external entity");
      }
    }

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    $external_entity_type = $this->getExternalEntityType();
    if ($external_entity_type && $external_entity_type->isReadOnly()) {
      return AccessResult::forbidden()
        ->addCacheableDependency($this->entityType)
        ->addCacheableDependency($external_entity_type);
    }

    return AccessResult::allowedIf($account->hasPermission("create {$this->entityTypeId} external entity"))->cachePerPermissions();
  }

  /**
   * {@inheritdoc}
   */
  protected function checkFieldAccess($operation, FieldDefinitionInterface $field_definition, AccountInterface $account, FieldItemListInterface $items = NULL) {
    $result = parent::checkFieldAccess($operation, $field_definition, $account, $items);

    // Do not display form fields when the external entity type is read-only,
    // with the exception of the annotation field (this allows editing the
    // annotation directly from the external entity form by using, for example,
    // the Inline Entity Form module).
    if ($operation === 'edit') {
      $external_entity_type = $this->getExternalEntityType();
      if ($external_entity_type && $external_entity_type->isReadOnly() && $field_definition->getName() !== ExternalEntityInterface::ANNOTATION_FIELD) {
        $result = AccessResult::forbidden()
          ->addCacheableDependency($this->entityType)
          ->addCacheableDependency($external_entity_type);
      }
    }

    return $result;
  }

  /**
   * Get the external entity type this handler is running for.
   *
   * @return \Drupal\external_entities\ExternalEntityTypeInterface|bool
   *   The external entity type config entity object, or FALSE if not found.
   */
  protected function getExternalEntityType() {
    return $this
      ->entityTypeManager
      ->getStorage('external_entity_type')
      ->load($this->entityTypeId);
  }

}
