<?php

namespace Drupal\nj_import\NJImport\Processor;

/**
 * Defines a node processor.
 *
 * Creates nodes from import items.
 *
 * @NJImportProcessor(
 *   id = "entity:node",
 *   title = @Translation("Node"),
 *   description = @Translation("Creates nodes from import items."),
 *   entity_type = "node",
 *   form = {
 *     "configuration" = "Drupal\nj_import\NJImport\Processor\Form\DefaultEntityProcessorForm",
 *     "option" = "Drupal\nj_import\NJImport\Processor\Form\EntityProcessorOptionForm",
 *   },
 * )
 */
class NodeProcessor extends EntityProcessorBase {

  /**
   * {@inheritdoc}
   */
  public function entityLabel() {
    return $this->t('Node');
  }

}
