<?php

namespace Drupal\nj_import\NJImport\Target;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\nj_import\FieldTargetDefinition;

/**
 * Defines an integer field mapper.
 *
 * @NJImportTarget(
 *   id = "integer",
 *   field_types = {
 *     "integer",
 *     "list_integer"
 *   }
 * )
 */
class Integer extends Number {

  /**
   * {@inheritdoc}
   */
  protected static function prepareTarget(FieldDefinitionInterface $field_definition) {
    $definition = FieldTargetDefinition::createFromFieldDefinition($field_definition)
      ->addProperty('value')
      ->markPropertyUnique('value');

    return $definition;
  }

  /**
   * {@inheritdoc}
   */
  protected function prepareValue($delta, array &$values) {
    $value = trim($values['value']);
    $values['value'] = is_numeric($value) ? (int) $value : '';
  }

}
