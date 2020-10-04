<?php

namespace Drupal\nj_import;

/**
 * Status of the import or clearing operation of a Import.
 */
class State implements StateInterface {

  /**
   * Denotes the progress made.
   *
   * 0.0 meaning no progress. 1.0 = StateInterface::BATCH_COMPLETE meaning
   * finished.
   *
   * @var float
   */
  public $progress = StateInterface::BATCH_COMPLETE;

  /**
   * Used as a pointer to store where left off. Must be serializable.
   *
   * @var scalar
   */
  public $pointer;

  /**
   * The total number of items being processed.
   *
   * @var int
   */
  public $total = 0;

  /**
   * The number of Import items created.
   *
   * @var int
   */
  public $created = 0;

  /**
   * The number of Import items updated.
   *
   * @var int
   */
  public $updated = 0;

  /**
   * The number of Import items deleted.
   *
   * @var int
   */
  public $deleted = 0;

  /**
   * The number of Import items skipped.
   *
   * @var int
   */
  public $skipped = 0;

  /**
   * The number of failed Import items.
   *
   * @var int
   */
  public $failed = 0;

  /**
   * The list of messages to display to the user.
   *
   * Each entry on the array is expected to have the following values:
   * - message (string|\Drupal\Component\Render\MarkupInterface): the translated
   *   message to be displayed to the user;
   * - type (string): the message's type. These values are supported:
   *   - 'status'
   *   - 'warning'
   *   - 'error'
   * - repeat (bool): whether or not showing the same message more than once.
   *
   * @var array
   */
  protected $messages = [];

  /**
   * {@inheritdoc}
   */
  public function progress($total, $progress) {
    if ($progress > $total || $total === $progress) {
      $this->setCompleted();
    }
    elseif ($total) {
      $this->progress = (float) ($progress / $total);
      if ($this->progress === StateInterface::BATCH_COMPLETE && $total !== $progress) {
        $this->progress = 0.99;
      }
    }
    else {
      $this->setCompleted();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function setCompleted() {
    $this->progress = StateInterface::BATCH_COMPLETE;
  }

  /**
   * {@inheritdoc}
   */
  public function setMessage($message = NULL, $type = 'status', $repeat = FALSE) {
    $this->messages[] = [
      'message' => $message,
      'type' => $type,
      'repeat' => $repeat,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function displayMessages() {
    foreach ($this->messages as $message) {
      \Drupal::messenger()->addMessage($message['message'], $message['type'], $message['repeat']);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function logMessages(ImportInterface $feed) {
    foreach ($this->messages as $message) {
      switch ($message['type']) {
        case 'status':
          $message['type'] = 'info';
          break;
      }
      \Drupal::logger('nj_import')->log($message['type'], $message['message'], [
        'feed' => $feed,
      ]);
    }
  }

}
