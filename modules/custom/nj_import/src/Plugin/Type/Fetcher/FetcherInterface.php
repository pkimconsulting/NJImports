<?php

namespace Drupal\nj_imports\Plugin\Type\Fetcher;

use Drupal\nj_imports\ImportInterface;
use Drupal\nj_imports\StateInterface;

/**
 * Interface for Imports fetchers.
 */
interface FetcherInterface {

  /**
   * Fetch content from a import and return it.
   *
   * @param \Drupal\nj_imports\ImportInterface $import
   *   The import to fetch results for.
   * @param \Drupal\nj_imports\StateInterface $state
   *   The state object.
   *
   * @return \Drupal\nj_imports\Result\FetcherResultInterface
   *   A fetcher result object.
   */
  public function fetch(ImportInterface $import, StateInterface $state);

}
