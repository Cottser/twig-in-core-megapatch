<?php

/**
 * @file
 * Definition of \Drupal\ckeditor\Tests\CKEditorPluginManagerTest.
 */

namespace Drupal\ckeditor\Tests;

use Drupal\simpletest\DrupalUnitTestBase;
use Drupal\ckeditor\CKEditorPluginManager;

/**
 * Tests for the "CKEditor plugins" plugin manager.
 */
class CKEditorPluginManagerTest extends DrupalUnitTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('system', 'editor', 'ckeditor');

  /**
   * The manager for "CKEditor plugin" plugins.
   *
   * @var \Drupal\Component\Plugin\PluginManagerInterface
   */
  protected $manager;

  public static function getInfo() {
    return array(
      'name' => 'CKEditor plugin manager',
      'description' => 'Tests different ways of enabling CKEditor plugins.',
      'group' => 'CKEditor',
    );
  }

  function setUp() {
    parent::setUp();

    // Install the Filter module.
    $this->installSchema('system', 'url_alias');
    $this->enableModules(array('user', 'filter'));

    // Create text format, associate CKEditor.
    $filtered_html_format = entity_create('filter_format', array(
      'format' => 'filtered_html',
      'name' => 'Filtered HTML',
      'weight' => 0,
      'filters' => array(),
    ));
    $filtered_html_format->save();
    $editor = entity_create('editor', array(
      'format' => 'filtered_html',
      'editor' => 'ckeditor',
    ));
    $editor->save();
  }

  /**
   * Tests the enabling of plugins.
   */
  function testEnabledPlugins() {
    $this->manager = $this->container->get('plugin.manager.ckeditor.plugin');
    $editor = entity_load('editor', 'filtered_html');

    // Case 1: no CKEditor plugins.
    $definitions = array_keys($this->manager->getDefinitions());
    sort($definitions);
    $this->assertIdentical(array('internal', 'stylescombo'), $definitions, 'No CKEditor plugins found besides the built-in ones.');
    $this->assertIdentical(array(), $this->manager->getEnabledPlugins($editor), 'Only built-in plugins are enabled.');
    $this->assertIdentical(array('internal' => NULL), $this->manager->getEnabledPlugins($editor, TRUE), 'Only the "internal" plugin is enabled.');

    // Enable the CKEditor Test module, which has the Llama plugin (plus three
    // variations of it, to cover all possible ways a plugin can be enabled) and
    // clear the editor manager's cache so it is picked up.
    $this->enableModules(array('ckeditor_test'));
    $this->manager->clearCachedDefinitions();

    // Case 2: CKEditor plugins are available.
    $plugin_ids = array_keys($this->manager->getDefinitions());
    sort($plugin_ids);
    $this->assertIdentical(array('internal', 'llama', 'llama_button', 'llama_contextual', 'llama_contextual_and_button', 'stylescombo'), $plugin_ids, 'Additional CKEditor plugins found.');
    $this->assertIdentical(array(), $this->manager->getEnabledPlugins($editor), 'Only the internal plugins are enabled.');
    $this->assertIdentical(array('internal' => NULL), $this->manager->getEnabledPlugins($editor, TRUE), 'Only the "internal" plugin is enabled.');

    // Case 3: enable each of the newly available plugins, if possible:
    // a. Llama: cannot be enabled, since it does not implement
    //    CKEditorPluginContextualInterface nor CKEditorPluginButtonsInterface.
    // b. LlamaContextual: enabled by adding the 'Strike' button, which is
    //    part of another plugin!
    // c. LlamaButton: automatically enabled by adding its 'Llama' button.
    // d. LlamaContextualAndButton: enabled by either b or c.
    // Below, we will first enable the "Llama" button, which will cause the
    // LlamaButton and LlamaContextualAndButton plugins to be enabled. Then we
    // will remove the "Llama" button and add the "Strike" button, which will
    // cause the LlamaContextual and LlamaContextualAndButton plugins to be
    // enabled. Finally, we will add the "Strike" button back again, which would
    // cause all three plugins to be enabled.
    $original_toolbar = $editor->settings['toolbar']['buttons'][0];
    $editor->settings['toolbar']['buttons'][0][] = 'Llama';
    $editor->save();
    $file = array();
    $file['b'] = 'core/modules/ckeditor/tests/modules/js/llama_button.js';
    $file['c'] = 'core/modules/ckeditor/tests/modules/js/llama_contextual.js';
    $file['cb'] = 'core/modules/ckeditor/tests/modules/js/llama_contextual_and_button.js';
    $expected = array('llama_button' => $file['b'], 'llama_contextual_and_button' => $file['cb']);
    $this->assertIdentical($expected, $this->manager->getEnabledPlugins($editor), 'The LlamaButton and LlamaContextualAndButton plugins are enabled.');
    $this->assertIdentical(array('internal' => NULL) + $expected, $this->manager->getEnabledPlugins($editor, TRUE), 'The LlamaButton and LlamaContextualAndButton plugins are enabled.');
    $editor->settings['toolbar']['buttons'][0] = $original_toolbar;
    $editor->settings['toolbar']['buttons'][0][] = 'Strike';
    $editor->save();
    $expected = array('llama_contextual' => $file['c'], 'llama_contextual_and_button' => $file['cb']);
    $this->assertIdentical($expected, $this->manager->getEnabledPlugins($editor), 'The  LLamaContextual and LlamaContextualAndButton plugins are enabled.');
    $this->assertIdentical(array('internal' => NULL) + $expected, $this->manager->getEnabledPlugins($editor, TRUE), 'The LlamaContextual and LlamaContextualAndButton plugins are enabled.');
    $editor->settings['toolbar']['buttons'][0][] = 'Llama';
    $editor->save();
    $expected = array('llama_button' => $file['b'], 'llama_contextual' => $file['c'], 'llama_contextual_and_button' => $file['cb']);
    $this->assertIdentical($expected, $this->manager->getEnabledPlugins($editor), 'The LlamaButton, LlamaContextual and LlamaContextualAndButton plugins are enabled.');
    $this->assertIdentical(array('internal' => NULL) + $expected, $this->manager->getEnabledPlugins($editor, TRUE), 'The LLamaButton, LlamaContextual and LlamaContextualAndButton plugins are enabled.');
  }

}
