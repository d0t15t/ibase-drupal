<?php

namespace Drupal\ibase_gatsby_deploy\Commands;

use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drush\Commands\DrushCommands;

class IbaseGatsbyDeployCommands extends DrushCommands {

  /**
   * Build gatsby frontend.
   *
   * @usage gatsby:build goldies
   *   Build gatsby frontend.
   *
   * @command gatsby:build
   * @aliases gatb
   */
  public function gatsbyBuild() {
    \Drupal::service('ibase_gatsby_deploy.service')->deploy();
    $this->logger()->success(dt('Achievement unlocked.'));
  }

  /**
   * Command description here.
   *
   * @param $arg1
   *   Argument description.
   * @param array $options
   *   An associative array of options whose values come from cli, aliases, config, etc.
   * @option option-name
   *   Description
   *
   * @command gatsby:foo
   * @aliases foobar
   */
  public function commandName($arg1, $options = ['option-name' => 'default']) {
    $this->logger()->success(dt('Achievement unlocked.'));
  }

  /**
   * An example of the table output format.
   *
   * @param array $options An associative array of options whose values come from cli, aliases, config, etc.
   *
   * @field-labels
   *   group: Group
   *   token: Token
   *   name: Name
   * @default-fields group,token,name
   *
   * @command ibase_gatsby_deploy:token
   * @aliases token
   *
   * @filter-default-field name
   * @return \Consolidation\OutputFormatters\StructuredData\RowsOfFields
   */
  public function token($options = ['format' => 'table']) {
    $all = \Drupal::token()->getInfo();
    foreach ($all['tokens'] as $group => $tokens) {
      foreach ($tokens as $key => $token) {
        $rows[] = [
          'group' => $group,
          'token' => $key,
          'name' => $token['name'],
        ];
      }
    }
    return new RowsOfFields($rows);
  }
}
