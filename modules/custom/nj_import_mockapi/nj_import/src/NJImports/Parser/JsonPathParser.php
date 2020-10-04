<?php

namespace Drupal\nj_import\Imports\Parser;

use RuntimeException;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\nj_imports\ImportInterface;
use Drupal\nj_imports\StateInterface;
use Flow\JSONPath\JSONPath;

/**
 * Defines a JSON parser using JSONPath.
 *
 * @ImportsParser(
 *   id = "jsonpath",
 *   title = @Translation("JsonPath"),
 *   description = @Translation("Parse JSON with JSONPath.")
 * )
 */
class JsonPathParser extends JsonParserBase {

  /**
   * {@inheritdoc}
   */
  protected function executeContext(ImportInterface $import, FetcherResultInterface $fetcher_result, StateInterface $state) {
    $raw = $this->prepareRaw($fetcher_result);
    $parsed = $this->utility->decodeJsonArray($raw);
    $parsed = $this->search($parsed, $this->configuration['context']['value']);

    if (!$state->total) {
      $state->total = count($parsed);
    }

    $start = (int) $state->pointer;
    $state->pointer = $start + $this->configuration['line_limit'];
    return array_slice($parsed, $start, $this->configuration['line_limit']);
  }

  /**
   * {@inheritdoc}
   */
  protected function cleanUp(ImportInterface $import, ParserResultInterface $result, StateInterface $state) {
    // Calculate progress.
    $state->progress($state->total, $state->pointer);
  }

  /**
   * {@inheritdoc}
   */
  protected function executeSourceExpression($machine_name, $expression, $row) {
    $result = $this->search($row, $expression);

    if (is_scalar($result)) {
      return $result;
    }

    // Return a single value if there's only one value.
    return count($result) === 1 ? reset($result) : $result;
  }

  /**
   * {@inheritdoc}
   */
  protected function validateExpression(&$expression) {
    $expression = trim($expression);
  }

  /**
   * {@inheritdoc}
   */
  protected function getErrors() {
    if (!function_exists('json_last_error')) {
      return [];
    }

    if (!$error = json_last_error()) {
      return [];
    }

    $message = [
      'message' => $this->utility->translateError($error),
      'variables' => [],
      'severity' => RfcLogLevel::ERROR,
    ];
    return [$message];
  }

  /**
   * Searches an array via JSONPath.
   *
   * @param array $data
   *   The array to search.
   * @param string $expression
   *   The JSONPath expression.
   *
   * @return mixed
   *   The search results.
   */
  protected function search(array $data, $expression) {
    $json_path = new JSONPath($data);
    return $json_path->find($expression)->data();
  }

  /**
   * {@inheritdoc}
   */
  protected function loadLibrary() {
    if (!class_exists('Flow\JSONPath\JSONPath')) {
      throw new RuntimeException($this->t('The JSONPath library is not installed.'));
    }
  }

}
