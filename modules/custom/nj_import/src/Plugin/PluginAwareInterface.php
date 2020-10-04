<?php

namespace Drupal\nj_imports\Plugin;

use Drupal\nj_imports\Plugin\Type\ImportsPluginInterface;

/**
 * Interface for objects that are aware of a plugin.
 */
interface PluginAwareInterface {

  /**
   * Sets the plugin for this object.
   *
   * @param \Drupal\Component\Plugin\ImportsPluginInterface $plugin
   *   The plugin.
   */
  public function setPlugin(ImportsPluginInterface $plugin);

}
