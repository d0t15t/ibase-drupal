<?php

namespace Drupal\ibase_api\Service;

use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Render\AttachmentsInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Component\Render\HtmlEscapedText;
use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Utility\Token;

/**
 * Token handling service. Uses core token service or contributed Token.
 */
class IbaseApiTokenService {

  use StringTranslationTrait;

  /**
   * Token service.
   *
   * @var \Drupal\Core\Utility\Token
   */
  protected $token;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Constructs a new MetatagToken object.
   *
   * @param \Drupal\Core\Utility\Token $token
   *   Token service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler.
   */
  public function __construct(
    Token $token,
    ModuleHandlerInterface $moduleHandler
  ) {
    $this->token = $token;
    $this->moduleHandler = $moduleHandler;
  }

  /**
   * Wrapper for the Token module's string parsing.
   *
   * @param string $string
   *   The string to parse.
   * @param array $data
   *   Arguments for token->replace().
   * @param array $options
   *   Any additional options necessary.
   * @param \Drupal\Core\Render\BubbleableMetadata|null $bubbleable_metadata
   *   (optional) An object to which static::generate() and the hooks and
   *   functions that it invokes will add their required bubbleable metadata.
   *
   * @return mixed|string
   *   The processed string.
   */
  public function replace($string, array $data = [], array $options = [], BubbleableMetadata $bubbleable_metadata = NULL) {
    // Set default requirements for metatag unless options specify otherwise.
    $options = $options + [
      'clear' => TRUE,
    ];

    // This calls our Token.php:replace() function. This is necessary b/c the
    // normal token replace uses a render() which causes a render cache memory leak.
    // @TODO: add proper BubbleableMetadata to $response and use normal token or metatag token.
    $replaced = $this->doReplace($string, $data, $options, $bubbleable_metadata);

    // Ensure that there are no double-slash sequences due to empty token
    // values.
    $replaced = preg_replace('/(?<!:)(?<!)\/+\//', '/', $replaced);

    return $replaced;
  }

  /**
   * @param $text
   * @param array $data
   * @param array $options
   * @param \Drupal\Core\Render\BubbleableMetadata|null $bubbleable_metadata
   *
   * @return string|string[]
   */
  public function doReplace($text, array $data = [], array $options = [], BubbleableMetadata $bubbleable_metadata = NULL) {
    $text_tokens = $this->token->scan($text);
    if (empty($text_tokens)) {
      return $text;
    }
    $replacements = [];
    foreach ($text_tokens as $type => $tokens) {
      $replacements += $this->generate($type, $tokens, $data, $options, new BubbleableMetadata());
      if (!empty($options['clear'])) {
        $replacements += array_fill_keys($tokens, '');
      }
    }

    // Escape the tokens, unless they are explicitly markup.
    foreach ($replacements as $token => $value) {
      $replacements[$token] = $value instanceof MarkupInterface ? $value : new HtmlEscapedText($value);
    }

    $tokens = array_keys($replacements);
    $values = array_values($replacements);

    $replaced = str_replace($tokens, $values, $text);
    return $replaced;
  }

  public function generate($type, array $tokens, array $data, array $options, BubbleableMetadata $bubbleable_metadata) {
    return $this->moduleHandler->invokeAll('tokens', [$type, $tokens, $data, $options, $bubbleable_metadata]);
  }


}
