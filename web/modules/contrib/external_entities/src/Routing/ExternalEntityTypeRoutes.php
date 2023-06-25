<?php

namespace Drupal\external_entities\Routing;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Provides dynamic routes for external entity types.
 */
class ExternalEntityTypeRoutes implements ContainerInjectionInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Constructs a new EntityTypeRepository.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, ModuleHandlerInterface $module_handler) {
    $this->entityTypeManager = $entity_type_manager;
    $this->moduleHandler = $module_handler;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('module_handler')
    );
  }

  /**
   * Returns a collection of routes.
   *
   * @return \Symfony\Component\Routing\RouteCollection
   *   A collection of routes.
   */
  public function routes() {
    $collection = new RouteCollection();

    foreach ($this->entityTypeManager->getDefinitions() as $entity_type_id => $entity_type) {
      if ($entity_type->getProvider() === 'external_entities') {
        // Edit page.
        $route = new Route('/admin/structure/external-entity-types/' . $entity_type_id);
        $route->setDefault('_entity_form', 'external_entity_type.edit');
        $route->setDefault('_title_callback', '\Drupal\Core\Entity\Controller\EntityController::title');
        $route->setDefault('external_entity_type', $entity_type_id);
        $route->setRequirement('_permission', 'administer external entity types');
        $collection->add('entity.external_entity_type.' . $entity_type_id . '.edit_form', $route);

        // Delete page.
        $route = new Route('/admin/structure/external-entity-types/' . $entity_type_id . '/delete');
        $route->setDefault('_entity_form', 'external_entity_type.delete');
        $route->setDefault('_title', 'Delete');
        $route->setDefault('external_entity_type', $entity_type_id);
        $route->setRequirement('_permission', 'administer external entity types');
        $collection->add('entity.external_entity_type.' . $entity_type_id . '.delete_form', $route);

        // Translate page.
        if ($this->moduleHandler->moduleExists('config_translation')) {
          $route = new Route('/admin/structure/external-entity-types/' . $entity_type_id . '/translate');
          $route->setDefault('_controller', '\Drupal\config_translation\Controller\ConfigTranslationController::itemPage');
          $route->setDefault('plugin_id', 'external_entity_type');
          $route->setDefault('external_entity_type', $this->entityTypeManager
            ->getStorage('external_entity_type')
            ->load($entity_type_id));
          $route->setRequirement('_config_translation_overview_access', 'TRUE');
          $collection->add('entity.external_entity_type.' . $entity_type_id . '.translate_form', $route);
        }
      }
    }

    return $collection;
  }

}
