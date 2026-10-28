<?php

declare(strict_types=1);

namespace Drupal\ibase_seo\EventSubscriber;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\LocalRedirectResponse;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Sends visitors of imported duplicates to the node they were imported into.
 *
 * Some sites copied old content into a new content type and kept the old
 * nodes published, so the same page exists at two URLs. A permanent redirect
 * leaves search engines with one URL per page.
 */
class ImportedDuplicateRedirect implements EventSubscriberInterface {

  /**
   * The field on the new node that references the node it was imported from.
   */
  const REFERENCE_FIELD = 'field_import_reference';

  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected RouteMatchInterface $routeMatch,
    protected AccountInterface $currentUser,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    // After routing, so the route match is available.
    return [KernelEvents::REQUEST => ['onRequest', 30]];
  }

  /**
   * Redirects anonymous visitors from an imported duplicate.
   */
  public function onRequest(RequestEvent $event): void {
    if (!$event->isMainRequest() || !$this->currentUser->isAnonymous()) {
      return;
    }
    if (!$this->configFactory->get('ibase_seo.settings')->get('redirect_imported_duplicates')) {
      return;
    }
    $node = $this->routeMatch->getParameter('node');
    if ($this->routeMatch->getRouteName() !== 'entity.node.canonical' || !$node instanceof NodeInterface) {
      return;
    }
    $storage = $this->entityTypeManager->getStorage('node');
    $ids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition(self::REFERENCE_FIELD, $node->id())
      ->condition('status', 1)
      ->range(0, 1)
      ->execute();
    if (!$ids) {
      return;
    }
    $target = $storage->load(reset($ids));
    $url = $target->toUrl('canonical', ['absolute' => TRUE, 'language' => $target->language()])->toString(TRUE);
    $response = new LocalRedirectResponse($url->getGeneratedUrl(), 301);
    $response->addCacheableDependency($url);
    $response->addCacheableDependency((new CacheableMetadata())
      ->addCacheableDependency($node)
      ->addCacheableDependency($target)
      ->addCacheTags(['config:ibase_seo.settings'])
      ->addCacheContexts(['user.roles:anonymous']));
    $event->setResponse($response);
  }

}
