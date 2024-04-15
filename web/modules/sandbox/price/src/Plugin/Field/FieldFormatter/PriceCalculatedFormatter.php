<?php

namespace Drupal\price\Plugin\Field\FieldFormatter;

use CommerceGuys\Intl\Formatter\CurrencyFormatterInterface;
use Drupal\price\Context;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\price\Resolver\ChainPriceResolverInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'price_calculated' formatter.
 *
 * @FieldFormatter(
 *   id = "price_calculated",
 *   label = @Translation("Price Calculated"),
 *   field_types = {
 *     "price_price"
 *   }
 * )
 */
class PriceCalculatedFormatter extends PriceDefaultFormatter implements ContainerFactoryPluginInterface {

  /**
   * The chain price resolver.
   *
   * @var \Drupal\price\Resolver\ChainPriceResolverInterface
   */
  protected $chainPriceResolver;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * Constructs a new PriceCalculatedFormatter object.
   *
   * @param string $plugin_id
   *   The plugin_id for the formatter.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the formatter is associated.
   * @param array $settings
   *   The formatter settings.
   * @param string $label
   *   The formatter label display setting.
   * @param string $view_mode
   *   The view mode.
   * @param array $third_party_settings
   *   Any third party settings settings.
   * @param \CommerceGuys\Intl\Formatter\CurrencyFormatterInterface $currency_formatter
   *   The currency formatter.
   * @param \Drupal\price\Resolver\ChainPriceResolverInterface $chain_price_resolver
   *   The chain price resolver.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, $label, $view_mode, array $third_party_settings, CurrencyFormatterInterface $currency_formatter, ChainPriceResolverInterface $chain_price_resolver, AccountInterface $current_user) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings, $currency_formatter);

    $this->chainPriceResolver = $chain_price_resolver;
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('price.currency_formatter'),
      $container->get('price.chain_price_resolver'),
      $container->get('current_user')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];
    if (!$items->isEmpty()) {
      $context = new Context($this->currentUser, NULL, [
        'field_name' => $items->getName(),
      ]);
      /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
      $entity = $items->getEntity();
      $resolved_price = $this->chainPriceResolver->resolve($entity, 1, $context);
      $number = $resolved_price->getNumber();
      $currency_code = $resolved_price->getCurrencyCode();
      $options = $this->getFormattingOptions();

      $elements[0] = [
        '#theme' => 'price_calculated',
        '#calculated_price' => $this->currencyFormatter->format($number, $currency_code, $options),
        '#entity' => $entity,
        '#cache' => [
          'tags' => $entity->getCacheTags(),
          'contexts' => Cache::mergeContexts($entity->getCacheContexts(), [
            'languages:' . LanguageInterface::TYPE_INTERFACE,
            'country',
          ]),
        ],
      ];
    }

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition) {
    $entity_type = \Drupal::entityTypeManager()->getDefinition($field_definition->getTargetEntityTypeId());
    return $entity_type->entityClassImplements(ContentEntityInterface::class);
  }

}