<?php

namespace Drupal\lingotek_test;

use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;

class LingotekFakeConfigFactory extends ConfigFactory implements ConfigFactoryInterface {

  public function get($name) {
    $config = parent::get($name);
    if ($name === 'lingotek.account') {
      if ($config instanceof LingotekFakeAccountConfigWrapper && !$config->config instanceof ImmutableConfig) {
        unset($this->cache[$name]);
        $config = parent::get($name);
        $config = new LingotekFakeAccountConfigWrapper($name, $this->storage, $this->eventDispatcher, $this->typedConfigManager, $config);
        $this->cache[$name] = $config;
      }
      elseif (!$config instanceof LingotekFakeAccountConfigWrapper) {
        $config = new LingotekFakeAccountConfigWrapper($name, $this->storage, $this->eventDispatcher, $this->typedConfigManager, $config);
        $this->cache[$name] = $config;
      }
    }
    return $config;
  }

  public function getEditable($name) {
    $config = parent::getEditable($name);
    if ($name === 'lingotek.account') {
      if ($config instanceof LingotekFakeAccountConfigWrapper && $config->config instanceof ImmutableConfig) {
        unset($this->cache[$name]);
        $config = parent::getEditable($name);
        $config = new LingotekFakeAccountConfigWrapper($name, $this->storage, $this->eventDispatcher, $this->typedConfigManager, $config);
        $this->cache[$name] = $config;
      }
      elseif (!$config instanceof LingotekFakeAccountConfigWrapper) {
        $config = new LingotekFakeAccountConfigWrapper($name, $this->storage, $this->eventDispatcher, $this->typedConfigManager, $config);
        $this->cache[$name] = $config;
      }
    }
    return $config;
  }

}
