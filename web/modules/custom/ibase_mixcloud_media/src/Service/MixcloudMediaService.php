<?php

namespace Drupal\ibase_mixcloud_media\Service;

use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Batch\BatchStorageInterface;
use Drupal\Core\Entity\EntityBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\Core\TempStore\SharedTempStore;
use Drupal\Core\TempStore\SharedTempStoreFactory;
use Drupal\Core\Utility\LinkGeneratorInterface;
use Drupal\file\FileRepositoryInterface;
use Drupal\ibase_media_embed_mixcloud\Commands\IbaseMediaEmbedMixcloudCommands;
use GuzzleHttp\ClientInterface;
use Psr\Log\LoggerInterface;

class MixcloudMediaService {

  public LoggerInterface $logger;

  protected MessengerInterface $messenger;

  protected EntityTypeManagerInterface $etm;

  public ClientInterface $httpClient;

  public Json $json;

  protected FileRepositoryInterface $fileRepository;

  protected SharedTempStoreFactory $tempStore;

  protected BatchStorageInterface $batchStorage;

  protected UrlGeneratorInterface $urlGenerator;

  protected LinkGeneratorInterface $linkGenerator;

  public function __construct(
    LoggerInterface $logger,
    MessengerInterface $messenger,
    EntityTypeManagerInterface $etm,
    ClientInterface $httpClient,
    Json $json,
    FileRepositoryInterface $fileRepository,
    SharedTempStoreFactory $tempStore,
    BatchStorageInterface $batch_storage,
    UrlGeneratorInterface $url_generator,
    LinkGeneratorInterface $link_generator
  ) {
    $this->logger = $logger;
    $this->messenger = $messenger;
    $this->etm = $etm;
    $this->httpClient = $httpClient;
    $this->json = $json;
    $this->fileRepository = $fileRepository;
    $this->tempStore = $tempStore;
    $this->batchStorage = $batch_storage;
    $this->urlGenerator = $url_generator;
    $this->linkGenerator = $link_generator;
  }

  const NODE_TYPE_CHANNEL = 'channel';

  const MEDIA_TYPE_MIXCLOUD = 'mixcloud';

  const MEDIA_TYPE_OEMBED_AUDIO = 'audio_oembed';

  const FIELD_LABEL_API_ENDPOINT = 'field_api_endpoint';

  const FIELD_LABEL_EPISODE_ENDPOINT = 'field_remote_url';

  const FIELD_LABEL_MEDIA_IMAGE = 'field_media_image';

  const MODULE_NAME = 'ibase_media_embed_mixcloud';

  public function getValidChannel(string $nid) {
    try {
      if ($node = $this->etm->getStorage('node')->load($nid)) {
        /** @var $node EntityBase */
        return ($node->bundle() === $this::NODE_TYPE_CHANNEL
          && $node->hasField($this::FIELD_LABEL_API_ENDPOINT)
          && !$node->get($this::FIELD_LABEL_API_ENDPOINT)->isEmpty()
        )
          ? $node : NULL;
      } else $this->logger->warning('Invalid or incomplete channel node.');
    }
    catch (\Exception $e) {
      $this->logger->error($e->getMessage());
    }
  }

  public function getChannelEndpoint(EntityBase $channel) {
    return $channel->get($this::FIELD_LABEL_API_ENDPOINT)->getString();
  }

  protected function getTempstore(): SharedTempStore {
    return $this->tempStore->get($this::MODULE_NAME);
  }

  public function getTempstoreData($key) {
    return $this->getTempstore()->get($key);
  }

  public function saveChannelPagedData(
   string $endpoint,
   $paged_data = [],
   $items_count = 0,
   $store_label = NULL,
   $done = FALSE
  ) {
    try {
      $store_label = $store_label ?? 'paged_data:' . time();
//      if ($done) return $store_label; // ???
      $request = $this->httpClient->request('GET', $endpoint);
      if ($request->getStatusCode() === 200) {
        $this->logger->info('Successfully contacted: ' . $endpoint);
        $data = $request->getBody()->getContents();
        $json = $this->json->decode($data);
        if (isset($json['data']) && is_array($json['data'])) {
          $paged_data[$endpoint] = $json['data'];
          $items_count += sizeof($json['data']);
        }
        if (isset($json['paging']['next']) && is_string($json['paging']['next'])) {
          $this->saveChannelPagedData($json['paging']['next'], $paged_data, $items_count, $store_label);
        }
        else {
          $done = TRUE;
        }
      }
      if ($done) {
        $this->getTempstore()->set($store_label, [
          'paged_data' => $paged_data,
          'items_count' => $items_count,
        ]);
      }
      return $store_label;
    } catch (\Exception $e) {
      throw new \Exception($e->getMessage());
    }
  }

