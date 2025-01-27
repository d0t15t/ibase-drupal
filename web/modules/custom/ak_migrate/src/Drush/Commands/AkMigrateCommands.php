<?php

namespace Drupal\ak_migrate\Drush\Commands;

use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drupal\ak_migrate\Service\ImportArticlesToExhibitions;
use Drupal\Core\Utility\Token;
use Drush\Attributes as CLI;
use Drush\Commands\AutowireTrait;
use Drush\Commands\DrushCommands;

/**
 * A Drush commandfile.
 */
final class AkMigrateCommands extends DrushCommands {

  use AutowireTrait;

  /**
   * Constructs an AkMigrateCommands object.
   */
  public function __construct(
    private readonly ImportArticlesToExhibitions $utility,
  ) {
    parent::__construct();
  }

  /**
   * An example of the table output format.
   */
  #[CLI\Command(name: 'ak_migrate-articles_to_exhibitions', aliases: ['akm:a2e'])]
  #[CLI\FieldLabels(labels: [
    'source' => 'Source Article',
    'target' => 'Existing ID',
    'insert' => 'Insert'
  ])]
  #[CLI\DefaultTableFields(fields: ['source', 'target', 'insert'])]
  #[CLI\FilterDefaultField(field: 'source')]
  public function articles_to_exhibitions($options = ['format' => 'table']): RowsOfFields {
    $articles = $this->utility->getSourceArticles();
    $rows = array_map(function ($article) {
      $exhibition = $this->utility->creUpdateExhibition($article);
      return [
        'source' => $article->id() . ' / ' . substr($article->getTitle(), 0, 50),
        'target' => 'Exhibition: ' . $exhibition->id(),
        'insert' => $exhibition->isNew() ? 'Insert' : 'Update',
      ];
    }, $articles);
    return new RowsOfFields($rows);
  }

}
