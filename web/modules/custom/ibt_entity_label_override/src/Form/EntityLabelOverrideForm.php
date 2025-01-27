<?php

declare(strict_types=1);

namespace Drupal\ibt_entity_label_override\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure AK Theme settings for this site.
 */
final class EntityLabelOverrideForm extends ConfigFormBase {

  const POE = 'pagetitle-override-entities';

  protected EntityTypeManagerInterface $entityTypeManager;

  public function __construct(ConfigFactoryInterface $config_factory, EntityTypeManagerInterface $entityTypeManager) {
    parent::__construct($config_factory);

    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'ibt_entity_label_override_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['ibt_entity_label_override.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $node_types = $this->entityTypeManager->getStorage('node_type')->loadMultiple();
    $poe = $this->config('ibt_entity_label_override.settings')->get("entity-label-overrides");
    foreach (array_keys($node_types) as $node_type_id) {
      $poe_el =  $poe['node'][$node_type_id] ?? '';
      $form["entity-label-overrides-node-$node_type_id"]
        = [
        '#type' => 'textfield',
        '#title' => $this->t('Page title field override for %type', ['%type' => $node_type_id]),
        '#default_value' => $poe_el,
      ];
    }
    $form['usage-information'] =  [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $this->t('Enter the name of the field to use as the page title. Leave blank to use the default title.'),
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    // Example:
    // @code
    //   if ($form_state->getValue('example') === 'wrong') {
    //     $form_state->setErrorByName(
    //       'message',
    //       $this->t('The value is not correct.'),
    //     );
    //   }
    // @endcode
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $node_types = array_keys($this->entityTypeManager->getStorage('node_type')->loadMultiple());
    $values = array_map(fn($node_type_id) => $form_state->getValue("entity-label-overrides-node-$node_type_id"), $node_types);
    $poe_keyed_values = ['node'=> array_combine($node_types, $values)];
    foreach (array_keys($node_types) as $node_type_id) {
      $this->config('ibt_entity_label_override.settings')
        ->set("entity-label-overrides", $poe_keyed_values)
        ->save();
    }
    parent::submitForm($form, $form_state);
  }

}
