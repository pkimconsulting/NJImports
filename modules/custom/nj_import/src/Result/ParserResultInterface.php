<?php

namespace Drupal\nj_import\Result;

use Drupal\nj_import\NJimport\Item\ItemInterface;

/**
 * The result of a parsing stage.
 */
interface ParserResultInterface extends \Iterator, \ArrayAccess, \Countable {

  /**
   * Adds an item to the result.
   *
   * @param \Drupal\nj_import\NJimport\Item\ItemInterface $item
   *   A parsed feed item.
   *
   * @return $this
   */
  public function addItem(ItemInterface $item);

  /**
   * Adds a list of items to the result.
   *
   * @param \Drupal\nj_import\NJimport\Item\ItemInterface[] $items
   *   A list of feed items.
   *
   * @return $this
   */
  public function addItems(array $items);

}
