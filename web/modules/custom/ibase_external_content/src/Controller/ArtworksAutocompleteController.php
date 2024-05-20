<?php

declare(strict_types=1);

namespace Drupal\ibase_external_content\Controller;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Site\Settings;
use Psr\Http\Client\ClientInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Returns responses for Ibase External Content routes.
 */
final class ArtworksAutocompleteController extends ControllerBase implements ContainerInjectionInterface {

  /**
   * @var ClientInterface
   */
  protected $httpClient;

  /**
   * @var EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * @var Settings
   */
  protected $settings;

  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->httpClient = $container->get('http_client');
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->settings = $container->get('settings');
    return $instance;
  }

  /**
   * Builds the response.
   */
  public function __invoke(): array {

    $build['content'] = [
      '#type' => 'item',
      '#markup' => $this->t('It works!'),
    ];

    return $build;
  }

  public function handleAutocomplete(Request $request) {
    $results = [];
    $input = $request->query->get('q');
    if (!$input) {
      return new JsonResponse($results);
    }
    $input = Xss::filter($input);
    $url = $this->settings->get('ibase_external_content_autocomplete_url');
    $response = $this->httpClient->get($url . '?filter[title][operator]=CONTAINS&filter[title][value]=' . $input);
    $artworks = json_decode($response->getBody()->getContents(), TRUE);
    if (empty($artworks['data'])) {
      return new JsonResponse([]);
    }
    foreach ($artworks['data'] as $artwork) {
      $artists = [];
      foreach ($artwork["relationships"]["field_artists"]['data'] as $artist) {
        $url = 'https://thegallery.art/jsonapi/node/artist/' . $artist['id'];
        $response = $this->httpClient->get($url);
        $artist = json_decode($response->getBody()->getContents(), TRUE);
        $artists[] = $artist['data']['attributes']['title'];
      }
      $label = new FormattableMarkup('@label / @author', ['@label' => $artwork['attributes']['title'], '@author' => implode(',', $artists)]);
      $results[] = [
        'value' => $artwork['id'],
        'label' => $label
      ];
    }
    return new JsonResponse($results);
  }

}
