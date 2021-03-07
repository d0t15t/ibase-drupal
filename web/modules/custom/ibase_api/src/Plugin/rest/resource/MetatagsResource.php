<?php

namespace Drupal\ibase_api\Plugin\rest\resource;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\Core\Language\LanguageManager;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\Component\Utility\Html;
use Drupal\metatag\MetatagManager;
use Drupal\rest\ResourceResponse;
use Drupal\metatag\MetatagToken;
use Drupal\Core\State\State;
use Psr\Log\LoggerInterface;

/**
 * Metatags resource
 *
 * @RestResource(
 *   id = "metatags_resource",
 *   label = @Translation("Metatags resource."),
 *   uri_paths = {
 *     "canonical" = "/rest/meta-tags",
 *   }
 * )
 */
class MetatagsResource extends ResourceBase {

  /** @var MetatagManager */
  protected $metatagManager;

  /** @var MetatagToken */
  protected $tokenService;

  /** @var State */
  protected $state;

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
   * @param LoggerInterface $logger
   *   A logger instance.
   * @param MetatagManager $metatagManager
   *   Metatag manager service.
   * @param MetatagToken $tokenService
   *   Custom Token service.
   * @param State $state
   *   State.
   * @param LanguageManager $languageManager
   *   Language service.
   * @param EntityTypeManagerInterface $etm
   *   Entity Service.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    array $serializer_formats,
    LoggerInterface $logger,
    MetatagManager $metatagManager,
    MetatagToken $tokenService,
    State $state,
    LanguageManager $languageManager,
    EntityTypeManagerInterface $etm
  ) {
    parent::__construct(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $serializer_formats,
      $logger
    );
    $this->metatagManager = $metatagManager;
    $this->tokenService = $tokenService;
    $this->state = $state;
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
      $container->get('metatag.manager'),
      $container->get('metatag.token'),
      $container->get('state'),
      $container->get('language_manager'),
      \Drupal::entityTypeManager()
    );
  }

  const DEFAULT_LANGCODE = 'en-us';

  /**
   * @return \Drupal\rest\ModifiedResourceResponse|\Drupal\rest\ResourceResponse
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function get() {
    $query = \Drupal::request()->query->all();

    $type = $query['type'] ?? NULL;
    $bundle = $query['bundle'] ?? NULL;
    if ($type && $bundle) {
      return $this->allContentTypeMetaTagsResponse($type, $bundle);
    }

    return new ResourceResponse(['@todo: global query with paging.']);
  }

  /**
   * @param $type
   * @param $bundle
   *
   * @return \Drupal\rest\ModifiedResourceResponse|\Drupal\rest\ResourceResponse
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  private function allContentTypeMetaTagsResponse($type, $bundle) {
    $storage = $this->etm->getStorage($type);
    if (!$storage) {
      return $this->returnResponseError();
    }

    $defaultLangcode = $this->languageManager->getDefaultLanguage()->getId();
    $baseUrl = $this->getBaseUrl();
    $frontEndUrl = $this->getFrontendUrl();

    $nodeTags = [];
    foreach ($storage->loadByProperties(['type' => $bundle]) as $entity) {
      /** @var \Drupal\node\Entity\Node $entity */
      $isStartPage = $this->isStartPage($entity);
      $langcodes = array_keys($entity->getTranslationLanguages());
      $translationSet = [];
      foreach ($langcodes as $langcode) {
        $entityTranslation = $entity->getTranslation($langcode);
        $metatags = $this->metatagManager->tagsFromEntityWithDefaults($entityTranslation);
        array_walk($metatags,
          function(&$value) use ($entityTranslation, $baseUrl, $frontEndUrl) {
            // Tokenize.
            $value = $this->tokenService->replace($value, [$entityTranslation->getEntityTypeId() => $entityTranslation]);
            // Process for frontend base url.
            $value = str_replace($baseUrl, $frontEndUrl, $value);
            // Decode html.
            $value = HTML::decodeEntities($value);
          });

        if ($isStartPage) {
          $canonical_langcode = $langcode === $defaultLangcode ? $this::DEFAULT_LANGCODE : $langcode;
          $metatags['canonical_url'] = sprintf('%s/%s/', $frontEndUrl, $canonical_langcode);
        }

        $translationSet[$langcode] = [
          'langcode' => $langcode,
          'lang_id' => $defaultLangcode === $langcode ? $this::DEFAULT_LANGCODE : $langcode,
          'default_lang' => $langcode === $defaultLangcode,
          'metatags_group' => 'content',
          'drupal_internal__nid' => $entityTranslation->id(),
          'uid' => sprintf('%s-%s', $entityTranslation->uuid(), $langcode),
          'entity' => $type,
          'bundle' => $bundle,
          'metatags' => $metatags
        ];
      }
      $nodeTags[$entity->id()] = $translationSet;
    }

    $translationTags = array_map(function($translationSet) {
      $defaultSet = array_filter($translationSet, function($item) {
        return $item['default_lang'];
      });
      $defaultHref = array_shift($defaultSet);
      $hreflangs = [
          [
            'rel' => 'alternative',
            'hreflang' => 'x-default',
            'href' => $defaultHref['metatags']['canonical_url'],
          ]
        ] + array_map(function($translationTags) {
          return [
            'rel' => 'alternative',
            'hreflang' => $translationTags['lang_id'],
            'href' => $translationTags['metatags']['canonical_url'],
          ];
        }, $translationSet);
      $translationSet['links'] = array_values($hreflangs);
      return $translationSet;
    }, $nodeTags);

    $metatags = [];
    $languages = array_keys($this->languageManager->getLanguages());
    foreach ($translationTags as $translationTagSet) {
      foreach ($languages as $langcode) {
        if (!isset($translationTagSet[$langcode])) continue;
        $newSet = $translationTagSet[$langcode];
        $newSet['links'] = $translationTagSet['links'];
        $metatags[] = $newSet;
      }
    }

    return new ModifiedResourceResponse(['nodes' => $metatags]);
  }

  private function getDefaultLangcode() {

  }

  private function getBaseUrl() {
    return sprintf('%s://%s', \Drupal::request()->getScheme(), \Drupal::request()->getHost());
  }

  private function getFrontendUrl() {
    return 'http://golides.test';
//    return $this->state->get($this->apiService->getSettingsStateKey('frontend_base_path'));
  }

  private function isStartPage($entity) {
    if ($entity->bundle() !== 'page') return FALSE;
    return $entity->id() == 1;
//    return $entity->id() === $this->apiService->state->get('usu_settings.front_page');
  }

  /**
   * Return a basic error.
   *
   * @return \Drupal\rest\ResourceResponse
   */
  private function returnResponseError(): ResourceResponse {
    return new ResourceResponse();
  }

}
