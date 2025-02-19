<?php

declare(strict_types=1);

namespace Drupal\ak_migrate\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;
use Drupal\node\NodeInterface;
use Psr\Log\LoggerInterface;

/**
 * @todo Add class description.
 */
final class ImportArticlesToExhibitions {

//  public function __construct(
//    private readonly EntityTypeManagerInterface $entityTypeManager,
//    private readonly LoggerChannelFactoryInterface $loggerFactory,
//  ) {}

  private EntityTypeManagerInterface $entityTypeManager;

  private LoggerInterface $logger;

  private $source_articles = [];

  private $existing_exhibitions = [];

  public function __construct(
    LoggerInterface $logger,
    EntityTypeManagerInterface $entityTypeManager,
  ) {
    $this->logger = $logger;
    $this->entityTypeManager = $entityTypeManager;
  }

  public function getSourceArticles(): array {
    return $this->entityTypeManager->getStorage('node')->loadByProperties(['type' => 'article']);
  }

  private function getExistingExhibitions() {
    if (empty($this->existing_exhibitions)) {
      $this->existing_exhibitions = $this->entityTypeManager->getStorage('node')->loadByProperties(['type' => 'exhibition']);
    }
    return $this->existing_exhibitions;
  }

  public function getPreviouslySyncedNode(NodeInterface $article):?NodeInterface {
    foreach ($this->getExistingExhibitions() as $exhibition) {
      if ($exhibition->get('field_import_reference')->getString() == $article->id()) {
        return $exhibition;
      }
    }
    return NULL;
  }

  public function creUpdateExhibition(NodeInterface $node): NodeInterface {
    $exhibition = $this->getPreviouslySyncedNode($node);
    if (!$exhibition) {
      $exhibition = $this->entityTypeManager->getStorage('node')->create([
        'type' => 'exhibition',
        'author' => \Drupal::currentUser()->id(),
        'status' => 1,
        'promote' => 0,
      ]);
    }
    $exhibition->set('title', $node->getTitle());
    $exhibition->set('field_title', $node->getTitle());
    $exhibition->set('field_import_reference', ['target_id' => $node->id()]);
    $dates = $node->get('field_dates')->getValue();
    if (sizeof($dates)) {
      $exhibition->set('field_dates', $node->get('field_dates')->getValue());
    } else {
      $created = (int) $node->get('created')->getString();
      $date = new \DateTime();
      $date->setTimestamp($created);
//      $date->modify('+7 days');
      $daterange = [
        'value' => $date->format('Y-m-d'),
        'end_value' => $date->modify('+7 days')->format('Y-m-d'),
      ];
      $exhibition->set('field_dates', $daterange);
    }

//    $exhibition->set('promote', 0);
//    $exhibition->set('field_d7_import', 1);
//    $exhibition->set('field_upcoming', 0);
    if (!$node->get('body')->isEmpty()) {
      $body = $node->get('body')->getValue()[0];
      $exhibition->set('body', [
        'value' => $body['value'],
        'format' => $body['format'],
      ]);
    }

    #Images.
    if (!$node->get('field_image')->isEmpty()) {
      # Add media from article.
      $article_image_files = $node->get('field_image')->referencedEntities();
      $t=1;
      $images = [];
      foreach ($article_image_files as $i => $image_file) {
        $media = $this->getExistingMedia($image_file);
        if (!$media) {
          $media = Media::create([
            'bundle' => 'image',
            'uid' => \Drupal::currentUser()->id(),
            'status' => 1,
          ]);
        }
        $media->set('field_media_image', [
            'target_id' => $image_file->id(),
            'alt' => $node->getTitle(),
          ]);
        $media->save();
        if ($i == 0) {
          $exhibition->set('field_media_image', ['target_id' => $media->id()]);
        } else {
          $images[] = ['target_id' => $media->id()];
        }
      }
      $exhibition->set('field_media_images', $images);
    }

    #Tags.
    if (!$node->get('field_tags')->isEmpty()) {
      $exhibition->set('field_tags', $node->get('field_tags')->getValue());
    }
    $exhibition->save();
    return $exhibition;
  }

  private function getExistingMedia(File $file):?Media {
    $existing = $this->entityTypeManager->getStorage('media')->loadByProperties(['field_media_image.target_id' => $file->id()]);
    return $existing ? reset($existing) : NULL;
  }

}
