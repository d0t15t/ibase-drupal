<?php

namespace Drupal\ibase_api\Service;

use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Batch\BatchStorageInterface;
use Drupal\Core\Entity\EntityBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\Core\Utility\LinkGeneratorInterface;
use Drupal\file\FileRepositoryInterface;
use GuzzleHttp\ClientInterface;
use Psr\Log\LoggerInterface;

/**
 * Class IbaseApiService
 */
class IbaseApiService {

  protected LoggerInterface $logger;

  protected MessengerInterface $messenger;

  protected EntityTypeManagerInterface $etm;

  protected ClientInterface $httpClient;

  protected Json $json;

  protected FileRepositoryInterface $fileRepository;

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
    $this->batchStorage = $batch_storage;
    $this->urlGenerator = $url_generator;
    $this->linkGenerator = $link_generator;
  }

  const NODE_TYPE_CHANNEL = 'channel';

  const NODE_TYPE_CHANNEL_ITEM = 'episode';

  const MEDIA_TYPE_OEMBED_AUDIO = 'audio_oembed';

  const FIELD_LABEL_API_ENDPOINT = 'field_api_endpoint';

  const FIELD_LABEL_API_REMOTE_ITEM_KEY = 'field_remote_item_key';

  const FIELD_LABEL_MEDIA_OEMBED_REMOTE_URL = 'field_media_oembed_audio';

  const FIELD_LABEL_MEDIA_IMAGE = 'field_media_image';

  public function updateChannel(string $id) {
    try {
      if ($node = $this->etm->getStorage('node')->load($id)) {
        /** @var $node EntityBase */
        if ($node->bundle() === $this::NODE_TYPE_CHANNEL
          && $node->hasField($this::FIELD_LABEL_API_ENDPOINT)
          && !$node->get($this::FIELD_LABEL_API_ENDPOINT)->isEmpty()
        ) {
          $this->logger->info('Begin updating channel %id / %title', ['%id' => $id, '%title' => $node->label()]);
          $endpoint = $node->get($this::FIELD_LABEL_API_ENDPOINT)->getString();
          if ($done = $this->processChannelRequest($endpoint, $id)) {
            $this->logger->info('Successfully processed channel id %id with %count channel items.', ['%id' => $id, '%count' => sizeof($done)]);
          }
        } else {
          $this->logger->warning('There was an error with the node: %id.', ['%id' => $id]);
        }
      } else {
        $this->logger->warning('%id is not a valid entity ID', ['%id' => $id]);
      }
    }
    catch (\Exception $exception) {
      throw new \Error($exception->getMessage());
    }
  }

  protected function processChannelRequest(
    string $endpoint,
    string $channel_id,
    $op_count = 0,
    $channel_item_nodes = []
  ): ?array {
    $request = $this->httpClient->request('GET', $endpoint);
    if ($request->getStatusCode() === 200) {
      $this->logger->info('Successfully contacted: ' . $endpoint);

      $op_count++;
      $data = $request->getBody()->getContents();
      $json = $this->json->decode($data);
      $channel_items_data_rows = isset($json['data']) && is_array($json['data'])
        ? $json['data'] : [];

      if (empty($channel_items_data_rows)) {
        $this->logger->warning('No results were returned from this request: ' . $endpoint);
        return [];
      }

      $processed_channel_item_nodes = array_map(function ($channel_item) use ($channel_id) {
        return $this->processChannelItemData(['channel_id' => $channel_id, ...$channel_item]);
      }, $channel_items_data_rows);

      $channel_item_nodes = [...$channel_item_nodes, ...$processed_channel_item_nodes];

      if (isset($json['paging']['next']) && is_string($json['paging']['next'])) {
        $this->processChannelRequest($json['paging']['next'], $channel_id, $op_count, $channel_item_nodes);
      }
      else {
        $episode_ids = array_map(fn ($n) => $n->id(), $channel_item_nodes);
        $channel_node = $this->etm->getStorage('node')->load($channel_id);
        $channel_node->set('field_episodes', $episode_ids);
        $channel_node->save();
        return $episode_ids;
      }
    }
    else {
      $this->logger->warning('Channel API request failed: ' . $endpoint);
    }
    return [];
  }

  private function processChannelItemData(array $data): ?EntityInterface {
    if ($existing_channel_item = $this->getExistingChannelItem($data['key'] ?? '')) {
      //@Todo: response for existing nodes. Test for update time, etc.
    }
    // Create new item + related entities.
    else {
      // Terms.
      $data['terms'] = array_filter(array_map(function ($term_data) {
        return $this->getChannelItemTag($term_data)->id();
      }, $data['tags'] ?? []), fn($e) => $e);

      // Oembed Audio Media.
      $data['audio'] = $data['url'] ? $this->getChannelItemAudio($data['url'])->id() : NULL;

      // Image Media.
      $remote_image_path = $data['pictures']['1024wx1024h'] ?? NULL;
      $data['image'] = $remote_image_path
        ? $this->getChannelItemImage(
          $remote_image_path,
          $data['name'] ?? '',
          $data['slug'] ?? ''
        )->id()
        : NULL;

      $values = $this->getMappedChannelItemValues($data);

      $node_storage = $this->etm->getStorage('node');
      $channel_item = $node_storage->create($values);
      $channel_item->save();
    }
    return $channel_item ?? NULL;
  }

  private function getExistingChannelItem(string $key): ?EntityBase {
    $items = $this->etm->getStorage('node')
      ->loadByProperties([$this::FIELD_LABEL_API_REMOTE_ITEM_KEY => $key]);
    return sizeof($items) ? reset($items) : NULL;
  }

  private function getChannelItemTag(array $tag_data): EntityInterface {
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

  private function getChannelItemImage(string $url, $name = '', $slug = ''): ?EntityInterface {
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

  private function getMappedChannelItemValues(array $data) {
    return array_filter([
      'type' => $this::NODE_TYPE_CHANNEL_ITEM,
      'title' => $data['name'] ?? t('Unknown title'),
      'field_remote_item_created' => $data['created_time'] ?? NULL,
      'field_remote_item_updated' => $data['updated_time'] ?? NULL,
      'field_remote_item_slug' => $data['slug'] ?? NULL,
      'field_remote_item_key' => $data['key'] ?? NULL,
      'field_duration' => $data['audio_length'] ?? NULL,
      'field_channels' => [$data['channel_id']],
      'field_tags' => [$data['channel_id']],
      'field_audio' => [$data['audio']],
      'field_media_image' => [$data['image']],
      'field_json_data' => $this->json->encode($data),
      'uid' => 1,
    ], fn ($e) => $e);
  }

}
