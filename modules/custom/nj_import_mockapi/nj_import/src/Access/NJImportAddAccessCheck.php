<?php

namespace Drupal\nj_import\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Access check for nj_import link add list routes.
 */
class ImportAddAccessCheck implements AccessInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a ImportAddAccessCheck object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function access(AccountInterface $account) {
    // @todo Perhaps read config directly rather than load all import types.
    $access_control_handler = $this->entityTypeManager->getAccessControlHandler('nj_import_import');

    foreach ($this->entityTypeManager->getStorage('nj_import_import_type')->loadByProperties(['status' => TRUE]) as $import_type) {
      $access = $access_control_handler->createAccess($import_type->id(), $account, [], TRUE);
      if ($access->isAllowed()) {
        return $access;
      }
    }

    return AccessResult::neutral();
  }

}
