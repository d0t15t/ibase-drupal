<?php

declare(strict_types=1);

namespace Drupal\ibase_seo\Controller;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\CacheableResponse;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Url;
use Drupal\ibase_seo\SchemaBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Serves robots.txt and llms.txt for the current site.
 */
class SeoTxtController extends ControllerBase {

  public function __construct(
    protected SchemaBuilder $builder,
    protected ModuleExtensionList $moduleList,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('ibase_seo.schema_builder'),
      $container->get('extension.list.module'),
    );
  }

  /**
   * Serves the core robots.txt rules plus this site's sitemap.
   */
  public function robots(): CacheableResponse {
    $lines = [file_get_contents($this->moduleList->getPath('ibase_seo') . '/assets/robots.txt')];
    $base = $this->builder->baseUrl();
    if ($this->moduleHandler()->moduleExists('simple_sitemap')) {
      $lines[] = 'Sitemap: ' . $base . '/sitemap.xml';
    }
    $lines[] = '# Site summary for AI assistants: ' . $base . '/llms.txt';
    $cacheability = (new CacheableMetadata())
      ->addCacheContexts(['url.site'])
      ->addCacheTags(['config:core.extension']);
    return $this->response(implode("\n", $lines) . "\n", $cacheability);
  }

  /**
   * Serves a Markdown summary of the site for language models (llms.txt).
   */
  public function llms(): CacheableResponse {
    $settings = $this->builder->settings();
    $organization = $this->builder->organization();
    $cacheability = (new CacheableMetadata())
      ->addCacheableDependency($settings)
      ->addCacheContexts(['url.site'])
      ->addCacheTags(['node_list']);

    $out = ['# ' . ($organization['name'] ?? $this->builder->siteName())];
    $intro = $settings->get('llms.intro') ?: ($organization['description'] ?? '');
    if ($intro) {
      $out[] = '> ' . trim(preg_replace('/\s+/', ' ', $intro));
    }

    $facts = [];
    if (!empty($organization['address'])) {
      $address = $organization['address'];
      $facts[] = '- Address: ' . implode(', ', array_filter([
        $address['streetAddress'] ?? NULL,
        trim(($address['postalCode'] ?? '') . ' ' . ($address['addressLocality'] ?? '')),
        $address['addressCountry'] ?? NULL,
      ]));
    }
    if (!empty($organization['geo'])) {
      $facts[] = sprintf('- Coordinates: %s, %s', $organization['geo']['latitude'], $organization['geo']['longitude']);
    }
    foreach (['openingHours' => 'Opening hours', 'telephone' => 'Phone', 'email' => 'Email'] as $key => $label) {
      if (!empty($organization[$key])) {
        $facts[] = "- $label: " . implode('; ', (array) $organization[$key]);
      }
    }
    $facts[] = '- Website: ' . $this->builder->baseUrl() . '/';
    foreach ($organization['sameAs'] ?? [] as $profile) {
      $facts[] = '- Profile: ' . $profile;
    }
    $out[] = implode("\n", $facts);

    $storage = $this->entityTypeManager()->getStorage('node');
    foreach ($settings->get('llms.bundles') ?: [] as $bundle => $heading) {
      $ids = $storage->getQuery()
        ->accessCheck(TRUE)
        ->condition('type', $bundle)
        ->condition('status', 1)
        ->sort('changed', 'DESC')
        ->range(0, 100)
        ->execute();
      if (!$ids) {
        continue;
      }
      $items = [];
      foreach ($storage->loadMultiple($ids) as $node) {
        $description = $this->builder->description($node);
        $items[] = sprintf('- [%s](%s)%s', $node->label(), $this->builder->url($node), $description ? ': ' . $description : '');
      }
      $out[] = "## $heading\n\n" . implode("\n", $items);
    }

    $term_storage = $this->entityTypeManager()->getStorage('taxonomy_term');
    foreach ($settings->get('llms.vocabularies') ?: [] as $vocabulary => $heading) {
      $ids = $term_storage->getQuery()
        ->accessCheck(TRUE)
        ->condition('vid', $vocabulary)
        ->condition('status', 1)
        ->sort('name')
        ->execute();
      if (!$ids) {
        continue;
      }
      $items = [];
      $people = in_array($vocabulary, $settings->get('person_vocabularies') ?: [], TRUE);
      foreach ($term_storage->loadMultiple($ids) as $term) {
        if ($people && !$this->builder->isPersonTerm($term)) {
          continue;
        }
        $items[] = sprintf('- [%s](%s)', $term->label(), $this->builder->url($term));
      }
      $cacheability->addCacheTags(['taxonomy_term_list']);
      $out[] = "## $heading\n\n" . implode("\n", $items);
    }

    $optional = [];
    if ($this->moduleHandler()->moduleExists('simple_sitemap')) {
      $optional[] = '- [XML sitemap](' . $this->builder->baseUrl() . '/sitemap.xml): every public page';
    }
    if ($optional) {
      $out[] = "## Optional\n\n" . implode("\n", $optional);
    }

    $cacheability->addCacheableDependency($this->builder->getCacheability());
    return $this->response(implode("\n\n", $out) . "\n", $cacheability, 'text/markdown; charset=utf-8');
  }

  /**
   * Wraps text in a cacheable plain text response.
   */
  protected function response(string $content, CacheableMetadata $cacheability, string $type = 'text/plain; charset=utf-8'): CacheableResponse {
    $response = new CacheableResponse($content, 200, ['Content-Type' => $type]);
    $response->addCacheableDependency($cacheability);
    return $response;
  }

}
