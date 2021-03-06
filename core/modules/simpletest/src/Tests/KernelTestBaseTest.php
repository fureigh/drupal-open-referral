<?php

/**
 * @file
 * Contains \Drupal\simpletest\Tests\KernelTestBaseTest.
 */

namespace Drupal\simpletest\Tests;

use Drupal\simpletest\KernelTestBase;

/**
 * Tests KernelTestBase functionality.
 *
 * @group simpletest
 */
class KernelTestBaseTest extends KernelTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('entity_test');

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    $original_container = $this->originalContainer;
    parent::setUp();
    $this->assertNotIdentical(\Drupal::getContainer(), $original_container, 'KernelTestBase test creates a new container.');
  }

  /**
   * Tests expected behavior of setUp().
   */
  function testSetUp() {
    $modules = array('entity_test');
    $table = 'entity_test';

    // Verify that specified $modules have been loaded.
    $this->assertTrue(function_exists('entity_test_entity_bundle_info'), 'entity_test.module was loaded.');
    // Verify that there is a fixed module list.
    $this->assertIdentical(array_keys(\Drupal::moduleHandler()->getModuleList()), $modules);
    $this->assertIdentical(\Drupal::moduleHandler()->getImplementations('entity_bundle_info'), ['entity_test']);
    $this->assertIdentical(\Drupal::moduleHandler()->getImplementations('entity_type_alter'), ['entity_test']);

    // Verify that no modules have been installed.
    $this->assertFalse(db_table_exists($table), "'$table' database table not found.");
  }

  /**
   * Tests expected load behavior of enableModules().
   */
  function testEnableModulesLoad() {
    $module = 'field_test';

    // Verify that the module does not exist yet.
    $this->assertFalse(\Drupal::moduleHandler()->moduleExists($module), "$module module not found.");
    $list = array_keys(\Drupal::moduleHandler()->getModuleList());
    $this->assertFalse(in_array($module, $list), "$module module not found in the extension handler's module list.");
    $list = \Drupal::moduleHandler()->getImplementations('entity_display_build_alter');
    $this->assertFalse(in_array($module, $list), "{$module}_entity_display_build_alter() in \Drupal::moduleHandler()->getImplementations() not found.");

    // Enable the module.
    $this->enableModules(array($module));

    // Verify that the module exists.
    $this->assertTrue(\Drupal::moduleHandler()->moduleExists($module), "$module module found.");
    $list = array_keys(\Drupal::moduleHandler()->getModuleList());
    $this->assertTrue(in_array($module, $list), "$module module found in the extension handler's module list.");
    $list = \Drupal::moduleHandler()->getImplementations('query_efq_table_prefixing_test_alter');
    $this->assertTrue(in_array($module, $list), "{$module}_query_efq_table_prefixing_test_alter() in \Drupal::moduleHandler()->getImplementations() found.");
  }

  /**
   * Tests expected installation behavior of enableModules().
   */
  function testEnableModulesInstall() {
    $module = 'module_test';
    $table = 'module_test';

    // Verify that the module does not exist yet.
    $this->assertFalse(\Drupal::moduleHandler()->moduleExists($module), "$module module not found.");
    $list = array_keys(\Drupal::moduleHandler()->getModuleList());
    $this->assertFalse(in_array($module, $list), "$module module not found in the extension handler's module list.");
    $list = \Drupal::moduleHandler()->getImplementations('hook_info');
    $this->assertFalse(in_array($module, $list), "{$module}_hook_info() in \Drupal::moduleHandler()->getImplementations() not found.");

    $this->assertFalse(db_table_exists($table), "'$table' database table not found.");
    $schema = drupal_get_schema($table, TRUE);
    $this->assertFalse($schema, "'$table' table schema not found.");

    // Install the module.
    \Drupal::service('module_installer')->install(array($module));

    // Verify that the enabled module exists.
    $this->assertTrue(\Drupal::moduleHandler()->moduleExists($module), "$module module found.");
    $list = array_keys(\Drupal::moduleHandler()->getModuleList());
    $this->assertTrue(in_array($module, $list), "$module module found in the extension handler's module list.");
    $list = \Drupal::moduleHandler()->getImplementations('hook_info');
    $this->assertTrue(in_array($module, $list), "{$module}_hook_info() in \Drupal::moduleHandler()->getImplementations() found.");

    $this->assertTrue(db_table_exists($table), "'$table' database table found.");
    $schema = drupal_get_schema($table);
    $this->assertTrue($schema, "'$table' table schema found.");
  }

  /**
   * Tests installing modules with DependencyInjection services.
   */
  function testEnableModulesInstallContainer() {
    // Install Node module.
    $this->enableModules(array('user', 'field', 'node'));

    $this->installEntitySchema('node', array('node', 'node_field_data'));
    // Perform an entity query against node.
    $query = \Drupal::entityQuery('node');
    // Disable node access checks, since User module is not enabled.
    $query->accessCheck(FALSE);
    $query->condition('nid', 1);
    $query->execute();
    $this->pass('Entity field query was executed.');
  }

  /**
   * Tests expected behavior of installSchema().
   */
  function testInstallSchema() {
    $module = 'entity_test';
    $table = 'entity_test_example';
    // Verify that we can install a table from the module schema.
    $this->installSchema($module, $table);
    $this->assertTrue(db_table_exists($table), "'$table' database table found.");

    // Verify that the schema is known to Schema API.
    $schema = drupal_get_schema();
    $this->assertTrue($schema[$table], "'$table' table found in schema.");
    $schema = drupal_get_schema($table);
    $this->assertTrue($schema, "'$table' table schema found.");

    // Verify that a unknown table from an enabled module throws an error.
    $table = 'unknown_entity_test_table';
    try {
      $this->installSchema($module, $table);
      $this->fail('Exception for non-retrievable schema found.');
    }
    catch (\Exception $e) {
      $this->pass('Exception for non-retrievable schema found.');
    }
    $this->assertFalse(db_table_exists($table), "'$table' database table not found.");
    $schema = drupal_get_schema($table);
    $this->assertFalse($schema, "'$table' table schema not found.");

    // Verify that a table from a unknown module cannot be installed.
    $module = 'database_test';
    $table = 'test';
    try {
      $this->installSchema($module, $table);
      $this->fail('Exception for non-retrievable schema found.');
    }
    catch (\Exception $e) {
      $this->pass('Exception for non-retrievable schema found.');
    }
    $this->assertFalse(db_table_exists($table), "'$table' database table not found.");
    $schema = drupal_get_schema($table);
    $this->assertFalse($schema, "'$table' table schema not found.");

    // Verify that the same table can be installed after enabling the module.
    $this->enableModules(array($module));
    $this->installSchema($module, $table);
    $this->assertTrue(db_table_exists($table), "'$table' database table found.");
    $schema = drupal_get_schema($table);
    $this->assertTrue($schema, "'$table' table schema found.");
  }

  /**
   * Tests expected behavior of installEntitySchema().
   */
  function testInstallEntitySchema() {
    $entity = 'entity_test';
    // The entity_test Entity has a field that depends on the User module.
    $this->enableModules(array('user'));
    // Verity that the entity schema is created properly.
    $this->installEntitySchema($entity);
    $this->assertTrue(db_table_exists($entity), "'$entity' database table found.");
  }

  /**
   * Tests expected behavior of installConfig().
   */
  function testInstallConfig() {
    $module = 'user';

    // Verify that default config can only be installed for enabled modules.
    try {
      $this->installConfig(array($module));
      $this->fail('Exception for non-enabled module found.');
    }
    catch (\Exception $e) {
      $this->pass('Exception for non-enabled module found.');
    }
    $this->assertFalse($this->container->get('config.storage')->exists('user.settings'));

    // Verify that default config can be installed.
    $this->enableModules(array('user'));
    $this->installConfig(array('user'));
    $this->assertTrue($this->container->get('config.storage')->exists('user.settings'));
    $this->assertTrue($this->config('user.settings')->get('register'));
  }

  /**
   * Tests that the module list is retained after enabling/installing/disabling.
   */
  function testEnableModulesFixedList() {
    // Install system module.
    $this->container->get('module_installer')->install(array('system', 'menu_link_content'));
    $entity_manager = \Drupal::entityManager();

    // entity_test is loaded via $modules; its entity type should exist.
    $this->assertEqual($this->container->get('module_handler')->moduleExists('entity_test'), TRUE);
    $this->assertTrue(TRUE == $entity_manager->getDefinition('entity_test'));

    // Load some additional modules; entity_test should still exist.
    $this->enableModules(array('field', 'text', 'entity_test'));
    $this->assertEqual($this->container->get('module_handler')->moduleExists('entity_test'), TRUE);
    $this->assertTrue(TRUE == $entity_manager->getDefinition('entity_test'));

    // Install some other modules; entity_test should still exist.
    $this->container->get('module_installer')->install(array('user', 'field', 'field_test'), FALSE);
    $this->assertEqual($this->container->get('module_handler')->moduleExists('entity_test'), TRUE);
    $this->assertTrue(TRUE == $entity_manager->getDefinition('entity_test'));

    // Uninstall one of those modules; entity_test should still exist.
    $this->container->get('module_installer')->uninstall(array('field_test'));
    $this->assertEqual($this->container->get('module_handler')->moduleExists('entity_test'), TRUE);
    $this->assertTrue(TRUE == $entity_manager->getDefinition('entity_test'));

    // Set the weight of a module; entity_test should still exist.
    module_set_weight('field', -1);
    $this->assertEqual($this->container->get('module_handler')->moduleExists('entity_test'), TRUE);
    $this->assertTrue(TRUE == $entity_manager->getDefinition('entity_test'));

    // Reactivate the previously uninstalled module.
    $this->enableModules(array('field_test'));

    // Create a field.
    entity_create('entity_view_display', array(
      'targetEntityType' => 'entity_test',
      'bundle' => 'entity_test',
      'mode' => 'default',
    ));
    $field_storage = entity_create('field_storage_config', array(
      'field_name' => 'test_field',
      'entity_type' => 'entity_test',
      'type' => 'test_field'
    ));
    $field_storage->save();
    entity_create('field_config', array(
      'field_storage' => $field_storage,
      'bundle' => 'entity_test',
    ))->save();
  }

  /**
   * Tests that _theme() works right after loading a module.
   */
  function testEnableModulesTheme() {
    $original_element = $element = array(
      '#type' => 'container',
      '#markup' => 'Foo',
      '#attributes' => array(),
    );
    $this->enableModules(array('system'));
    // _theme() throws an exception if modules are not loaded yet.
    $this->assertTrue(drupal_render($element));

    $element = $original_element;
    $this->disableModules(array('entity_test'));
    $this->assertTrue(drupal_render($element));
  }

  /**
   * Tests that there is no theme by default.
   */
  function testNoThemeByDefault() {
    $themes = $this->config('core.extension')->get('theme');
    $this->assertEqual($themes, array());

    $extensions = $this->container->get('config.storage')->read('core.extension');
    $this->assertEqual($extensions['theme'], array());

    $active_theme = $this->container->get('theme.manager')->getActiveTheme();
    $this->assertEqual($active_theme->getName(), 'core');
  }

  /**
   * Tests that drupal_get_profile() returns NULL.
   *
   * As the currently active installation profile is used when installing
   * configuration, for example, this is essential to ensure test isolation.
   */
  public function testDrupalGetProfile() {
    $this->assertNull(drupal_get_profile());
  }

}
