<?php

namespace Drupal\ibase_api\Plugin\rest\resource;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\ibase_api\Service\IbaseApiTokenService;
use Drupal\metatag\MetatagTagPluginManager;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\Core\Language\LanguageManager;
use Drupal\core\Entity\EntityInterface;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\metatag\MetatagManager;
use Drupal\rest\ResourceResponse;
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
  protected $metatagService;

  /** @var MetatagTagPluginManager */
  protected $tagPluginManager;

  /** @var IbaseApiTokenService */
  protected $tokenService;

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
   * @param MetatagManager $metatagService
   *   Metatag manager service.
   * @param IbaseApiTokenService $tokenService
   *   Custom Token service.
   * @param MetatagTagPluginManager $tagPluginManager
   *   Metatag plugin manager.
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
    MetatagManager $metatagService,
    IbaseApiTokenService $tokenService,
    MetatagTagPluginManager $tagPluginManager,
    LanguageManager $languageManager,
    EntityTypeManagerInterface $etm
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
    $this->metatagService = $metatagService;
    $this->tokenService = $tokenService;
    $this->tagPluginManager = $tagPluginManager;
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
      $container->get('ibase_api.token'),
      $container->get('plugin.manager.metatag.tag'),
      $container->get('language_manager'),
      \Drupal::entityTypeManager()
    );
  }

  /**
   * @return \Drupal\rest\ModifiedResourceResponse|\Drupal\rest\ResourceResponse
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function get() {
    $query = \Drupal::request()->query->all();
    $global = $query['q'] ?? NULL;
    if ($global) {
      return $this->globalMetaTagsResponse();
    }
    $type = $query['type'] ?? NULL;
    $bundle = $query['bundle'] ?? NULL;
    if ($type && $bundle) {
      return $this->allContentTypeMetaTagsResponse($type, $bundle);
    }
    return $this->returnResponseError();
  }

  private $configNameBase = 'metatag.metatag_defaults';

  /**
   * @return array|string[]
   */
  private function getGlobalTagTypes(): array {
    // Is there a cooler way to do this?
    return [
      'global', 'user', 'node', 'user', 'taxonomy_term', 'front', '404', '403',
    ];
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
    // @Todo: batch this for large collections?
    $entities = $storage->loadByProperties([
      'type' => $bundle,
      'status' => 1,
    ]);
    $defaultLangcode = $this->languageManager->getDefaultLanguage()->getId();
    $nodeTags = [];
    foreach ($entities as $entity) {
      /** @var \Drupal\node\Entity\Node $entity */
      $langcodes = array_keys($entity->getTranslationLanguages());
      foreach ($langcodes as $langcode) {
        $translation = $entity->getTranslation($langcode);
        $translatedMetatags = $this->getContentMetatagFieldValue($translation);
        $mergedMetaTags = $this->mergeMetaTagsContext($translatedMetatags, 'node', $langcode);
        $tokenReplacements = [$translation->getEntityTypeId() => $translation];
        $finalMetaTags = $this->processMetaTagsTokenValues($mergedMetaTags, $tokenReplacements);
        $finalMetaTags['article'] = TRUE;
        $nodeTags[] = [
          'langcode' => $langcode,
          'default_lang' => $langcode === $defaultLangcode,
          'metatags_group' => 'content',
          'drupal_internal__nid' => $translation->id(),
          'uid' => sprintf('%s-%s', $translation->uuid(), $langcode),
          'entity' => $type,
          'bundle' => $bundle,
          'metatags' => $finalMetaTags,
        ];
      }
    }
    return new ModifiedResourceResponse(['nodes' => $nodeTags]);
  }

  /**
   * @return \Drupal\rest\ResourceResponse
   */
  private function globalMetaTagsResponse(): ResourceResponse {
    $configNameBase = $this->configNameBase;
    $configKeys = $this->getGlobalTagTypes();
    $langManager = $this->languageManager;
    $langcodes = array_keys($langManager->getLanguages());
    $defaultLangcode = $langManager->getDefaultLanguage()->getId();
    $nodes = [];
    foreach($configKeys as $configKey) {
      $configName = sprintf('%s.%s', $configNameBase, $configKey);
      $defaultTags = $this->getConfigMetaTags($configName);
      $defaultTags['id'] = sprintf('%s-%s', $configName, $defaultLangcode);
      $defaultTags['type'] = $configKey;
      $defaultTags['langcode'] = $defaultLangcode;
      $defaultTags['lang_default'] = TRUE;

      $nodes[] = $defaultTags;
      foreach($langcodes as $langcode) {
        if ($langcode === $defaultLangcode) continue;
        $langTags = $this->getConfigMetaTags($configName, $langcode);
        $langTags['langcode'] = $langcode;
        $defaultTags['id'] = sprintf('%s-%s', $configName, $langcode);
        $langTags['lang_default'] = FALSE;

        $langTagNode = [];
        foreach($defaultTags as $key => $defaultTag) {
          // If no tag is set for the language value, fallback to default language value.
          $langTagNode[$key] = $langTags[$key] ?? $defaultTag;
        }
        $nodes[] = $langTagNode;
      }
    }
    $responseData = [
      'languages' => $langcodes,
      'lang_default' => $defaultLangcode,
      'nodes' => $nodes,
    ];
    $response = new ResourceResponse($responseData);
    $account = \Drupal::currentUser();
    $response->addCacheableDependency($account);
    return $response;
  }

  /**
   * @param array $resolvedTags
   * @param array $tokenReplacements
   *
   * @return array
   */
  private function processMetaTagsTokenValues($resolvedTags, $tokenReplacements): array {
    $processed = [];
    foreach ($resolvedTags as $tag => $string) {
      $processed[$tag] = $this->tokenService->replace($string, $tokenReplacements, []);
    }
    return $processed;
  }

  /**
   * @param EntityInterface $entity
   *
   * @return array
   */
  private function getContentMetatagFieldValue($entity): array {
    if($entity->get('field_metatags')->isEmpty()) return [];
    return unserialize($entity->get('field_metatags')->getString());
  }

  /**
   * Return a basic error.
   *
   * @return \Drupal\rest\ResourceResponse
   */
  private function returnResponseError(): ResourceResponse {
    return new ResourceResponse();
  }

  /**
   * @param string $configName
   * @param null $langcode
   *
   * @return array|mixed|null
   */
  private function getConfigMetaTags($configName, $langcode = NULL) {
    if (!$langcode) {
      return \Drupal::config($configName)->get('tags');
    }
    else {
      return $this->languageManager
        ->getLanguageConfigOverride($langcode, $configName)
        ->get('tags');
    }
  }

  /**
   * @param array $tags
   * @param string $tagType
   * @param string $langcode
   *
   * @return array
   */
  private function mergeMetaTagsContext($tags, $tagType, $langcode): array {
    // No langcode for default.
    $langcode = $langcode === $this->languageManager->getDefaultLanguage()->getId() ? NULL : $langcode;
    $resolvedTags = array_merge(
      $this->getConfigMetaTags($this->configNameBase . '.' . 'global') ?? [], #'globalConfig
      $this->getConfigMetaTags($this->configNameBase . '.' . 'global', $langcode) ?? [], #globalConfigTrans
      $this->getConfigMetaTags($this->configNameBase . '.' . $tagType) ?? [], #typeConfig
      $this->getConfigMetaTags($this->configNameBase . '.' . $tagType, $langcode) ?? [], #typeConfigTrans
      $tags
    );
    return $resolvedTags;
  }

  private function getTagsDiff($contentTags, $globalTags) {

  }

  /**
   * @param string $tagKey
   * @param array $resolverSequence
   *
   * @return string
   */
  private function resolveMetaTag($tagKey, $resolverSequence): string {
    // @todo: change to an array merging sequence.
    foreach($resolverSequence as $set) {
      if (empty($set[$tagKey])) continue;
      return $set[$tagKey];
    }
  }
}
