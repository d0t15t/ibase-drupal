#!/usr/bin/env bash
set -euo pipefail

# Test script for the_price:migrate-artwork-prices
# - Runs the migration
# - Then verifies that, for each artwork node and translation, the new paragraph
#   price fields match the legacy price_* fields.

cd "$(dirname "$0")/.."

echo "Running price migration for 'the' site..."
echo "Command: ddev drush the_price:migrate-artwork-prices -l the"
echo

ddev drush the_price:migrate-artwork-prices -l the

echo
echo "Checking migrated prices against legacy fields..."

# Use Drush php:eval to compare old vs new values.
ddev drush php:eval -l the "$(cat << 'PHP'
use Drupal\node\NodeInterface;
use Drupal\paragraphs\Entity\Paragraph;

/** @var \Drupal\Core\Entity\EntityTypeManagerInterface $etm */
$etm = \Drupal::service('entity_type.manager');
$storage = $etm->getStorage('node');

$nids = $storage->getQuery()
  ->condition('type', 'artwork')
  ->accessCheck(FALSE)
  ->execute();

if (!$nids) {
  echo "No artwork nodes found.\n";
  return;
}

$nodes = $storage->loadMultiple($nids);

$mapping = [
  'field_sale_price'      => 'field_price_sale',
  'field_offset_cost'     => 'field_price_offset_cost',
  'field_insurance_value' => 'field_price_insurance_value',
];

// Normalize a numeric string to a canonical dot-decimal form, handling
// comma/thousand separators where possible.
$normalizeAmount = static function ($value) {
  if ($value === NULL || $value === '') {
    return NULL;
  }

  // Cast to string so we can safely run regex/str_replace.
  $str = (string) $value;

  // If clearly already a simple dot-decimal or integer, keep as-is.
  if (preg_match('/^[-+]?\d+(?:\.\d+)?$/', $str)) {
    return $str;
  }

  // Common European-style: "1.234,56" -> remove thousand dot, use comma as decimal.
  if (preg_match('/^[-+]?\d{1,3}(?:\.\d{3})*,\d+$/', $str)) {
    $str = str_replace('.', '', $str);   // remove thousand separators
    $str = str_replace(',', '.', $str);  // convert decimal comma to dot
    return $str;
  }

  // Fallbacks:
  // - If there is exactly one comma and no dot, treat comma as decimal.
  if (strpos($str, ',') !== FALSE && strpos($str, '.') === FALSE) {
    $str = str_replace(',', '.', $str);
    return $str;
  }

  // - If there are both comma and dot, but pattern is ambiguous, just strip
  //   thousand-style separators (comma) and keep dot as decimal.
  if (strpos($str, ',') !== FALSE && strpos($str, '.') !== FALSE) {
    $str = str_replace(',', '', $str);
    return $str;
  }

  // Last resort: return original string.
  return $str;
};

$total_checked = 0;
$total_ok = 0;
$total_missing_target = 0;
$total_mismatch = 0;

foreach ($nodes as $node) {
  if (!$node instanceof NodeInterface) {
    continue;
  }

  foreach ($node->getTranslationLanguages() as $langcode => $language) {
    $translation = $node->getTranslation($langcode);

    foreach ($mapping as $source_field => $target_field) {
      if (!$translation->hasField($source_field) || !$translation->hasField($target_field)) {
        continue;
      }

      $source = $translation->get($source_field);
      if ($source->isEmpty()) {
        continue;
      }

      $total_checked++;

      $item = $source->first();
      $raw_amount = $item->number ?? NULL;
      $currency_code = $item->currency_code ?? NULL;

      $amount = $normalizeAmount($raw_amount);

      if ($amount === NULL || $currency_code === NULL) {
        echo sprintf(
          "[WARN] Node %d (%s), lang %s, %s: legacy field has incomplete data (amount/currency).\n",
          $node->id(),
          $node->bundle(),
          $langcode,
          $source_field,
        );
        $total_mismatch++;
        continue;
      }

      $currency_value = strtolower($currency_code);

      $target = $translation->get($target_field);
      if ($target->isEmpty()) {
        echo sprintf(
          "[FAIL] Node %d (%s), lang %s, %s -> %s: legacy value %s %s but NO paragraph created.\n",
          $node->id(),
          $node->bundle(),
          $langcode,
          $source_field,
          $target_field,
          $amount,
          $currency_code,
        );
        $total_missing_target++;
        continue;
      }

      $target_item = $target->first();
      $paragraph_id = $target_item->target_id ?? NULL;

      if (!$paragraph_id) {
        echo sprintf(
          "[FAIL] Node %d (%s), lang %s, %s -> %s: target reference exists but has no target_id.\n",
          $node->id(),
          $node->bundle(),
          $langcode,
          $source_field,
          $target_field,
        );
        $total_mismatch++;
        continue;
      }

      /** @var \Drupal\paragraphs\Entity\Paragraph|null $paragraph */
      $paragraph = Paragraph::load($paragraph_id);
      if (!$paragraph) {
        echo sprintf(
          "[FAIL] Node %d (%s), lang %s, %s -> %s: paragraph %d could not be loaded.\n",
          $node->id(),
          $node->bundle(),
          $langcode,
          $source_field,
          $target_field,
          $paragraph_id,
        );
        $total_mismatch++;
        continue;
      }

      $para_amount = NULL;
      if ($paragraph->hasField('field_price') && !$paragraph->get('field_price')->isEmpty()) {
        $para_amount = $paragraph->get('field_price')->first()->value;
      }

      $norm_para_amount = $normalizeAmount($para_amount);

      $para_currency = NULL;
      if ($paragraph->hasField('field_currency') && !$paragraph->get('field_currency')->isEmpty()) {
        $para_currency = $paragraph->get('field_currency')->first()->value;
      }

      // Compare amounts as floats to handle different decimal precision (e.g. 7500.000000 vs 7500.00)
      if ($amount !== NULL && $norm_para_amount !== NULL && (float) $norm_para_amount == (float) $amount && $para_currency === $currency_value) {
        $total_ok++;
      }
      else {
        echo sprintf(
          "[FAIL] Node %d (%s), lang %s, %s -> %s: legacy %s %s (raw %s), paragraph %s %s (raw %s) (para id %d).\n",
          $node->id(),
          $node->bundle(),
          $langcode,
          $source_field,
          $target_field,
          $amount,
          $currency_code,
          $raw_amount,
          $norm_para_amount,
          $para_currency,
          $para_amount,
          $paragraph_id,
        );
        $total_mismatch++;
      }
    }
  }
}

echo "\nSummary:\n";
echo sprintf("  Total legacy price fields checked: %d\n", $total_checked);
echo sprintf("  OK (matching): %d\n", $total_ok);
echo sprintf("  FAIL (missing new paragraph): %d\n", $total_missing_target);
echo sprintf("  FAIL (mismatched values): %d\n", $total_mismatch);

if ($total_checked > 0 && $total_ok === $total_checked) {
  echo "\nAll migrated price paragraphs match legacy values.\n";
}
else {
  echo "\nThere were mismatches or missing paragraphs; see details above.\n";
}
PHP
)"
