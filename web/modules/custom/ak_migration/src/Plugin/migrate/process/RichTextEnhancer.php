<?php

namespace Drupal\ak_migration\Plugin\migrate\process;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\migrate\MigrateException;
use Drupal\migrate\MigrateLookupInterface;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Perform custom value transformations.
 *
 * @MigrateProcessPlugin(
 *   id = "rich_text_enhancer"
 * )
 *
 * @code
 * field_rich_text:
 *   plugin: rich_text_enhancer
 *   source: text
 * @endcode
 */
class RichTextEnhancer extends ProcessPluginBase implements ContainerFactoryPluginInterface {

  protected MigrateLookupInterface $migrateLookup;

  protected EntityStorageInterface $blockContentStorage;

  public function __construct(
    array $configuration,
    string $plugin_id,
    array $plugin_definition,
    EntityStorageInterface $storage,
    MigrateLookupInterface $migrate_lookup
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->blockContentStorage = $storage;
    $this->migrateLookup = $migrate_lookup;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition,
    MigrationInterface $migration = NULL
  ) {
    $entity_type_manager = $container->get('entity_type.manager');
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $entity_type_manager->getDefinition('block_content') ? $entity_type_manager->getStorage('block_content') : NULL,
      $container->get('migrate.lookup')
    );
  }

  /**
   * {@inheritdoc}
   *
   * Set the block plugin id.
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $t=1;
    if ($value === strrev($value)) {
      throw new MigrateException('Reverse value is the same as value.');
//      throw new MigrateSkipRowException('Skip this record.');
//      throw new MigrateSkipProcessException($message);
    }
    return $value;
    if (is_array($value)) {
      [$module, $delta] = $value;
      switch ($module) {
        case 'aggregator':
          [$type] = explode('-', $delta);
          if ($type == 'feed') {
            return 'aggregator_feed_block';
          }
          break;

        case 'menu':
          return "system_menu_block:$delta";

        case 'block':
          if ($this->blockContentStorage) {
            $lookup_result = $this->migrateLookup->lookup(['d6_custom_block', 'd7_custom_block'], [$delta]);
            if ($lookup_result) {
              $block_id = $lookup_result[0]['id'];
              return 'block_content:' . $this->blockContentStorage->load($block_id)->uuid();
            }
          }
          break;

        default:
          break;
      }
    }
    else {
      return $value;
    }
  }

}
