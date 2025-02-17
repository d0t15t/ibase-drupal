<?php

namespace Drupal\ak_theme\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Path\CurrentPathStack;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\Core\Url;
use Drupal\Core\Utility\LinkGeneratorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an exhibition years block.
 *
 * @Block(
 *   id = "ak_theme_exhibition_years",
 *   admin_label = @Translation("AK Exhibition Years Block"),
 *   category = @Translation("Custom")
 * )
 */
class ExhibitionYearsBlock extends BlockBase implements ContainerFactoryPluginInterface {

  protected \Drupal\Core\Database\Connection $database;

  protected UrlGeneratorInterface $urlGenerator;

  protected LinkGeneratorInterface $linkGenerator;

  protected CurrentPathStack $currentPath;

  /**
   * Constructs a new ExhibitionYearsBlock instance.
   *
   * @param array $configuration
   *   The plugin configuration, i.e. an array with configuration values keyed
   *   by configuration option name. The special key 'context' may be used to
   *   initialize the defined contexts by setting it to an array of context
   *   values keyed by context names.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection.
   * @param \Drupal\Core\Routing\UrlGeneratorInterface $url_generator
   *   The URL generator.
   * @param \Drupal\Core\Utility\LinkGeneratorInterface $link_generator
   *   The link_generator service.
   * @param \Drupal\Core\Path\CurrentPathStack $current_path
   *   The current path.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, Connection $connection, UrlGeneratorInterface $url_generator, LinkGeneratorInterface $link_generator, CurrentPathStack $current_path) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->database = $connection;
    $this->urlGenerator = $url_generator;
    $this->linkGenerator = $link_generator;
    $this->currentPath = $current_path;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('database'),
      $container->get('url_generator'),
      $container->get('link_generator'),
      $container->get('path.current')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $sql = "SELECT nodes.nid, EXTRACT(year FROM dates.field_dates_value) AS year
            FROM node_field_data as nodes
            INNER JOIN node__field_dates AS dates ON nodes.nid=dates.entity_id
            WHERE nodes.status=1
            GROUP BY EXTRACT(year FROM dates.field_dates_value)
            ORDER BY year DESC;";
    $results = array_values($this->database->query($sql)->fetchAllKeyed(1));
//    $cur_node = \Drupal::routeMatch()->getParameter('node');
//    $view_url_path = Url::fromRoute('view.exhibitions_archive.page')->toString();
    $current_year = \Drupal::request()->get('y');
    return [
      '#theme' => 'exhibition_years',
      '#years' => array_map(fn($y) => [
        'title' => trim($y),
        'uri' => sprintf('/?year=%s', $y),
        'current' => $y === $current_year,
      ], $results),
      '#title' => t('Filter exhibitions by year'),
      '#current' => $current_year,
    ];
  }

  private function getCurrentYearFromQuery(string $path): ?string {
    $keys = \Drupal::request()->get('keys');
    if (!isset($arr[0]) || !isset($arr[1])) return NULL;
    return is_numeric($arr[0]) && $arr[1] === 'archive'
      ? $arr[0] : NULL;
  }

}
