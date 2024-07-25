<?php

namespace Drupal\ibase_external_content;

use Drupal\Component\Uuid\Uuid;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\file\Entity\File;
use GuzzleHttp\Exception\RequestException;
use Psr\Http\Client\ClientInterface;
use Drupal\Core\Site\Settings;

class RemoteApiClient {

  private $remote_image_style = 'wide';

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly ClientInterface $httpClient,
    private readonly Settings $settings,
  ) {}

  private function getRemoteUrl():string {
    return $this->settings->get('ibase_external_content_url_json');
  }

  private function getApiEndpoint(array $el):string {
    $type = $el['type'] ?? NULL;
    $type = str_replace('--', '/', $type);
    $uuid = $el['id'] ?? NULL;
    return $this->getRemoteUrl() . "/$type/$uuid";
  }

  /**
   * Syncs remote content.
   */
  public function syncFromRemote(EntityInterface $node) {
    $external_urls = $node->get('field_external_artworks')->getValue();
    $artworks = [];
    foreach ($external_urls as $external_url) {
      $uuid = $external_url['value'];
      if (!$this->validateUuid($uuid)) {
        throw new \Exception('Invalid UUID');
      }
      try {
        $api_endpoint = $this->settings->get('ibase_external_content_api_endpoint');
        $response = $this->httpClient->request('GET', $api_endpoint . '?filter[id]=' . $uuid);
        $body = $response->getBody()->getContents();
        $data = json_decode($body, TRUE);
      } catch (\Exception $e) {
        \Drupal::logger('ibase_external_content')->error($e->getMessage());
        continue;
      }
      if ($data['data'][0]) {
        $artwork = $this->createArtwork($data['data'][0]);
        $artworks[] = $artwork->id();
      }
    }
    $node->set('field_artworks', $artworks);
    $node->save();
  }

  private function createArtwork($data, $existing_media = []): EntityInterface {
    $get_body = function ($attributes, $relationships) {
      return implode("\r\n", array_filter([
        $attributes["field_year"],
        implode(', ', array_map(function ($el) {
          $response = $this->httpClient->request('GET', $this->getApiEndpoint($el));
          $data = json_decode($response->getBody()->getContents(), TRUE);
          return $data["data"]["attributes"]["name"] ?? NULL;
        }, $relationships["field_medium"]["data"])),
        t("%width, %height, %length (w, h, l)", [
          "%width" => $attributes["field_dimensions"]["width"],
          "%height" => $attributes["field_dimensions"]["height"],
          "%length" => $attributes["field_dimensions"]["length"],
        ]),
        $attributes["body"],
      ], fn($val) => $val));
    };

    $artwork = $this->entityTypeManager->getStorage('node')->create([
      'type' => 'artwork',
      'title'       => $data['attributes']['title'] ?? 'Untitled',
      'field_images' => array_map(function ($el) {
        $response = $this->httpClient->request('GET', $this->getApiEndpoint($el));
        $data = json_decode($response->getBody()->getContents(), TRUE);
        $media_name = $data["data"]["attributes"]["name"] ?? t('Untitled');
        $media = $this->entityTypeManager->getStorage('media')->create([
          'bundle' => 'image',
          'name' => $media_name,
          'field_media_image' => array_map(function ($el) use ($media_name) {
            $response = $this->httpClient->request('GET', $this->getApiEndpoint($el));
            $data = json_decode($response->getBody()->getContents(), TRUE);
            $remote_url = $data["data"]["attributes"]["image_style_uri"][$this->remote_image_style];

            $get_file_path = fn ($file_name) => "public://styles/{$this->remote_image_style}/{$file_name}";

            try { // fetch the remote image.
              $file_name = explode('?', pathinfo($remote_url)['basename'] ?? '')[0]  ?? t('Untitled remote');
              $response = \Drupal::httpClient()->get($remote_url, ['sink' => $get_file_path($file_name)]);
            } catch (RequestException $e) {
              \Drupal::logger('llm')->error($e->getMessage());
              return NULL;
            }
            if ($response->getStatusCode() !== 200) {
              \Drupal::logger('custom_module')->error('Failed to download image.');
              return NULL;
            }
            $file = File::create([
              'uri' => $file_name ? $get_file_path($file_name) : 'public://untitled_remote_image.jpg',
            ]);
            $file->setPermanent();
            $file->save();
            return [
              'target_id' => $file->id(),
              'alt' => $media_name,
              'title' => $media_name,
            ];
            // Wrap in array on purpose in order to use array_map.
          }, [$data["data"]["relationships"]["field_media_image"]["data"]])

        ]);
        $media->save();
        return [
          'target_id' => $media->id(),
        ];
      }, $data["relationships"]["field_images"]["data"] ?? []),
      'field_uuid' => $data['id'],
      'body' => [
        'format' => 'basic',
        'value' => $get_body($data['attributes'], $data['relationships']),
      ],
    ]);
    $artwork->save();
    return $artwork;
  }

  public function getRemoteArtworkDetails($uuid) {
    if (!$this->validateUuid($uuid)) {
      throw new \Exception('Invalid UUID');
    }
    try {
      $api_endpoint = $this->settings->get('ibase_external_content_api_endpoint');
      $response = $this->httpClient->request('GET', $api_endpoint . '?filter[id]=' . $uuid);
      $body = $response->getBody()->getContents();
      $data = json_decode($body, TRUE);
      $artwork_data = $data['data'][0];
      $artists_data = array_map(function ($el) {
        $response = NULL;
        try {
          $response = $this->httpClient->request('GET', $this->getApiEndpoint($el));
        } catch (RequestException $e) {
          \Drupal::logger('ibase_external_content')->error($e->getMessage());
        }
        if (!$response) return '';
        if ($response->getStatusCode() !== 200) return '';
        return json_decode($response->getBody()->getContents(), TRUE)['data'];
      }, $artwork_data["relationships"]["field_artists"]["data"]);
      $artists = $artists_data[0]["attributes"]["title"] ?? '';
      return "{$artwork_data["attributes"]["title"]} / {$artists}";
    } catch (\Exception $e) {
      \Drupal::logger('ibase_external_content')->error($e->getMessage());
      return NULL;
    }
  }

  public function validateUuid($uuid): bool {
    return Uuid::isValid($uuid);
  }

}
