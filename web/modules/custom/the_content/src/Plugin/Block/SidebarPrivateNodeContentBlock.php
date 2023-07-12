<?php

namespace Drupal\the_content\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Provides a sidebar private node content block.
 *
 * @Block(
 *   id = "the_content_sidebar_private_node_content",
 *   admin_label = @Translation("Sidebar Private Node Content"),
 *   category = @Translation("Custom")
 * )
 */
class SidebarPrivateNodeContentBlock extends BlockBase implements ContainerFactoryPluginInterface {

  protected RequestStack $requestStack;

  protected EntityTypeManagerInterface $entityTypeManager;

  protected RouteMatchInterface $route_match;

  protected AccountInterface $current_user;

  /**
   * Constructs a new SidebarNodeContentBlock instance.
   *
   * @param array $configuration
   *   The plugin configuration, i.e. an array with configuration values keyed
   *   by configuration option name.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param RouteMatchInterface $route_match
   * @param AccountInterface $current_user
   * @param EntityTypeManagerInterface $entity_type_manager
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, RouteMatchInterface $route_match, AccountInterface $current_user, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->route_match = $route_match;
    $this->current_user = $current_user;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_route_match'),
      $container->get('current_user'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $node = $this->route_match->getParameter('node');
    $view_builder = $this->entityTypeManager->getViewBuilder('node');
    $build_sidebar_private = $view_builder->view($node, 'sidebar_private');
    $build['content'] = [
      'sidebar_content_private' => $this->current_user->isAuthenticated() ? $build_sidebar_private : NULL,
    ];
    return $build;
  }

}
