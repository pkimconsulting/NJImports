<?php

namespace Drupal\nj_import\NJImport\Target;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\nj_import\FieldTargetDefinition;
use Drupal\nj_import\Plugin\Type\Target\FieldTargetBase;

/**
 * Defines a string field mapper.
 *
 * @NJImportTarget(
 *   id = "string",
 *   field_types = {
 *     "string",
 *     "string_long",
 *     "list_string"
 *   }
 * )
 */
class StringTarget extends FieldTargetBase {

  /**
   * {@inheritdoc}
   */
  protected static function prepareTarget(FieldDefinitionInterface $field_definition) {
    $definition = FieldTargetDefinition::createFromFieldDefinition($field_definition)
      ->addProperty('value');

    if ($field_definition->getType() === 'string') {
      $definition->markPropertyUnique('value');
    }
    return $definition;
  }

}
