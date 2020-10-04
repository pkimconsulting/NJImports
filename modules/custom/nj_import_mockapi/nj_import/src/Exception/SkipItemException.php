<?php

namespace Drupal\nj_import\Exception;

/**
 * Thrown if the import of a single item should be skipped.
 *
 * This exception should only be thrown by event subscribers that extend
 * \Drupal\nj_import\EventSubscriber\AfterParseBase.
 */
class SkipItemException extends NJImportRuntimeException {}
