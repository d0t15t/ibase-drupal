<?php

namespace Drupal\ibase_gatsby_deploy\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\State\State;
use JetBrains\PhpStorm\Pure;
use Psr\Log\LoggerInterface;

/**
 * Settings service description.
 */
class GatsbyDeploySettings {

  protected GatsbyDeployService $utility;

  public function __construct(
    GatsbyDeployService $utility
  ) {
    $this->utility = $utility;
  }

  const MODULE_NAME = 'ibase_gatsby_deploy';

  public function getSettingsFormId(): string {
    return sprintf('%s_settings', $this::MODULE_NAME);
  }

  public function getSettingsStateKey(string $label): string {
    return sprintf('%s--form-values--%s', $this->getSettingsFormId(), $label);
  }

  public function getSettingsFormStateLabels(): array {
    return [
      'node_path', 'yarn_path', 'user', 'group', 'mail_to',
    ];
  }

  public function getSettingsFormStateValue(string $key): ?string {
    return $this->utility->getState($this->getSettingsStateKey($key));
  }

  public function getSettingsFormStateValues(array $keys): ?array {
    $state_keys = array_filter(array_map(fn ($key) => $this->getSettingsStateKey($keys), $keys), fn($e) => $e);
    return $this->utility->getStateMultiple($state_keys);
  }

  public function setSettingsFormStateValue(string $key, $value) {
    $this->utility->setState($this->getSettingsStateKey($key), $value);
  }

}