  public function getChannelEpisodesEndpoints(array $channel_data) {
    return array_reduce($channel_data, function ($agg, $page_data) {
      $endpoints = array_map(fn ($d) => $d['url'] ?? NULL, $page_data);
      return array_merge($agg, $endpoints);
    }, []);
  }

  public function modifyEpisodeEndpoint(string $url): string {
    return str_replace('www.', 'api.', $url);
  }

  static public function batchProcessEpisodeEndpoint(string $endpoint, $batchId, $channel_id, &$context) {
    $mixcloud = \Drupal::service('ibase_mixcloud_media.service');
    $response = $mixcloud->httpClient->request('GET', $endpoint);
    $body = $response->getBody()->getContents();
    $json = $mixcloud->json->decode($body);
    $json['endpoint'] = $endpoint;
    $json['channel_id'] = $channel_id;
    $media = $mixcloud->processEpisodeData($json);

    $name = $json['name'] ?? t('Unknown title');
    if ($media) {
      $context['results'][] = ['title' => $media->label(), 'id' => $media->id()];
      // Optional message displayed under the progressbar.
      $context['message'] = t('Completed episode batch @id: @details', [
        '@id' => $batchId,
        '@details' => $name,
      ]);
    }
  }

  static public function batchProcessEpisodesFinished(bool $success, array $results, array $operations) {
    $messenger = \Drupal::messenger();
    if ($success) {
      // Here we could do something meaningful with the results.
      // We just display the number of nodes we processed...
      $messenger->addMessage(t('@count results processed.', [
        '@count' => count($results),
      ]));
    }
    else {
      // An error occurred.
      // $operations contains the operations that remained unprocessed.
      $error_operation = reset($operations);
      $messenger->addError(t('An error occurred while processing @operation with arguments : @args', [
        '@operation' => $error_operation[0],
        '@args' => print_r($error_operation[0], TRUE),
      ]));
    }
  }

  public function processEpisodeData(array $data): ?EntityInterface {
    if ($existing_channel_item = $this->getExistingEpisode($data['endpoint'] ?? '')) {
      //@Todo: response for existing nodes. Test for update time, etc.
      return $existing_channel_item;
    }
    // Create new item + related entities.
    else {
      // Terms.
      $data['terms'] = array_filter(array_map(function ($term_data) {
        return $this->getEpisodeTag($term_data)->id();
      }, $data['tags'] ?? []), fn($e) => $e);

      // Image Media.
      $remote_image_path = $data['pictures']['extra_large'] ?? NULL;
      $data['image'] = $remote_image_path
        ? $this->getEpisodeImage(
          $remote_image_path,
          $data['name'] ?? '',
          $data['slug'] ?? ''
        )->id()
        : NULL;

      // Oembed data.
      $data['embed_code'] = $this->getEpisodeEmbedCode($data['url']);

      $values = $this->getMappedChannelItemValues($data);

      $media_storage = $this->etm->getStorage('media');
      $episode = $media_storage->create($values);
      $episode->save();
      return $episode;
    }
  }

  private function getExistingEpisode(string $url): ?EntityBase {
    $items = $this->etm->getStorage('media')
      ->loadByProperties([$this::FIELD_LABEL_EPISODE_ENDPOINT => $url]);
    return sizeof($items) ? reset($items) : NULL;
  }

  private function getEpisodeTag(array $tag_data): EntityInterface {
    try {
      $taxonomy_storage = $this->etm->getStorage('taxonomy_term');
      $items = $taxonomy_storage->loadByProperties(['name' => $tag_data['name'] ?? '']);
      if (empty($items)) {
        $term_values = array_filter([
          'name' => $tag_data['name'] ?? NULL,
          'vid' => 'tags',
          'field_link' => isset($tag_data['url']) && UrlHelper::isValid($tag_data['url'])
            ? ['uri' =>  $tag_data['url']] : NULL,
          'uid' => 1,
        ], fn ($e) => $e);
        $term = $taxonomy_storage->create($term_values);
        $term->save();
        return $term;
      }
      else
        return reset($items);
    }
    catch (\Exception $exception) {
      throw new \Exception($exception->getMessage());
    }
  }

