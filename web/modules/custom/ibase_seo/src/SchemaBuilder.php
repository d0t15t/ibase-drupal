<?php

declare(strict_types=1);

namespace Drupal\ibase_seo;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\Unicode;
use Drupal\Core\Breadcrumb\BreadcrumbBuilderInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Url;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\file\FileInterface;
use Drupal\media\MediaInterface;
use Drupal\node\NodeInterface;
use Drupal\taxonomy\TermInterface;

/**
 * Builds schema.org structured data and page-specific meta values for nodes.
 */
class SchemaBuilder {

  /**
   * Image fields to look at, in order of preference.
   */
  const IMAGE_FIELDS = [
    'field_images',
    'field_featured_image',
    'field_media_image',
    'field_image',
    'field_portrait',
    'field_media_images',
  ];

  /**
   * UN/CEFACT unit codes for the artwork dimension units.
   */
  const UNIT_CODES = [
    'mm' => 'MMT',
    'cm' => 'CMT',
    'm' => 'MTR',
    'in' => 'INH',
    'inch' => 'INH',
  ];

  /**
   * Cacheability collected while building, to be bubbled to the page.
   */
  protected CacheableMetadata $cacheability;

  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected LanguageManagerInterface $languageManager,
    protected BreadcrumbBuilderInterface $breadcrumb,
    protected RouteMatchInterface $routeMatch,
    protected FileUrlGeneratorInterface $fileUrlGenerator,
  ) {
    $this->cacheability = new CacheableMetadata();
  }

  /**
   * Returns the cacheability collected so far.
   */
  public function getCacheability(): CacheableMetadata {
    return $this->cacheability;
  }

  /**
   * Builds the JSON-LD graph for the current page.
   *
   * @return array
   *   A schema.org document with an @graph, or an empty array.
   */
  public function buildPageGraph(): array {
    $settings = $this->settings();
    $this->cacheability->addCacheableDependency($settings);
    $this->cacheability->addCacheContexts(['url.site', 'url.path', 'languages:language_content']);

    $langcode = $this->langcode();
    $base = $this->baseUrl();
    $graph = [];

    $organization = $this->organization();
    if ($organization) {
      $graph[] = $organization;
    }

    $front = Url::fromRoute('<front>', [], ['absolute' => TRUE])->toString(TRUE);
    $this->cacheability->addCacheableDependency($front);
    $graph[] = array_filter([
      '@type' => 'WebSite',
      '@id' => $base . '/#website',
      'url' => $front->getGeneratedUrl(),
      'name' => $this->siteName(),
      'description' => $settings->get('organization.description') ?: NULL,
      'inLanguage' => $this->languageCodes(),
      'publisher' => $organization ? ['@id' => $organization['@id']] : NULL,
    ]);

    // Error pages only get the site-wide items.
    if (in_array($this->routeMatch->getRouteName(), ['system.403', 'system.404'], TRUE)) {
      return [
        '@context' => 'https://schema.org',
        '@graph' => array_values($graph),
      ];
    }

    $node = $this->routeMatch->getParameter('node');
    $is_front = $node instanceof NodeInterface && $this->isFrontPage($node);
    $current = $is_front ? $front : Url::fromRoute('<current>', [], ['absolute' => TRUE])->toString(TRUE);
    $this->cacheability->addCacheableDependency($current);
    $page_url = $current->getGeneratedUrl();
    $page = [
      '@type' => 'WebPage',
      '@id' => $page_url . '#webpage',
      'url' => $page_url,
      'isPartOf' => ['@id' => $base . '/#website'],
      'inLanguage' => $langcode,
    ];

    $breadcrumb = $this->breadcrumbList($page_url);
    if ($breadcrumb) {
      $graph[] = $breadcrumb;
      $page['breadcrumb'] = ['@id' => $breadcrumb['@id']];
    }

    if ($node instanceof NodeInterface && $this->routeMatch->getRouteName() === 'entity.node.canonical') {
      $node = $this->translation($node, $langcode);
      $this->cacheability->addCacheableDependency($node);
      $page['name'] = $is_front ? $this->siteName() : $node->label();
      $page['description'] = $is_front ? ($settings->get('organization.description') ?: NULL) : $this->description($node);
      $page['datePublished'] = date('c', (int) $node->getCreatedTime());
      $page['dateModified'] = date('c', (int) $node->getChangedTime());
      if ($image = $this->image($node)) {
        $page['primaryImageOfPage'] = $this->imageObject($image);
      }
      if ($is_front) {
        if ($organization) {
          $page['about'] = ['@id' => $organization['@id']];
        }
      }
      elseif ($entity = $this->nodeEntity($node, $langcode)) {
        $entity['@id'] = $page_url . '#main';
        $entity['mainEntityOfPage'] = ['@id' => $page['@id']];
        $page['mainEntity'] = ['@id' => $entity['@id']];
        $page['@type'] = $this->pageType($node);
        $graph[] = $entity;
      }
      $page_types = $settings->get('page_types') ?: [];
      if (!empty($page_types[$node->id()])) {
        $page['@type'] = $page_types[$node->id()];
      }
    }

    $term = $this->routeMatch->getParameter('taxonomy_term');
    if ($term instanceof TermInterface && $this->routeMatch->getRouteName() === 'entity.taxonomy_term.canonical' && $this->isPersonTerm($term)) {
      $term = $this->translation($term, $langcode);
      $this->cacheability->addCacheableDependency($term);
      $person = $this->termPerson($term);
      $person['@id'] = $page_url . '#main';
      $person['mainEntityOfPage'] = ['@id' => $page['@id']];
      $page['@type'] = 'ProfilePage';
      $page['name'] = $term->label();
      $page['description'] = $person['description'] ?? NULL;
      $page['mainEntity'] = ['@id' => $person['@id']];
      $graph[] = $person;
    }

    $graph[] = array_filter($page);

    return [
      '@context' => 'https://schema.org',
      '@graph' => array_values($graph),
    ];
  }

  /**
   * Builds the organization (gallery, association) behind the site.
   */
  public function organization(): array {
    $org = $this->settings()->get('organization') ?: [];
    $name = $org['name'] ?: $this->siteName();
    if (!$name) {
      return [];
    }
    $base = $this->baseUrl();
    $data = [
      '@type' => $org['type'] ?: 'Organization',
      '@id' => $base . '/#organization',
      'name' => $name,
      'alternateName' => $org['alternate_name'] ?: NULL,
      'description' => $org['description'] ?: NULL,
      'url' => $base . '/',
      'logo' => $org['logo'] ? ['@type' => 'ImageObject', 'url' => $org['logo']] : NULL,
      'image' => $org['image'] ?: ($org['logo'] ?: NULL),
      'telephone' => $org['telephone'] ?: NULL,
      'email' => $org['email'] ?: NULL,
      'sameAs' => array_values($org['same_as'] ?? []) ?: NULL,
    ];
    if ($org['street_address'] || $org['locality']) {
      $data['address'] = array_filter([
        '@type' => 'PostalAddress',
        'streetAddress' => $org['street_address'],
        'addressLocality' => $org['locality'],
        'postalCode' => $org['postal_code'],
        'addressRegion' => $org['region'],
        'addressCountry' => $org['country'],
      ]);
    }
    if ($org['latitude'] && $org['longitude']) {
      $data['geo'] = [
        '@type' => 'GeoCoordinates',
        'latitude' => (float) $org['latitude'],
        'longitude' => (float) $org['longitude'],
      ];
      $data['hasMap'] = sprintf('https://www.openstreetmap.org/?mlat=%s&mlon=%s#map=17/%s/%s', $org['latitude'], $org['longitude'], $org['latitude'], $org['longitude']);
    }
    if (!empty($org['opening_hours'])) {
      $data['openingHours'] = array_values($org['opening_hours']);
      $data['openingHoursSpecification'] = $this->openingHoursSpecification($org['opening_hours']) ?: NULL;
    }
    if (!empty($org['founding_date'])) {
      $data['foundingDate'] = $org['founding_date'];
    }
    if (!empty($org['knows_about'])) {
      $data['knowsAbout'] = array_values($org['knows_about']);
    }
    if (!empty($org['locality'])) {
      $data['areaServed'] = ['@type' => 'City', 'name' => $org['locality']];
    }
    return array_filter($data);
  }

  /**
   * Converts opening hours like "Tu-Fr 16:00-18:00" to structured data.
   */
  protected function openingHoursSpecification(array $opening_hours): array {
    $days = ['Mo' => 'Monday', 'Tu' => 'Tuesday', 'We' => 'Wednesday', 'Th' => 'Thursday', 'Fr' => 'Friday', 'Sa' => 'Saturday', 'Su' => 'Sunday'];
    $keys = array_keys($days);
    $specification = [];
    foreach ($opening_hours as $line) {
      if (!preg_match('/^([A-Z][a-z])(?:-([A-Z][a-z]))?\s+(\d{1,2}:\d{2})-(\d{1,2}:\d{2})$/', trim($line), $m) || !isset($days[$m[1]])) {
        continue;
      }
      $from = array_search($m[1], $keys);
      $to = $m[2] && isset($days[$m[2]]) ? array_search($m[2], $keys) : $from;
      $specification[] = [
        '@type' => 'OpeningHoursSpecification',
        'dayOfWeek' => array_map(fn($key) => 'https://schema.org/' . $days[$key], array_slice($keys, $from, $to - $from + 1)),
        'opens' => $m[3],
        'closes' => $m[4],
      ];
    }
    return $specification;
  }

  /**
   * Builds the schema.org entity a node describes, keyed by its bundle.
   */
  public function nodeEntity(NodeInterface $node, string $langcode): ?array {
    $url = $this->url($node);
    $description = $this->description($node);
    $image = $this->image($node);
    $organization = ['@id' => $this->baseUrl() . '/#organization'];
    $common = [
      'name' => $node->label(),
      'url' => $url,
      'description' => $description,
      'image' => $image ? $this->imageObject($image) : NULL,
      'inLanguage' => $langcode,
    ];

    switch ($node->bundle()) {
      case 'artist':
        $data = ['@type' => 'Person'] + $common;
        unset($data['inLanguage']);
        $data['jobTitle'] = $langcode === 'de' ? 'Künstler*in' : 'Artist';
        if ($this->has($node, 'field_instagam_handle')) {
          $handle = ltrim(trim((string) $node->get('field_instagam_handle')->value), '@');
          if ($handle && !str_contains($handle, '/')) {
            $data['sameAs'] = ['https://www.instagram.com/' . $handle];
          }
        }
        if ($this->has($node, 'field_artworks')) {
          $data['workExample'] = $this->references($node, 'field_artworks', 'VisualArtwork', 20);
        }
        $data['affiliation'] = $organization;
        return array_filter($data);

      case 'artwork':
        $data = ['@type' => 'VisualArtwork'] + $common;
        $data['creator'] = $this->references($node, 'field_artists', 'Person');
        if ($this->has($node, 'field_year')) {
          $data['dateCreated'] = (string) $node->get('field_year')->value;
        }
        if ($this->has($node, 'field_medium')) {
          $media = [];
          foreach ($node->get('field_medium')->referencedEntities() as $term) {
            $media[] = $this->translation($term, $langcode)->label();
          }
          $data['artMedium'] = implode(', ', $media);
        }
        if ($this->has($node, 'field_edition')) {
          $data['artEdition'] = (string) $node->get('field_edition')->value;
        }
        $data += $this->dimensions($node);
        $data['provider'] = $organization;
        return array_filter($data);

      case 'exhibition':
      case 'events':
        $data = ['@type' => $node->bundle() === 'exhibition' ? 'ExhibitionEvent' : 'Event'] + $common;
        $data += $this->eventDates($node);
        if (empty($data['startDate'])) {
          // Events without a date are not valid schema.org events.
          return array_filter(['@type' => 'CreativeWork'] + $common);
        }
        $data['eventAttendanceMode'] = 'https://schema.org/OfflineEventAttendanceMode';
        $data['eventStatus'] = 'https://schema.org/EventScheduled';
        $data['location'] = $this->place($node);
        $data['organizer'] = $organization;
        $data['performer'] = array_merge($this->references($node, 'field_artists', 'Person'), $this->personTerms($node));
        $data['workFeatured'] = $this->references($node, 'field_artworks', 'VisualArtwork', 30);
        $data['isAccessibleForFree'] = $this->settings()->get('free_admission') ? TRUE : NULL;
        return array_filter($data);

      case 'article':
        $dates = $this->eventDates($node);
        $data = ['@type' => !empty($dates['startDate']) && $this->has($node, 'field_dates') ? 'Event' : 'NewsArticle'] + $common;
        if ($data['@type'] === 'Event') {
          // Articles typed as exhibitions are exhibitions.
          if ($this->has($node, 'field_type') && in_array(mb_strtolower((string) $node->get('field_type')->entity?->label()), ['exhibition', 'ausstellung'], TRUE)) {
            $data['@type'] = 'ExhibitionEvent';
          }
          $data += $dates;
          $data['eventAttendanceMode'] = 'https://schema.org/OfflineEventAttendanceMode';
          $data['eventStatus'] = 'https://schema.org/EventScheduled';
          $data['location'] = $this->place($node);
          $data['organizer'] = $organization;
          $data['performer'] = $this->personTerms($node);
          $data['isAccessibleForFree'] = $this->settings()->get('free_admission') ? TRUE : NULL;
          return array_filter($data);
        }
        $data['headline'] = Unicode::truncate($node->label(), 110, TRUE, TRUE);
        $data['datePublished'] = $dates['startDate'] ?? date('c', (int) $node->getCreatedTime());
        $data['dateModified'] = date('c', (int) $node->getChangedTime());
        $data['author'] = $organization;
        $data['publisher'] = $organization;
        return array_filter($data);

      case 'project':
        $data = ['@type' => 'Project'] + $common;
        unset($data['inLanguage']);
        $data['parentOrganization'] = $organization;
        $data['member'] = $this->references($node, 'field_artists', 'Person');
        return array_filter($data);

      case 'workgroup':
        $data = ['@type' => 'Collection'] + $common;
        $data['hasPart'] = $this->references($node, 'field_artworks', 'VisualArtwork', 30);
        $data['provider'] = $organization;
        return array_filter($data);
    }
    return NULL;
  }

  /**
   * Returns a meta description for a node: hand-written, summary or generated.
   */
  public function description(NodeInterface $node): ?string {
    $overrides = $this->settings()->get('description_overrides') ?: [];
    if (!empty($overrides[$node->id()])) {
      return $overrides[$node->id()];
    }
    foreach (['body', 'field_biography', 'field_headline'] as $field_name) {
      if ($this->has($node, $field_name)) {
        $item = $node->get($field_name)->first();
        $text = $this->plainText((string) (($item->summary ?? '') ?: $item->value));
        if (mb_strlen($text) >= 50) {
          return $text;
        }
      }
    }

    $de = $this->contentLangcode($node->language()->getId()) === 'de';
    $org = $this->settings()->get('organization.name') ?: $this->siteName();
    $place = $this->settings()->get($de ? 'place_de' : 'place');
    $at = $org . ($place ? ' ' . $place : '');
    $title = $node->label();
    switch ($node->bundle()) {
      case 'artist':
        return $de
          ? "$title – Biografie, Werke und Ausstellungen bei $at."
          : "$title – biography, artworks and exhibitions at $at.";

      case 'artwork':
        $parts = [$title];
        $artists = array_map(fn($a) => $a->label(), $this->has($node, 'field_artists') ? $node->get('field_artists')->referencedEntities() : []);
        if ($artists) {
          $parts[] = ($de ? 'von ' : 'by ') . implode(', ', $artists);
        }
        if ($this->has($node, 'field_year')) {
          $parts[] = $node->get('field_year')->value;
        }
        return implode(', ', $parts) . ($de ? " – Kunstwerk bei $at." : " – artwork at $at.");

      case 'exhibition':
        return $de ? "$title – Ausstellung bei $at." : "$title – exhibition at $at.";

      case 'workgroup':
        return $de ? "$title – Werkgruppe bei $at." : "$title – a group of works at $at.";
    }
    return NULL;
  }

  /**
   * Finds the main image of an entity.
   *
   * @return array|null
   *   Keys: url, width, height, alt, type.
   */
  public function image(ContentEntityInterface $entity): ?array {
    foreach (self::IMAGE_FIELDS as $field_name) {
      if (!$this->has($entity, $field_name)) {
        continue;
      }
      $item = $entity->get($field_name)->first();
      $target = $item->entity ?? NULL;
      $alt = $item->alt ?? '';
      if ($target instanceof MediaInterface) {
        $this->cacheability->addCacheableDependency($target);
        $source_field = $target->getSource()->getConfiguration()['source_field'] ?? NULL;
        if (!$source_field || !$target->hasField($source_field) || $target->get($source_field)->isEmpty()) {
          continue;
        }
        $item = $target->get($source_field)->first();
        $target = $item->entity;
        $alt = $item->alt ?: $target?->label() ?? '';
      }
      if ($target instanceof FileInterface && str_starts_with((string) $target->getMimeType(), 'image/')) {
        return $this->styledImage($target, (int) ($item->width ?? 0), (int) ($item->height ?? 0), (string) $alt);
      }
    }
    return NULL;
  }

  /**
   * Strips markup and shortens text to a meta description length.
   */
  public function plainText(string $html, int $length = 160): string {
    // Keep words apart where block elements and line breaks end.
    $html = preg_replace('@<(br|/p|/div|/h[1-6]|/li)[^>]*>@i', ' ', $html);
    $text = Html::decodeEntities(strip_tags($html));
    $text = trim(preg_replace('/\s+/u', ' ', str_replace("\xC2\xA0", ' ', $text)));
    return Unicode::truncate($text, $length, TRUE, TRUE, 20);
  }

  /**
   * Returns the absolute canonical URL of an entity in its language.
   */
  public function url(ContentEntityInterface $entity): string {
    $generated = $entity->toUrl('canonical', ['absolute' => TRUE, 'language' => $entity->language()])->toString(TRUE);
    $this->cacheability->addCacheableDependency($generated);
    return $generated->getGeneratedUrl();
  }

  /**
   * Returns the site's configured settings.
   */
  public function settings(): ImmutableConfig {
    return $this->configFactory->get('ibase_seo.settings');
  }

  /**
   * Returns the site name.
   */
  public function siteName(): string {
    $site = $this->configFactory->get('system.site');
    $this->cacheability->addCacheableDependency($site);
    return (string) $site->get('name');
  }

  /**
   * Returns the scheme and host of the current site, without a slash.
   */
  public function baseUrl(): string {
    return rtrim(Url::fromUri('base:/', ['absolute' => TRUE])->toString(), '/');
  }

  /**
   * Builds the breadcrumb trail of the current page.
   */
  protected function breadcrumbList(string $page_url): ?array {
    $breadcrumb = $this->breadcrumb->build($this->routeMatch);
    $this->cacheability->addCacheableDependency($breadcrumb);
    $items = [];
    foreach ($breadcrumb->getLinks() as $link) {
      $url = $link->getUrl();
      $text = $link->getText();
      // Skip unlinked items and labels without words, like "+".
      if (is_array($text) || !preg_match('/\pL/u', (string) $text) || ($url->isRouted() && $url->getRouteName() === '<nolink>')) {
        continue;
      }
      $generated = $url->setAbsolute()->toString(TRUE);
      $this->cacheability->addCacheableDependency($generated);
      $items[] = [
        '@type' => 'ListItem',
        'position' => count($items) + 1,
        'name' => $this->plainText((string) $text, 110),
        // The current page is often shown as unlinked text.
        'item' => $generated->getGeneratedUrl() ?: $page_url,
      ];
    }
    if (count($items) < 2) {
      return NULL;
    }
    return [
      '@type' => 'BreadcrumbList',
      '@id' => $page_url . '#breadcrumb',
      'itemListElement' => $items,
    ];
  }

  /**
   * Builds references to other nodes as schema.org stubs.
   */
  protected function references(ContentEntityInterface $entity, string $field_name, string $type, int $limit = 0): array {
    if (!$this->has($entity, $field_name)) {
      return [];
    }
    $references = [];
    foreach ($entity->get($field_name)->referencedEntities() as $target) {
      if (!$target instanceof NodeInterface || !$target->isPublished()) {
        continue;
      }
      $target = $this->translation($target, $this->langcode());
      $this->cacheability->addCacheableDependency($target);
      $references[] = [
        '@type' => $type,
        'name' => $target->label(),
        'url' => $this->url($target),
      ];
      if ($limit && count($references) >= $limit) {
        break;
      }
    }
    return $references;
  }

  /**
   * Builds start and end dates from the date fields a node has.
   */
  protected function eventDates(NodeInterface $node): array {
    foreach (['field_dates', 'field_date'] as $field_name) {
      if (!$this->has($node, $field_name)) {
        continue;
      }
      $item = $node->get($field_name)->first();
      return array_filter([
        'startDate' => $this->isoDate($item->value ?? NULL),
        'endDate' => $this->isoDate($item->end_value ?? NULL),
      ]);
    }
    return [];
  }

  /**
   * Converts a stored date value to ISO 8601 in the site's time zone.
   */
  protected function isoDate(?string $value): ?string {
    if (!$value) {
      return NULL;
    }
    if (strlen($value) === 10) {
      // Date-only values have no time zone.
      return $value;
    }
    $date = new \DateTime($value, new \DateTimeZone(DateTimeItemInterface::STORAGE_TIMEZONE));
    $date->setTimezone(new \DateTimeZone(date_default_timezone_get()));
    return $date->format('c');
  }

  /**
   * Builds width, height and depth of an artwork.
   */
  protected function dimensions(NodeInterface $node): array {
    if (!$this->has($node, 'field_dimensions')) {
      return [];
    }
    $item = $node->get('field_dimensions')->first();
    $unit = $this->has($node, 'field_dimensions_unit') ? (string) $node->get('field_dimensions_unit')->value : 'cm';
    $unit_code = self::UNIT_CODES[strtolower($unit)] ?? NULL;
    $data = [];
    foreach (['height' => 'height', 'width' => 'width', 'length' => 'depth'] as $property => $schema_property) {
      $value = (float) ($item->{$property} ?? 0);
      if ($value > 0) {
        $data[$schema_property] = array_filter([
          '@type' => 'QuantitativeValue',
          'value' => $value,
          'unitCode' => $unit_code,
          'unitText' => $unit_code ? NULL : $unit,
        ]);
      }
    }
    return $data;
  }

  /**
   * Builds the venue as a schema.org Place, with the room a node names.
   */
  protected function place(?NodeInterface $node = NULL): array {
    $organization = $this->organization();
    $place = array_filter([
      '@type' => 'Place',
      'name' => $organization['name'] ?? NULL,
      'address' => $organization['address'] ?? NULL,
      'geo' => $organization['geo'] ?? NULL,
      'url' => $organization['url'] ?? NULL,
    ]);
    $room = $node && $this->has($node, 'field_location') ? $node->get('field_location')->entity : NULL;
    if ($room instanceof TermInterface) {
      $place['name'] = $place['name'] . ' – ' . $room->label();
      $place['containedInPlace'] = ['@id' => $this->baseUrl() . '/#organization'];
    }
    return $place;
  }

  /**
   * Builds people from the person terms (e.g. artist tags) a node references.
   */
  protected function personTerms(NodeInterface $node): array {
    $people = [];
    foreach ($node->getFieldDefinitions() as $field_name => $definition) {
      if ($definition->getType() !== 'entity_reference' || $definition->getSetting('target_type') !== 'taxonomy_term' || $node->get($field_name)->isEmpty()) {
        continue;
      }
      foreach ($node->get($field_name)->referencedEntities() as $term) {
        if ($term instanceof TermInterface && $this->isPersonTerm($term)) {
          $this->cacheability->addCacheableDependency($term);
          $people[] = ['@type' => 'Person', 'name' => $term->label(), 'url' => $this->url($term)];
        }
      }
    }
    return $people;
  }

  /**
   * Checks whether a term stands for a person, such as an artist tag.
   */
  public function isPersonTerm(TermInterface $term): bool {
    if (!in_array($term->bundle(), $this->settings()->get('person_vocabularies') ?: [], TRUE)) {
      return FALSE;
    }
    // Leave out tags that are not names, like "+" or "a.o.".
    $name = trim($term->label());
    return preg_match('/\pL.*\pL.*\pL/u', $name) && !preg_match('/^(a\.\s*o\.?|u\.\s*a\.?|and others|und andere)$/iu', $name);
  }

  /**
   * Builds a schema.org Person from a person term.
   */
  public function termPerson(TermInterface $term): array {
    return array_filter([
      '@type' => 'Person',
      'name' => $term->label(),
      'url' => $this->url($term),
      'description' => $this->termDescription($term),
      'jobTitle' => $this->contentLangcode($term->language()->getId()) === 'de' ? 'Künstler*in' : 'Artist',
      'affiliation' => ['@id' => $this->baseUrl() . '/#organization'],
    ]);
  }

  /**
   * Returns a meta description for a person term.
   */
  public function termDescription(TermInterface $term): string {
    $text = $this->plainText((string) $term->getDescription());
    if (mb_strlen($text) >= 50) {
      return $text;
    }
    $de = $this->contentLangcode($term->language()->getId()) === 'de';
    $org = $this->settings()->get('organization.name') ?: $this->siteName();
    $place = $this->settings()->get($de ? 'place_de' : 'place');
    $at = $org . ($place ? ' ' . $place : '');
    return $de
      ? $term->label() . " – Ausstellungen und Werke bei $at."
      : $term->label() . " – exhibitions and works at $at.";
  }

  /**
   * Builds a schema.org ItemList of the nodes a listing shows.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface[] $entities
   *   The listed entities, in order.
   */
  public function itemList(array $entities, string $name, int $offset = 0): array {
    $items = [];
    foreach (array_values($entities) as $delta => $entity) {
      $entity = $this->translation($entity, $this->langcode());
      $this->cacheability->addCacheableDependency($entity);
      $items[] = [
        '@type' => 'ListItem',
        'position' => $offset + $delta + 1,
        'url' => $this->url($entity),
        'name' => $entity->label(),
      ];
    }
    return [
      '@context' => 'https://schema.org',
      '@type' => 'ItemList',
      'name' => $name,
      'numberOfItems' => count($items),
      'itemListElement' => $items,
    ];
  }

  /**
   * Builds an image URL, using the wide image style when there is one.
   */
  protected function styledImage(FileInterface $file, int $width, int $height, string $alt): array {
    $this->cacheability->addCacheableDependency($file);
    $style = $this->entityTypeManager->getStorage('image_style')->load('wide');
    if ($style && $style->supportsUri($file->getFileUri())) {
      $this->cacheability->addCacheableDependency($style);
      $url = $style->buildUrl($file->getFileUri());
      $dimensions = ['width' => $width, 'height' => $height];
      $style->transformDimensions($dimensions, $file->getFileUri());
      $width = (int) $dimensions['width'];
      $height = (int) $dimensions['height'];
    }
    else {
      $url = $this->fileUrlGenerator->generateAbsoluteString($file->getFileUri());
    }
    return [
      'url' => $url,
      'width' => $width ?: NULL,
      'height' => $height ?: NULL,
      'alt' => $alt,
      'type' => $file->getMimeType(),
    ];
  }

  /**
   * Converts image data to a schema.org ImageObject.
   */
  protected function imageObject(array $image): array {
    return array_filter([
      '@type' => 'ImageObject',
      'url' => $image['url'],
      'contentUrl' => $image['url'],
      'width' => $image['width'],
      'height' => $image['height'],
      'caption' => $image['alt'] ?: NULL,
    ]);
  }

  /**
   * Picks the WebPage subtype that fits a node.
   */
  protected function pageType(NodeInterface $node): string {
    return match ($node->bundle()) {
      'artist' => 'ProfilePage',
      'artwork', 'exhibition', 'events', 'article' => 'ItemPage',
      'workgroup' => 'CollectionPage',
      default => 'WebPage',
    };
  }

  /**
   * Checks whether the node is the configured front page.
   */
  protected function isFrontPage(NodeInterface $node): bool {
    $front = (string) $this->configFactory->get('system.site')->get('page.front');
    return $front === '/node/' . $node->id();
  }

  /**
   * Returns the translation of an entity in a language, if there is one.
   */
  protected function translation(ContentEntityInterface $entity, string $langcode): ContentEntityInterface {
    return $entity->hasTranslation($langcode) ? $entity->getTranslation($langcode) : $entity;
  }

  /**
   * Returns the codes of the site's languages.
   */
  protected function languageCodes(): array|string {
    if ($langcode = $this->settings()->get('content_langcode')) {
      return $langcode;
    }
    $codes = array_keys($this->languageManager->getLanguages());
    return count($codes) === 1 ? reset($codes) : $codes;
  }

  /**
   * Returns the current content language code.
   */
  public function langcode(): string {
    return $this->contentLangcode($this->languageManager->getCurrentLanguage(LanguageInterface::TYPE_CONTENT)->getId());
  }

  /**
   * Returns the language content is really written in.
   *
   * A monolingual site can be set up in one language while its content is
   * written in another; the content_langcode setting names the latter.
   */
  public function contentLangcode(string $langcode): string {
    return $this->settings()->get('content_langcode') ?: $langcode;
  }

  /**
   * Checks that an entity has a non-empty field.
   */
  protected function has(ContentEntityInterface $entity, string $field_name): bool {
    return $entity->hasField($field_name) && !$entity->get($field_name)->isEmpty();
  }

}
