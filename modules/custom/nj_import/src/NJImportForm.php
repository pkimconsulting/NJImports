<?php

namespace Drupal\nj_import;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form controller for the import edit forms.
 */
class ImportForm extends ContentEntityForm {

  /**
   * Sets the form factory, used to generate forms for NJImport plugins.
   *
   * @param \Drupal\nj_import\Plugin\PluginFormFactory $factory
   *   The NJImport form factory.
   */
  protected function setPluginFormFactory(PluginFormFactory $factory) {
    $this->formFactory = $factory;
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $import = $this->entity;

    $import_type = $import->getType();

    $form['advanced'] = [
      '#type' => 'vertical_tabs',
      '#attributes' => ['class' => ['entity-meta']],
      '#weight' => 99,
    ];
    $form = parent::form($form, $form_state);

    $form['plugin']['#tree'] = TRUE;
    foreach ($import_type->getPlugins() as $type => $plugin) {
      if ($this->pluginHasForm($plugin, 'import')) {
        $import_form = $this->formFactory->createInstance($plugin, 'import');

        $plugin_state = (new FormState())->setValues($form_state->getValue(['plugin', $type], []));

        $form['plugin'][$type] = $import_form->buildConfigurationForm([], $plugin_state, $import);
        $form['plugin'][$type]['#tree'] = TRUE;

        $form_state->setValue(['plugin', $type], $plugin_state->getValues());
      }
    }

    $form['author'] = [
      '#type' => 'details',
      '#title' => $this->t('Authoring information'),
      '#group' => 'advanced',
      '#attributes' => ['class' => ['nj_import-import-form-author']],
      '#weight' => 90,
      '#optional' => TRUE,
    ];
    if (isset($form['uid'])) {
      $form['uid']['#group'] = 'author';
    }
    if (isset($form['created'])) {
      $form['created']['#group'] = 'author';
    }

    // Import options for administrators.
    $form['options'] = [
      '#type' => 'details',
      '#access' => $this->currentUser()->hasPermission('administer nj_import'),
      '#title' => $this->t('Import options'),
      '#collapsed' => TRUE,
      '#group' => 'advanced',
    ];

    $form['options']['status'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Active'),
      '#default_value' => $import->isActive(),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function actions(array $form, FormStateInterface $form_state) {
    $element = parent::actions($form, $form_state);

    // Add an "Import" button.
    if ($this->entity->access('import')) {
      $element['submit']['#dropbutton'] = 'save';
      $element['import'] = $element['submit'];
      $element['import']['#dropbutton'] = 'save';
      $element['import']['#value'] = $this->t('Save and import');
      $element['import']['#weight'] = 0;
      $element['import']['#submit'][] = '::import';
    }

    $element['delete']['#access'] = $this->entity->access('delete');

    return $element;
  }

  /**
   * {@inheritdoc}
   *
   * @todo Don't call buildEntity() here.
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    if ($form_state->getErrors()) {
      return;
    }
    $import = $this->buildEntity($form, $form_state);

    foreach ($import->getType()->getPlugins() as $type => $plugin) {
      if (!$this->pluginHasForm($plugin, 'import')) {
        continue;
      }

      $import_form = $this->formFactory->createInstance($plugin, 'import');

      $plugin_state = (new FormState())->setValues($form_state->getValue(['plugin', $type], []));
      $import_form->validateConfigurationForm($form['plugin'][$type], $plugin_state, $import);

      $form_state->setValue(['plugin', $type], $plugin_state->getValues());

      foreach ($plugin_state->getErrors() as $name => $error) {
        // Remove duplicate error messages.
        if (!empty($_SESSION['messages']['error'])) {
          foreach ($_SESSION['messages']['error'] as $delta => $message) {
            if ($message['message'] === $error) {
              unset($_SESSION['messages']['error'][$delta]);
              break;
            }
          }
        }
        $form_state->setErrorByName($name, $error);
      }
    }

    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Build the import object from the submitted values.
    parent::submitForm($form, $form_state);
    $import = $this->entity;

    foreach ($import->getType()->getPlugins() as $type => $plugin) {
      if ($this->pluginHasForm($plugin, 'import')) {
        $import_form = $this->formFactory->createInstance($plugin, 'import');

        $plugin_state = (new FormState())->setValues($form_state->getValue(['plugin', $type], []));

        $import_form->submitConfigurationForm($form['plugin'][$type], $plugin_state, $import);

        $form_state->setValue(['plugin', $type], $plugin_state->getValues());
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $import = $this->entity;
    $insert = $import->isNew();
    $import->save();

    $context = ['@type' => $import->bundle(), '%title' => $import->label()];
    $t_args = [
      '@type' => $import->getType()->label(),
      '%title' => $import->label(),
    ];

    if ($insert) {
      $this->logger('nj_import')->notice('@type: added %title.', $context);
      $this->messenger()->addMessage($this->t('%title has been created.', $t_args));
    }
    else {
      $this->logger('nj_import')->notice('@type: updated %title.', $context);
      $this->messenger()->addMessage($this->t('%title has been updated.', $t_args));
    }

    if (!$import->id()) {
      // In the unlikely case something went wrong on save, the import will be
      // rebuilt and import form redisplayed the same way as in preview.
      $this->messenger()->addError($this->t('The import could not be saved.'));
      $form_state->setRebuild();
      return;
    }

    if ($import->access('view')) {
      $form_state->setRedirect('entity.nj_import_import.canonical', ['nj_import_import' => $import->id()]);
    }
    else {
      $form_state->setRedirect('<front>');
    }
  }

  /**
   * Form submission handler for the 'import' action.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function import(array $form, FormStateInterface $form_state) {
    $import = $this->entity;
    $import->startBatchImport();
    return $import;
  }


}
