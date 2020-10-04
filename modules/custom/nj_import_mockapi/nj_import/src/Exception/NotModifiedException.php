<?php

namespace Drupal\nj_import\Exception;

/**
 * Exception thrown if the import has not been updated since the last run.
 */
class NotModifiedException extends NJImportRuntimeException {}
