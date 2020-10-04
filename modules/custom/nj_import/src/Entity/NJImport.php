<?php

namespace Drupal\nj_import\Entity;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\nj_import\ImportTypeInterface;
use Drupal\nj_import\State;
use Drupal\nj_import\StateInterface;
use Drupal\user\UserInterface;

/**
 * Defines the import entity class.
 *
 */
class Import extends ContentEntityBase implements ImportInterface {

  use EntityChangedTrait;

  /**
   * An array of import stage states keyed by state.
   *
   * @var array
   */
  protected $states;

  /**
   * Gets the event dispatcher.
   *
   * @return \Symfony\Component\EventDispatcher\EventDispatcherInterface
   *   The event dispatcher service.
   */
  protected function eventDispatcher() {
    return \Drupal::service('event_dispatcher');
  }

  /**
   * {@inheritdoc}
   */
  public function id() {
    return $this->get('fid')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function label() {
    return $this->get('title')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getSource() {
    return $this->get('source')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setSource($source) {
    return $this->set('source', $source);
  }

  /**
   * {@inheritdoc}
   */
  public function getCreatedTime() {
    return (int) $this->get('created')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setCreatedTime($timestamp) {
    $this->set('created', (int) $timestamp);
  }

  /**
   * {@inheritdoc}
   */
  public function getChangedTime() {
    return (int) $this->get('changed')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getImportedTime() {
    return (int) $this->get('imported')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getNextImportTime() {
    return (int) $this->get('next')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getQueuedTime() {
    return (int) $this->get('queued')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setQueuedTime($queued) {
    $this->set('queued', (int) $queued);
  }

  /**
   * {@inheritdoc}
   */
  public function getType() {
    return $this->get('type')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getOwner() {
    return $this->get('uid')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getOwnerId() {
    return $this->get('uid')->target_id;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwnerId($uid) {
    $this->set('uid', $uid);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwner(UserInterface $account) {
    $this->set('uid', $account->id());
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isActive() {
    return (bool) $this->get('status')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setActive($active) {
    $this->set('status', $active ? static::ACTIVE : static::INACTIVE);
  }

  /**
   * {@inheritdoc}
   */
  public function import() {
    $this->entityTypeManager()
      ->getHandler('nj_import_import', 'import_import')
      ->import($this);
  }

  /**
   * {@inheritdoc}
   */
  public function startBatchImport() {
    $this->entityTypeManager()
      ->getHandler('nj_import_import', 'import_import')
      ->startBatchImport($this);
  }

  /**
   * {@inheritdoc}
   */
  public function startCronImport() {
    $this->entityTypeManager()
      ->getHandler('nj_import_import', 'import_import')
      ->startCronImport($this);
  }

  /**
   * {@inheritdoc}
   */
  public function pushImport($raw) {
    return $this->entityTypeManager()
      ->getHandler('nj_import_import', 'import_import')
      ->pushImport($this, $raw);
  }

  /**
   * {@inheritdoc}
   */
  public function startBatchClear() {
    $this->entityTypeManager()
      ->getHandler('nj_import_import', 'import_clear')
      ->startBatchClear($this);
  }

  /**
   * {@inheritdoc}
   */
  public function startBatchExpire() {
    return $this->entityTypeManager()
      ->getHandler('nj_import_import', 'import_expire')
      ->startBatchExpire($this);
  }

  /**
   * {@inheritdoc}
   */
  public function dispatchEntityEvent($event, EntityInterface $entity, ItemInterface $item) {
    return $this->eventDispatcher()->dispatch($event, new EntityEvent($this, $entity, $item));
  }

  /**
   * {@inheritdoc}
   */
  public function finishImport() {
    $time = time();

    $this->getType()
      ->getProcessor()
      ->postProcess($this, $this->getState(StateInterface::PROCESS));

    foreach ($this->states as $state) {
      if (is_object($state)) {
        $state->displayMessages();
        $state->logMessages($this);
      }
    }

    // Allow other modules to react upon finishing importing.
    $this->eventDispatcher()->dispatch(NJImportEvents::IMPORT_FINISHED, new ImportFinishedEvent($this));

    // Cleanup.
    $this->clearStates();
    $this->setQueuedTime(0);

    $this->set('imported', $time);

    $interval = $this->getType()->getImportPeriod();
    if ($interval !== ImportTypeInterface::SCHEDULE_NEVER) {
      $this->set('next', $interval + $time);
    }

    $this->save();
    $this->unlock();
  }

  /**
   * Cleans up after an import.
   */
  public function finishClear() {
    $this
      ->getType()
      ->getProcessor()
      ->postClear($this, $this->getState(StateInterface::CLEAR));

    foreach ($this->states as $state) {
      is_object($state) ? $state->displayMessages() : NULL;
    }

    $this->clearStates();
  }

  /**
   * {@inheritdoc}
   */
  public function getState($stage) {
    if (!isset($this->states[$stage])) {
      $state = \Drupal::keyValue('nj_import_import.' . $this->id())->get($stage);

      if (empty($state)) {
        // @todo move this logic to a factory or alike.
        switch ($stage) {
          case StateInterface::CLEAN:
            $state = new CleanState($this->id());
            break;

          default:
            $state = new State();
            break;
        }
      }

      $this->states[$stage] = $state;
    }
    return $this->states[$stage];
  }

  /**
   * {@inheritdoc}
   */
  public function setState($stage, StateInterface $state = NULL) {
    $this->states[$stage] = $state;
  }

  /**
   * {@inheritdoc}
   */
  public function clearStates() {
    $this->states = [];
    \Drupal::keyValue('nj_import_import.' . $this->id())->deleteAll();

    // Clean up references in nj_import_clean_list table for this import.
    \Drupal::database()->delete(CleanState::TABLE_NAME)
      ->condition('import_id', $this->id())
      ->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function saveStates() {
    \Drupal::keyValue('nj_import_import.' . $this->id())->setMultiple($this->states);
  }

  /**
   * {@inheritdoc}
   */
  public function progressFetching() {
    return $this->getState(StateInterface::FETCH)->progress;
  }

  /**
   * {@inheritdoc}
   */
  public function progressParsing() {
    return $this->getState(StateInterface::PARSE)->progress;
  }

  /**
   * {@inheritdoc}
   */
  public function progressImporting() {
    $fetcher = $this->getState(StateInterface::FETCH);
    $parser = $this->getState(StateInterface::PARSE);

    if ($fetcher->progress === StateInterface::BATCH_COMPLETE && $parser->progress === StateInterface::BATCH_COMPLETE) {
      return StateInterface::BATCH_COMPLETE;
    }
    // Fetching envelops parsing.
    // @todo: this assumes all fetchers neatly use total. May not be the case.
    $fetcher_fraction = $fetcher->total ? 1.0 / $fetcher->total : 1.0;
    $parser_progress = $parser->progress * $fetcher_fraction;
    $result = $fetcher->progress - $fetcher_fraction + $parser_progress;

    if ($result >= StateInterface::BATCH_COMPLETE) {
      return 0.99;
    }

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function progressCleaning() {
    return $this->getState(StateInterface::CLEAN)->progress;
  }

  /**
   * {@inheritdoc}
   */
  public function progressClearing() {
    return $this->getState(StateInterface::CLEAR)->progress;
  }

  /**
   * {@inheritdoc}
   */
  public function progressExpiring() {
    return $this->getState(StateInterface::EXPIRE)->progress;
  }

  /**
   * {@inheritdoc}
   */
  public function getItemCount() {
    return (int) $this->get('item_count')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function lock() {
    if (!\Drupal::service('lock.persistent')->acquire("nj_import_import_{$this->id()}", 3600 * 12)) {
      $args = ['@id' => $this->bundle(), '@fid' => $this->id()];
      throw new LockException(new FormattableMarkup('Cannot acquire lock for import @id / @fid.', $args));
    }
    Cache::invalidateTags(['nj_import_import_locked']);
  }

  /**
   * {@inheritdoc}
   */
  public function unlock() {
    \Drupal::service('lock.persistent')->release("nj_import_import_{$this->id()}");
    Cache::invalidateTags(['nj_import_import_locked']);
  }

  /**
   * {@inheritdoc}
   */
  public function isLocked() {
    return !\Drupal::service('lock.persistent')->lockMayBeAvailable("nj_import_import_{$this->id()}");
  }

  /**
   * {@inheritdoc}
   */
  public function getConfigurationFor(NJImportPluginInterface $client) {
    $type = $client->pluginType();
    // @todo Figure out why for the UploadFetcher there is no config available.
    $data = $this->get('config')->$type;
    $data = !empty($data) ? $data : [];

    return $data + $client->defaultImportConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function setConfigurationFor(NJImportPluginInterface $client, array $configuration) {
    $type = $client->pluginType();
    $this->get('config')->$type = array_intersect_key($configuration, $client->defaultImportConfiguration()) + $client->defaultImportConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage_controller, $update = TRUE) {
    $import_type = $this->getType();

    foreach ($import_type->getPlugins() as $plugin) {
      $plugin->onImportSave($this, $update);
    }

    // If this is a new node, 'next' and 'imported' will be zero which will
    // queue it for the next run.
    if ($import_type->getImportPeriod() === ImportTypeInterface::SCHEDULE_NEVER) {
      $this->set('next', ImportTypeInterface::SCHEDULE_NEVER);
    }

    // Update the item count.
    $this->set('item_count', $import_type->getProcessor()->getItemCount($this));
  }

  /**
   * {@inheritdoc}
   */
  public static function postDelete(EntityStorageInterface $storage_controller, array $nj_import) {
    // Delete values from other tables also referencing these nj_import.
    $ids = array_keys($nj_import);

    // Group nj_import by type.
    $grouped = [];
    foreach ($nj_import as $fid => $import) {
      $grouped[$import->bundle()][$fid] = $import;
    }

    // Alert plugins that we are deleting.
    foreach ($grouped as $group) {
      // Grab the first import to get its type.
      $import = reset($group);
      foreach ($import->getType()->getPlugins() as $plugin) {
        $plugin->onImportDeleteMultiple($group);
      }
    }

    // Clean up references in nj_import_clean_list table for each import.
    \Drupal::database()->delete(CleanState::TABLE_NAME)
      ->condition('import_id', $ids, 'IN')
      ->execute();

    \Drupal::service('event_dispatcher')->dispatch(NJImportEvents::FEEDS_DELETE, new DeleteNJImportEvent($nj_import));
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = [];

    $fields['fid'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Import ID'))
      ->setDescription(t('The import ID.'))
      ->setReadOnly(TRUE)
      ->setSetting('unsigned', TRUE);

    $fields['uuid'] = BaseFieldDefinition::create('uuid')
      ->setLabel(t('UUID'))
      ->setDescription(t('The import UUID.'))
      ->setReadOnly(TRUE);

    $fields['type'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Import type'))
      ->setDescription(t('The import type.'))
      ->setSetting('target_type', 'nj_import_import_type')
      ->setReadOnly(TRUE);

    $fields['title'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Title'))
      ->setDescription(t('The title of this import, always treated as non-markup plain text.'))
      ->setRequired(TRUE)
      ->setDefaultValue('')
      ->setSetting('max_length', 255)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'string',
        'weight' => -5,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -5,
      ])
      ->setDisplayConfigurable('form', TRUE);

    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Authored by'))
      ->setDescription(t('The user ID of the import author.'))
      ->setRevisionable(TRUE)
      ->setSetting('target_type', 'user')
      ->setSetting('handler', 'default')
      ->setDefaultValueCallback('Drupal\nj_import\Entity\Import::getCurrentUserId')
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'author',
        'weight' => 0,
      ])
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => 5,
        'settings' => [
          'match_operator' => 'CONTAINS',
          'size' => '60',
          'autocomplete_type' => 'tags',
          'placeholder' => '',
        ],
      ])
      ->setDisplayConfigurable('form', TRUE);

    $fields['status'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Importing status'))
      ->setDescription(t('A boolean indicating whether the import is active.'))
      ->setDefaultValue(TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Authored on'))
      ->setDescription(t('The time that the import was created.'))
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'timestamp',
        'weight' => 0,
      ])
      ->setDisplayOptions('form', [
        'type' => 'datetime_timestamp',
        'weight' => 10,
      ])
      ->setDisplayConfigurable('form', TRUE);

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the import was last edited.'));

    $fields['imported'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(t('Last import'))
      ->setDescription(t('The time that the import was imported.'))
      ->setDefaultValue(0)
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'timestamp_ago',
        'weight' => 1,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['next'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(t('Next import'))
      ->setDescription(t('The time that the import will import next.'))
      ->setDefaultValue(0)
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'timestamp',
        'weight' => 1,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['queued'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(t('Queued'))
      ->setDescription(t('Time when this import was queued for refresh, 0 if not queued.'))
      ->setDefaultValue(0);

    $fields['source'] = BaseFieldDefinition::create('uri')
      ->setLabel(t('Source'))
      ->setDescription(t('The source of the import.'))
      ->setRequired(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'nj_import_uri_link',
        'weight' => -3,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['config'] = BaseFieldDefinition::create('map')
      ->setLabel(t('Config'))
      ->setDescription(t('The config of the import.'));

    $fields['item_count'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Items imported'))
      ->setDescription(t('The number of items imported.'))
      ->setDefaultValue(0)
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'number_integer',
        'weight' => 0,
      ]);

    return $fields;
  }

  /**
   * Default value callback for 'uid' base field definition.
   *
   * @see ::baseFieldDefinitions()
   *
   * @return array
   *   An array of default values.
   */
  public static function getCurrentUserId() {
    return [\Drupal::currentUser()->id()];
  }

}
