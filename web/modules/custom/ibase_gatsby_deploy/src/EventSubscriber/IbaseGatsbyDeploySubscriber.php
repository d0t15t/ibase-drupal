<?php

namespace Drupal\ibase_gatsby_deploy\EventSubscriber;

use Drupal\Core\Messenger\MessengerInterface;
use Drupal\ibase_gatsby_deploy\Service\GatsbyDeployService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * IBASE gatsby deploy event subscriber.
 */
class IbaseGatsbyDeploySubscriber implements EventSubscriberInterface {

  protected GatsbyDeployService $deploy;

  public function __construct(GatsbyDeployService $deploy) {
    $this->deploy = $deploy;
  }

  /**
   * Kernel request event handler.
   *
   * @param \Symfony\Component\HttpKernel\Event\GetResponseEvent $event
   *   Response event.
   */
  public function onKernelRequest(GetResponseEvent $event) {
//    $this->messenger->addStatus(__FUNCTION__);
  }

  /**
   * Kernel response event handler.
   *
   * @param \Symfony\Component\HttpKernel\Event\FilterResponseEvent $event
   *   Response event.
   */
  public function onKernelResponse(FilterResponseEvent $event) {
//    $this->messenger->addStatus(__FUNCTION__);
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      KernelEvents::REQUEST => ['onKernelRequest'],
      KernelEvents::RESPONSE => ['onKernelResponse'],
    ];
  }

}
