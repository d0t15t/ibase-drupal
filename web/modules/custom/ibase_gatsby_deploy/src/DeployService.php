<?php

namespace Drupal\usu_deploy\Service;

use Drupal;
use Drupal\Core\Render\Markup;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Class DeployService.
 */
class DeployService {

  const LOG_FILE = DRUPAL_ROOT . '/../deploy_log.txt';

  static public function deploy() {
    if (!Drupal::currentUser()->hasPermission('build start')) {
      throw new AccessDeniedHttpException('You have no permission for this action.');
    }

    if (self::isBuildInProgress()) {
      return FALSE;
    }

    $node_path = Drupal::state()->get('usu_deploy.node_path');
    $yarn_path = Drupal::state()->get('usu_deploy.yarn_path');
    $user = Drupal::state()->get('usu_deploy.user');
    $group = Drupal::state()->get('usu_deploy.group');

    $env = "env NODE_PATH=$node_path YARN_PATH=$yarn_path";
    if (!empty($user)) {
      $env .= " SET_USER=$user";
    }
    if (!empty($group)) {
      $env .= " SET_GROUP=$group";
    }

    $cmd = "$env ../scripts/deploy.sh > " . self::LOG_FILE . " 2>&1";
    exec($cmd, $output, $ret);

    if ($ret !== 0) {
      /** @var \Drupal\Core\Mail\MailManagerInterface $mail_manager */
      $mail_manager = Drupal::service('plugin.manager.mail');
      $message = self::readLogs();
      $to = Drupal::state()->get('usu_deploy.mail_to')
        ?? Drupal::config('system.site')->get('mail');
      $mail_manager->mail('usu_deploy', 'mail', $to, 'en', [
        'subject' => Drupal::config('system.site')->get('name') .
          ': Build failed',
        'message' => $message,
      ], NULL, TRUE);
    }
  }

  static public function yarnClean() {
    if (!Drupal::currentUser()->hasPermission('yarn clean')) {
      throw new AccessDeniedHttpException('You have no permission for this action.');
    }

    if (self::isBuildInProgress()) {
      return FALSE;
    }

    $node_path = Drupal::state()->get('usu_deploy.node_path');
    $yarn_path = Drupal::state()->get('usu_deploy.yarn_path');

    $env = "env NODE_PATH=$node_path YARN_PATH=$yarn_path";
    $cmd = "$env ../scripts/yarn-clean.sh";
    $output = shell_exec($cmd);
    Drupal::messenger()->addMessage(Markup::create(
      '<strong>"yarn clean" finished.<br/>Console output:</strong><br/><br/>' .
      nl2br(self::filterConsoleOutput($output))));
  }

  static function isBuildInProgress() {
    exec('pgrep -flx "node deploy.js"', $output, $ret);
    return $ret == 0;
  }

  static function readLogs() {
    $logs = file_get_contents(self::LOG_FILE);
    $logs = self::filterConsoleOutput($logs);
    return $logs;
  }

  static function filterConsoleOutput($text) {
    // remove console formatting
    return preg_replace('/\x1b\[[0-9]{0,2}[mAKG]/', '', $text);
  }

}
