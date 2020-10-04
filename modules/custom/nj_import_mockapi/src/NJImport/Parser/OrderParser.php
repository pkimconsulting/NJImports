<?php

namespace Drupal\nj_import_mockapi\NJImport\Parser;

use Drupal\nj_import\Plugin\Type\Parser\ParserInterface;
use Drupal\nj_import\Plugin\Type\PluginBase;
use Drupal\nj_import\StateInterface;
use Drupal\nj_import\Result\ParserResult;

class OrderParser extends PluginBase implements ParserInterface {

  /**
   * {@inheritdoc}
   */
  public function parse($import, FetcherResultInterface $fetcher_result, StateInterface $state) {
    $result = new ParserResult();
    $raw = $fetcher_result->getRaw();
    $orders = json_decode($raw, TRUE);
    $failed = false;

    foreach ($orders as $order) {
      if ($order !== NULL) {
        $orderItems = $order->orderItems;

        foreach ($orderItems as $orderItem) {
          $item = $Drupal::entityTypeManager()->getStorage('node')->load($orderItem->id);
          if ($item->get('field_available_date')->getValue()  > strtotime($orderItem->shippingDate)) {
            $failed = true;
          }

          if ($item->get('field_quantity')->getValue() < $orderItem->quantity) {
            $failed =  true;
          }
        }

        if (!$failed) {
          foreach ($orderItems as $orderItem) {
            // Reduce quantity of items
            $item = $Drupal::entityTypeManager()->getStorage('node')->load($orderItem->id);
            $item->field_quantity->value =  $item->get('field_quantity')->getValue() - $orderItem->quantity;
          }
          $order->set('status', 'processed');
        }
        else {
          $order->get('status', 'failed');
        }
        $result->addItem($order);
      }
    }
    return $result;
  }
}
