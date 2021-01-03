<?php

namespace Drupal\ibase_api\Plugin\rest\resource;

use Drupal\Core\Link;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManager;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\Plugin\ResourceBase;
use Psr\Log\LoggerInterface;

/**
 * Menu items resource
 *
 * @RestResource(
 *   id = "menu_items_resource",
 *   label = @Translation("Menu items resource."),
 *   uri_paths = {
 *     "canonical" = "/rest/menu-items",
 *   }
 * )
 */
class MenuItemsResource extends ResourceBase {

  /** @var LanguageManager */
  protected $languageManager;

  /** @var EntityTypeManagerInterface */
  protected $etm;

  /**
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param array $serializer_formats
   *   The available serialization formats.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   * @param LanguageManager $languageManager
   *   Language manager.
   * @param EntityTypeManagerInterface $etm
   *   Entity type manager.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    array $serializer_formats,
    LoggerInterface $logger,
    LanguageManager $languageManager,
    EntityTypeManagerInterface $etm
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
    $this->languageManager = $languageManager;
    $this->etm = $etm;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition
  ) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->getParameter('serializer.formats'),
      $container->get('logger.factory')->get('rest'),
      $container->get('language_manager'),
      \Drupal::entityTypeManager()
    );
  }

  public function get() {
    $etm = \Drupal::entityTypeManager();
    /** @var MenuLinkContent $menuItem */
    $menuItems = $etm->getStorage('menu_link_content')
      ->loadByProperties(['bundle' => 'menu_link_content', 'enabled' => 1]);
    $defaultLangcode = $this->languageManager->getDefaultLanguage()->getId();
    $menuNodes = [];
    foreach ($menuItems as $menuItem) {
      $langcodes = array_keys($menuItem->getTranslationLanguages());
      foreach ($langcodes as $langcode) {
        $menuItem = $langcode === $defaultLangcode ? $menuItem : $menuItem->getTranslation($langcode);
        if ($langcode !== $menuItem->language()->getId()) {
          continue;
        }
        $link = $menuItem->get('link')->getValue();
        $uri = $link[0]['uri'] ?? NULL;
        if (!$uri) {
          continue;
        }
        $options = $langcode ? ['language' => $langcode] : [];
        $url = Url::fromUri($uri, $options);
        /** @var \Drupal\path_alias\AliasManager $pathAliasManager */
        $pathAliasManager = \Drupal::service('path_alias.manager');
        $internalPath = $url->getInternalPath();
        // This method causes errors, b/c it calls a render method which is illegal here.
        $path = sprintf('/%s', $internalPath);
        $pathAlias = $pathAliasManager->getAliasByPath($path, $langcode);
        if ($defaultLangcode !== $langcode) {
          $pathAlias = sprintf('/%s%s', $langcode, $pathAlias);
        }
        $menuNodes[] = [
          'uid' => sprintf('%s-%s', $menuItem->uuid(), $langcode),
          'url' => $pathAlias,
          'title' => $menuItem->label(),
          'menuName' => $menuItem->getMenuName(),
          'langcode' => $menuItem->language()->getId(),
          'default_lang' => $defaultLangcode,
          'expanded' => $menuItem->isExpanded(),
          'external' => $menuItem->get('external')->getString(),
          'description' => $menuItem->get('description')->getString(),
          'weight' => $menuItem->getWeight(),
          'parentId' => $menuItem->getParentId(),
        ];
      }
    }
    return new ModifiedResourceResponse(['nodes' => $menuNodes]);
  }

}
