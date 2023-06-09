<?php

/**
 * @file
 * Fixture for \Drupal\Tests\lingotek\Functional\Update\LingotekUpgrade9402ClearDownloadInterimPreferenceTest.
 */

use Drupal\Core\Database\Database;

$connection = Database::getConnection();

$settings = $connection->select('config')
  ->fields('config', ['data'])
  ->condition('name', 'lingotek.settings')
  ->execute()
  ->fetchField();
$settings = unserialize($settings);
$connection->update('config')
  ->fields([
    'data' => serialize(array_merge_recursive($settings, ['preference' => ['enable_download_interim' => TRUE]])),
  ])
  ->condition('name', 'lingotek.settings')
  ->execute();
