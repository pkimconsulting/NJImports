<?php

namespace Drupal\nj_import;

use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\EntityHandlerInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Messenger\MessengerTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Provides a base class for entity handlers.
 */
abstract class ImportHandlerBase implements EntityHandlerInterface {

  use DependencySerializationTrait;
  use EventDispatcherTrait;
  use MessengerTrait;
  use StringTranslationTrait;

  /**
   * Constructs a new ImportHandlerBase object.
   *
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher.
   */
  public function __construct(EventDispatcherInterface $event_dispatcher) {
    $this->setEventDispatcher($event_dispatcher);
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $container->get('event_dispatcher')
    );
  }

}
