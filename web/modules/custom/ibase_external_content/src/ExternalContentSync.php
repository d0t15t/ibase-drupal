<?php

declare(strict_types=1);

namespace Drupal\ibase_external_content;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Psr\Http\Client\ClientInterface;
use Drupal\Core\Site\Settings;

/**
 * @todo Add class description.
 */
final class ExternalContentSync {

  /**
   * Constructs an ExternalContentSync object.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly ClientInterface $httpClient,
    private readonly Settings $settings,
  ) {}

  private function getRemoteUrl():string {
    return $this->settings->get('ibase_external_content_url_json');
  }

  private function getUuidUrl(string $prefix, string $uuid):string {
    return $this->getRemoteUrl() . "/$prefix/$uuid";
  }

  /**
   * Syncs remote content.
   */
  public function syncFromRemote(EntityInterface $node): void {
    $external_urls = $node->get('field_external_artworks')->getValue();
    $uuids = array_map(function ($value) {
      $arr = explode('/', $value['value']);
      $nid = end($arr);
      if (!is_numeric($nid)) {
        return NULL;
      }
      try {
        $url = $this->settings->get('ibase_external_content_autocomplete_url');
        $response = $this->httpClient->request('GET', $url . '?filter[nid]=' . $nid);
        $artworks = json_decode($response->getBody()->getContents(), TRUE);

        return $artworks["data"][0]["id"];
      } catch (\Exception $e) {
        \Drupal::logger('ibase_external_content')->error($e->getMessage());
        return NULL;
      }
    }, $external_urls);
    $media_ids = [];
    foreach ($uuids as $uuid) {
      $existing_media = $this->entityTypeManager->getStorage('media')->loadByProperties(['field_remote_uuid' => $uuid]);
      $response = $this->httpClient->request('GET', $this->getUuidUrl('node/artwork', $uuid));
      $data = json_decode($response->getBody()->getContents(), TRUE);
      if ($data) {
        $remote_media = $this->createRemoteMedia($data, $existing_media);
        $media_ids[] = $remote_media->id();
      }
    }
    $node->set('field_artworks', $media_ids);

  }

  private function createRemoteMedia($data, $existing_media): EntityInterface {
    $artwork_uuid = $data["data"]["id"];
    $image_uuid = $data["data"]["relationships"]["field_images"]["data"][0]["id"];
    $response = $this->httpClient->request('GET', $this->getUuidUrl('media/image', $image_uuid));
    $data = json_decode($response->getBody()->getContents(), TRUE);

    $image_media_link = $data["data"]["relationships"]["field_media_image"]["links"]["self"]["href"];
    $response = $this->httpClient->request('GET', $image_media_link);
    $data = json_decode($response->getBody()->getContents(), TRUE);

    $image_file_url = $data["links"]["self"]["href"];
    $response = $this->httpClient->request('GET', $image_file_url);
    $data = json_decode($response->getBody()->getContents(), TRUE);

    $alt = $data["data"]["meta"]["alt"];
//    $height = $data["data"]["meta"]["height"];
//    $width = $data["data"]["meta"]["width"];

    $file_url = $data["links"]["related"]["href"];
    $response = $this->httpClient->request('GET', $file_url);
    $data = json_decode($response->getBody()->getContents(), TRUE);

//    $uri = $data["data"]["attributes"]["uri"]["url"];
//    $url = $this->settings->get('ibase_external_content_url') . $uri;
    $image_style_large_url = $data["data"]["attributes"]["image_style_uri"]["large"];

    if (sizeof($existing_media) > 0) {
      $media = reset($existing_media);
      $media->set('field_media_media_remote', $image_style_large_url);
      $media->set('field_caption', $alt);
      $media->save();
      return $media;
    } else {
      $media = $this->entityTypeManager->getStorage('media')->create([
        'bundle' => 'remote_image',
        'name' => $alt,
        'field_media_media_remote' => $image_style_large_url,
        'field_caption' => $alt,
        'field_remote_uuid' => $artwork_uuid,
      ]);
    }
    try {
      $media->save();
    } catch (\Exception $e) {
      \Drupal::logger('ibase_external_content')->error($e->getMessage());
    }
    return $media;
  }

}
