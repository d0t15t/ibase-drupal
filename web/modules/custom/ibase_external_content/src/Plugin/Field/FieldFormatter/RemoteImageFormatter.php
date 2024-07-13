<?php

namespace Drupal\ibase_external_content\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\media_remote\Plugin\Field\FieldFormatter\MediaRemoteFormatterBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\views\Plugin\views\field\Boolean;
use \Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Site\Settings;

/**
 * Plugin implementation of the 'media_remote_brightcove' formatter.
 *
 * @FieldFormatter(
 *   id = "ibase_external_content_remote_image_formatter",
 *   label = @Translation("Remote Media - Ibase External Content"),
 *   field_types = {
 *     "string"
 *   }
 * )
 */
class RemoteImageFormatter extends MediaRemoteFormatterBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    $setting = ['active_entity_link' => FALSE];
    return $setting + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $elements['active_entity_link'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Link to entity'),
      '#default_value' => $this->getSetting('active_entity_link'),
    ];
    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    return [
      $this->t('Link to entity: @active_entity_link', ['@active_entity_link' => $this->getSetting('active_entity_link')]),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function getUrlRegexPattern() {
    return '/^https:\/\/thegallery\.art\/sites\/the\/files\/[0-9]{4}-[0-9]{2}\/[a-zA-Z0-9-_]+\.[a-zA-Z]{3,4}$/';
  }

  public static function getValidUrlExampleStrings(): array {
    return [
      'https://thegallery.art/sites/the/files/2024-03/DSC_7514.JPG'
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $elements = [];
    foreach ($items as $delta => $item) {
      /** @var FieldItemInterface $item */
      if ($item->isEmpty()) {
        continue;
      }
      $media = $item->getEntity();
      $t=1;
      $elements[$delta] = [
        '#theme' => 'ibase_external_content_remote_image_formatter',
        '#remote_image_url' => $item->value,
        '#link_url' => $media->toUrl()->toString(['absolute' => TRUE]),
        '#remote_image_alt' => $media->get('field_caption')->getString(),
        '#active_entity_link' => (bool) $this->getSetting('active_entity_link'),
      ];
    }
    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public static function deriveMediaDefaultNameFromUrl($url) {
    $pattern = static::getUrlRegexPattern();
    if (preg_match($pattern, $url)) {
      return t('THE Gallery @url', [
        '@url' => $url,
      ]);
    }
    return parent::deriveMediaDefaultNameFromUrl($url);
  }

}
