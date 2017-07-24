<?php

/**
 * @file
 * Fixture for \Drupal\lingotek\Tests\Update\ConfigEntityMetadataUpdate8001Test.
 */

use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Database\Database;


$connection = Database::getConnection();

$connection->insert('config')
  ->fields([
    'collection' => '',
    'name' => 'node.type.basic_page',
    'data' => serialize(Yaml::decode(file_get_contents(__DIR__ . '/node.type.basic_page.yml'))),
  ])
  ->execute();

// Enable lingotek_test theme.
$extensions = $connection->select('config')
  ->fields('config', ['data'])
  ->condition('name', 'core.extension')
  ->execute()
  ->fetchField();
$extensions = unserialize($extensions);
$connection->update('config')
  ->fields([
    'data' => serialize(array_merge_recursive($extensions, ['module' =>
      [
        'lingotek_test' => 0,
      ]]))
  ])
  ->condition('name', 'core.extension')
  ->execute();
