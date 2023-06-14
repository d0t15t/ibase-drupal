<?php

namespace Drupal\the_content\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a the content links block.
 *
 * @Block(
 *   id = "the_content_the_content_links",
 *   admin_label = @Translation("THE content links"),
 *   category = @Translation("Custom")
 * )
 */
class TheContentLinksBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Constructs a new TheContentLinksBlock instance.
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
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build(): ?array {
    $node = \Drupal::routeMatch()->getParameter('node');
    $is_node = $node instanceof \Drupal\node\NodeInterface;
    $label = $is_node ? $node->label() : NULL;
    return $is_node ? [
      '#markup' => "<h4><a href='#node-content'>$label</a></h4>",
    ] : NULL;
  }

}
