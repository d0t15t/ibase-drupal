<?php

declare(strict_types=1);

namespace Drupal\the_pager\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Database;
use Drupal\Core\Database\Query\PagerSelectExtender;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Pager\PagerManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;


/**
 * Provides a the pager block.
 *
 * @Block(
 *   id = "the_pager",
 *   admin_label = @Translation("THE Pager"),
 *   category = @Translation("Custom"),
 * )
 */
final class ThePagerBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Constructs the plugin instance.
   */
  public function __construct(
    array $configuration,
          $plugin_id,
          $plugin_definition,
    private readonly PagerManagerInterface $pagerManager,
    private readonly Connection $connection,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly ModuleHandlerInterface $moduleHandler,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new self(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('pager.manager'),
      $container->get('database'),
      $container->get('entity_type.manager'),
      $container->get('module_handler'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'example' => $this->t('Hello world!'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state): array {
    $form['example'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Example'),
      '#default_value' => $this->configuration['example'],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state): void {
    $this->configuration['example'] = $form_state->getValue('example');
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {

//    $query = $this->qetPagerQuery('node');

    $total = $this->getTotals('node', 'artist');

    $num_per_page = 10;

    $pager = $this->pagerManager->createPager($total, $num_per_page);

    $page = $pager->getCurrentPage();

    $offset = $num_per_page * $page;

    $rows = $this->getItemsQuery('node', 'artist', $offset, $num_per_page);

    $t=1;

    return [
      'pager-top' => ['#type' => 'pager'],
      'table'     => [
        '#type'       => 'list',
        '#rows'       => array_keys($rows)
      ],
//      'table'     => [
//        '#type'       => 'table',
//        '#header'     => ['Title', 'Type', 'Created'],
//        '#rows'       => $rows
//      ],
      'pager-bottom' => ['#type' => 'pager'],
    ];
//       $render = [];
////       $render[] = [
////        '#theme' => 'mymodule_results',
////        '#result' => $result,
////      ];
//
//      // Finally, add the pager to the render array, and return.
//      $render[] = ['#type' => 'pager'];
//      return $render;
  }


  private function getQuery(string $entity_type) {
    return $this->entityTypeManager->getStorage($entity_type)->getQuery();
  }

  private function getTotals(string $entity_type, string $bundle) {
    $query = $this->getQuery($entity_type);
    $query
      ->condition('type', $bundle)
      ->condition('status', 1)
      ->accessCheck(FALSE)
    ;
    $result = $query->execute();
    return count($result);
  }

  private function qetPagerQuery(string $table_name) {
    return $this->connection->select($table_name,)
      ->extend(PagerSelectExtender::class);
  }

  private function getItemsQuery(string $entity_type, string $bundle, int $offset, int $limit) {
    $query = $this->getQuery($entity_type);
    $query
      ->condition('type', $bundle)
      ->condition('status', 1)
      ->accessCheck(FALSE)
      ->range($offset, $limit);
    ;
    $result = $query->execute();
    return $result;
  }

  private function getPager() {
    // @TODO - implement data query.
    $t=1;
  }

}
