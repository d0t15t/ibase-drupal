<?php

namespace Drupal\the_price\Commands;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;

/**
 * One-off migration of price_* fields on artworks into Price paragraphs.
 */
final class ThePriceCommands extends DrushCommands {

  public function __construct(
    protected readonly EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct();
  }

  /**
   * Migrate legacy price fields on artworks to paragraph-based price fields.
   *
   * Source (on node.artwork):
   *   - field_sale_price (price_price)
   *   - field_offset_cost (price_price)
   *   - field_insurance_value (price_price)
   *
   * Destination (on node.artwork):
   *   - field_price_sale (paragraph(price))
   *   - field_price_offset_cost (paragraph(price))
   *   - field_price_insurance_value (paragraph(price))
   */
  #[CLI\Command(name: 'the_price:migrate-artwork-prices', aliases: ['the-price:migrate-prices'])]
  public function migrateArtworkPrices(): void {
    $node_storage = $this->entityTypeManager->getStorage('node');

    // Load all artworks; filter in-code by field presence.
    $nids = $node_storage->getQuery()
      ->condition('type', 'artwork')
      ->accessCheck(FALSE)
      ->execute();

    if (!$nids) {
      $this->logger()->notice('No artwork nodes found.');
      return;
    }

    /** @var \Drupal\node\NodeInterface[] $nodes */
    $nodes = $node_storage->loadMultiple($nids);

    $mapping = [
      'field_sale_price'       => 'field_price_sale',
      'field_offset_cost'      => 'field_price_offset_cost',
      'field_insurance_value'  => 'field_price_insurance_value',
    ];

    $count_nodes = 0;
    $count_paragraphs = 0;

    foreach ($nodes as $node) {
      if (!$node instanceof NodeInterface) {
        continue;
      }

      $changed = FALSE;

      // Handle all translations; price fields are translatable.
      foreach ($node->getTranslationLanguages() as $langcode => $language) {
        $translation = $node->getTranslation($langcode);

        foreach ($mapping as $source_field => $target_field) {
          if (
            !$translation->hasField($source_field) ||
            !$translation->hasField($target_field)
          ) {
            continue;
          }

          $source = $translation->get($source_field);
          if ($source->isEmpty()) {
            continue;
          }

          // Don't overwrite existing paragraph references.
          $target = $translation->get($target_field);
          if (!$target->isEmpty()) {
            continue;
          }

          $item = $source->first();
          // price_price field item properties are typically "number" and "currency_code".
          $amount = $item->number ?? NULL;
          $currency_code = $item->currency_code ?? NULL;

          if ($amount === NULL || $currency_code === NULL) {
            continue;
          }

          // Map ISO code (e.g. "EUR") to paragraph list value (e.g. "eur").
          $currency_value = strtolower($currency_code);

          // Create Price paragraph.
          $paragraph = Paragraph::create([
            'type' => 'price',
            'field_currency' => [
              [
                'value' => $currency_value,
              ],
            ],
            'field_price' => [
              [
                'value' => $amount,
              ],
            ],
          ]);
          $paragraph->save();
          $count_paragraphs++;

          // Attach to the target entity_reference_revisions field.
          $translation->set($target_field, [
            [
              'target_id' => $paragraph->id(),
              'target_revision_id' => $paragraph->getRevisionId(),
            ],
          ]);

          $changed = TRUE;
        }
      }

      if ($changed) {
        $node->save();
        $count_nodes++;
      }
    }

    $this->logger()->notice('Migrated prices on @nodes artwork nodes, created @paras Price paragraphs.', [
      '@nodes' => $count_nodes,
      '@paras' => $count_paragraphs,
    ]);
  }

}
