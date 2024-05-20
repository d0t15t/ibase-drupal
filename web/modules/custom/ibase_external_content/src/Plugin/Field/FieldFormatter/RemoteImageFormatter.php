<?php

namespace Drupal\ibase_external_content\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\media_remote\Plugin\Field\FieldFormatter\MediaRemoteFormatterBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
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
//class RemoteImageFormatter extends MediaRemoteFormatterBase implements ContainerFactoryPluginInterface {

//  protected $settings;
//
//  /**
//   * @param array $configuration
//   * @param string $plugin_id
//   * @param mixed $plugin_definition
//   * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
//   */
//  public function __construct(array $configuration, $plugin_id, $plugin_definition, Settings $settings) {
//    parent::__construct($configuration, $plugin_id, $plugin_definition);
//
//    $this->settings = $settings;
//  }
//
//  /**
//   * {@inheritdoc}
//   */
//  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
//    return new static(
//      $configuration,
//      $plugin_id,
//      $plugin_definition,
//      $container->get('settings')
//    );
//  }

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
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];
    foreach ($items as $delta => $item) {
      /** @var FieldItemInterface $item */
      if ($item->isEmpty()) {
        continue;
      }
      $elements[$delta] = [
        '#theme' => 'ibase_external_content_remote_image_formatter',
        '#url' => $item->value,
      ];
    }
//    $caption = $item->properties["value"]->parent->parent->parent->entity->values["field_caption"]
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
