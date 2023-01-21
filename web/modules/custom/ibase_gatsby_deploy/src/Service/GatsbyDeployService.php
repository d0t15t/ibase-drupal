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
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Service description.
 */
class GatsbyDeployService {

  public LoggerInterface $logger;

  public MessengerInterface $messenger;

  protected State $state;

  protected EntityTypeManagerInterface $entityTypeManager;

  protected RouteMatchInterface $routeMatch;

  protected Connection $connection;

  protected AccountInterface $account;

  public function __construct(
    LoggerInterface $logger,
    MessengerInterface $messenger,
    State $state,
    EntityTypeManagerInterface $entity_type_manager,
    RouteMatchInterface $route_match,
    Connection $connection,
    AccountInterface $account
  ) {
    $this->logger = $logger;
    $this->messenger = $messenger;
    $this->state = $state;
    $this->entityTypeManager = $entity_type_manager;
    $this->routeMatch = $route_match;
    $this->connection = $connection;
    $this->account = $account;
  }

  public function getState(string $key) {
    return $this->state->get($key);
  }

  public function setState(string $key, $value) {
    $this->state->set($key, $value);
  }

  public function getStateMultiple(array $keys): ?array {
    return $this->state->getMultiple($keys);
  }

  public function getCurrentUser(): AccountInterface {
    return $this->account;
  }

  static function isBuildInProgress() {
    exec('pgrep -flx "node deploy.js"', $output, $ret);
    return $ret == 0;
  }

  static function readLogs() {
    $logs = file_get_contents(\Drupal::service('ibase_gatsby_deploy.service')->settings->getSettingsFormStateValue('frontend_log_file'));
    $logs = self::filterConsoleOutput($logs);
    return $logs;
  }

  static function filterConsoleOutput($text) {
    // remove console formatting
    return preg_replace('/\x1b\[[0-9]{0,2}[mAKG]/', '', $text);
  }

  static public function deploy() {
    /** @var GatsbyDeployService $utility */
    $utility = \Drupal::service('ibase_gatsby_deploy.service');
    /** @var GatsbyDeploySettings $settings */
    $settings = \Drupal::service('ibase_gatsby_deploy.settings');
//    if (!$utility->getCurrentUser()->hasPermission('ibase_gatsby_deploy build start')) {
//      throw new AccessDeniedHttpException('You have no permission for this action.');
//    }

    if (self::isBuildInProgress()) {
      return FALSE;
    }

    $path_to_frontend = $settings->getSettingsFormStateValue('frontend_path');
    $frontend_deploy_log_path = $settings->getSettingsFormStateValue('frontend_log_file');
    $node_path = $settings->getSettingsFormStateValue('node_path');
    $yarn_path = $settings->getSettingsFormStateValue('yarn_path');
    $user = $settings->getSettingsFormStateValue('user');
    $group = $settings->getSettingsFormStateValue('group');

    $env = "env NODE_PATH=$node_path YARN_PATH=$yarn_path FRONTEND_PATH=$path_to_frontend";
    if (!empty($user)) {
      $env .= " SET_USER=$user";
    }
    if (!empty($group)) {
      $env .= " SET_GROUP=$group";
    }

    $module_path = \Drupal::service('extension.list.module')->getPath('ibase_gatsby_deploy');
    $cmd = "$env $module_path/scripts/deploy.sh > " . $frontend_deploy_log_path . " 2>&1";
    exec($cmd, $output, $ret);

    if ($ret !== 0) {
      /** @var \Drupal\Core\Mail\MailManagerInterface $mail_manager */
      $t=1;
//      $mail_manager = Drupal::service('plugin.manager.mail');
//      $message = self::readLogs();
//      $to = Drupal::state()->get('usu_deploy.mail_to')
//        ?? Drupal::config('system.site')->get('mail');
//      $mail_manager->mail('usu_deploy', 'mail', $to, 'en', [
//        'subject' => Drupal::config('system.site')->get('name') .
//          ': Build failed',
//        'message' => $message,
//      ], NULL, TRUE);
    }
  }

}
