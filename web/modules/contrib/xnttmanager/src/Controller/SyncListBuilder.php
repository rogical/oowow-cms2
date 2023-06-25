<?php

namespace Drupal\xnttmanager\Controller;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;

/**
 * Provides a listing of external entity synchronization crons.
 */
class SyncListBuilder extends ConfigEntityListBuilder {

  /**
   * {@inheritdoc}
   */
  protected function getModuleName() {
    return 'xnttmanager';
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['label'] = $this->t('Synchronization Operation');
    $header['xntt_type'] = $this->t('External Entity Type');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    $row['label'] = $entity->label();
    $row['xntt_type'] = $entity->id();

    return $row + parent::buildRow($entity);
  }

}
