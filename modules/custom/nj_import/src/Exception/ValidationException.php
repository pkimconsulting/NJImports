<?php

namespace Drupal\nj_import\Exception;

use Drupal\Component\Render\FormattableMarkup;

/**
 * Thrown if validation of a import item fails.
 */
class ValidationException extends NJImportRuntimeException {

  /**
   * Returns the formatted message.
   *
   * @return \Drupal\Component\Render\FormattableMarkup
   *   A formatted message.
   */
  public function getFormattedMessage() {
    return new FormattableMarkup($this->getMessage(), []);
  }

}
