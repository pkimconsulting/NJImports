<?php

namespace Drupal\nj_imports\Plugin\Type\Parser;

use Drupal\nj_imports\ImportInterface;
use Drupal\nj_imports\StateInterface;

/**
 * The interface Imports parser must implement.
 */
interface ParserInterface {

  /**
   * Parses content returned by fetcher.
   *
   * @param $import
   *   The import we are parsing for.
   * @param $fetcher_result
   *   The result returned by the fetcher.
   * @param \Drupal\nj_imports\StateInterface $state
   *   The state object.
   *
   * @return \Drupal\nj_imports\Result\ParserResultInterface
   *   The parser result object.
   *
   * @todo This needs more documentation.
   */
  public function parse($import, $fetcher_result, StateInterface $state);

  /**
   * Declare the possible mapping sources that this parser produces.
   *
   * @return array|false
   *   An array of mapping sources, or false if the sources can be defined by
   *   typing a value in a text field.
   *
   * @todo Get rid of the false return here and create a configurable source
   *   solution for parsers.
   * @todo Add type data here for automatic mappings.
   * @todo Provide code example.
   */
  public function getMappingSources();

}