  private function getChannelItemAudio(string $url): EntityInterface {
    try {
      $media_storage = $this->etm->getStorage('media');
      $items = $media_storage->loadByProperties([$this::FIELD_LABEL_MEDIA_OEMBED_REMOTE_URL => $url]);
      if (empty($items)) {
        $values = [
          $this::FIELD_LABEL_MEDIA_OEMBED_REMOTE_URL => $url,
          'bundle' => $this::MEDIA_TYPE_OEMBED_AUDIO,
          'uid' => 1,
        ];
        $media = $media_storage->create($values);
        $media->save();
        return $media;
      }
      else
        return reset($items);
    }
    catch (\Exception $exception) {
      throw new \Exception($exception->getMessage());
    }
  }

  private function getEpisodeImage(string $url, $name = '', $slug = ''): ?EntityInterface {
    try {
      $media_storage = $this->etm->getStorage('media');
      $items = $media_storage->loadByProperties(['field_remote_url' => $url]);
      if (empty($items)) {
        $image_data = $this->httpClient->request('GET', $url)->getBody()->getContents();
        $file_info = new \finfo(FILEINFO_MIME_TYPE);
        $mime_type_data = explode('/', $file_info->buffer($image_data));
        if ($mime_type_data[0] ?? NULL === 'image') {
          $path_to_file = sprintf('public://%s.%s', $slug, $mime_type_data[1] ?? 'png');
          $image = $this->fileRepository->writeData($image_data, $path_to_file, FileSystemInterface::EXISTS_REPLACE);
          $values = [
            $this::FIELD_LABEL_MEDIA_IMAGE => $url,
            'name' => $slug,
            'bundle' => 'image',
            'uid' => 1,
            'field_remote_url' => $url,
            'field_media_image' => [
              'target_id' => $image->id(),
              'alt' => $name,
              'title' => $name,
            ],
          ];
          $media = $media_storage->create($values);
          $media->save();
          return $media;
        }
      }
      else
        return reset($items);
    }
    catch (\Exception $exception) {
      throw new \Exception($exception->getMessage());
    }
  }

  private function getEpisodeEmbedCode(string $url): string {
    $endpoint = 'https://www.mixcloud.com/oembed/?url=' . $url;
    $response = $this->httpClient->request('GET', $endpoint);
    $body = $response->getBody()->getContents();
    $json = $this->json->decode($body);
    $embed = $json['embed'] ?? '';
    $mod1 = str_replace('height="120"', 'height="600"', $embed);
    $mod2 = str_replace('&amp;hide_cover=1', '', $mod1);
    return $mod2;
  }

  private function getMappedChannelItemValues(array $data) {
    return array_filter([
      'bundle' => $this::MEDIA_TYPE_MIXCLOUD,
      'name' => $data['name'] ?? t('Unknown title'),
      $this::FIELD_LABEL_EPISODE_ENDPOINT => $data['endpoint'],
      'field_link' => $data['url'],
      'field_remote_item_created' => $data['created_time'] ?? NULL,
      'field_remote_item_updated' => $data['updated_time'] ?? NULL,
      'field_slug' => $data['slug'] ?? NULL,
      'field_key' => $data['key'] ?? NULL,
      'field_duration' => $data['audio_length'],
      'field_favorite_count' => $data['favorite_count'],
      'field_comments_count' => $data['comment_count'],
      'field_play_count' => $data['play_count'],
      'field_listener_count' => $data['listener_count'],
      'field_repost_count' => $data['repost_count'],
      'field_channels' => [$data['channel_id']],
      'field_tags' => $data['terms'],
      'field_image_media' => [$data['image']],
      'field_json_data' => $this->json->encode($data),
      'field_embed_code' => $data['embed_code'],
      'field_color' => $data['picture_primary_color'],
      'field_description' => $data['description'],
      'uid' => 1,
    ], fn ($e) => $e);
  }


}
