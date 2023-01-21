<?php

namespace Drupal\ibase_gatsby_deploy\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\ibase_gatsby_deploy\Service\GatsbyDeployService;
use Drupal\ibase_gatsby_deploy\Service\GatsbyDeploySettings;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure IBASE gatsby deploy settings for this site.
 */
class GatsbyDeploySettingsForm extends FormBase {

  protected GatsbyDeploySettings $settings;

  public function __construct(GatsbyDeploySettings $settings) {
    $this->settings = $settings;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('ibase_gatsby_deploy.settings')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return $this->settings->getSettingsFormId();
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['frontend_path'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Frontend path'),
      '#placeholder' => '/var/www/gatsby',
      '#description' => $this->t('Path to the gatsby frontend.'),
      '#default_value' => $this->settings->getSettingsFormStateValue('frontend_path'),
    ];
    $form['frontend_log_file'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Frontend log file'),
      '#placeholder' => '/var/www/gatsby',
      '#description' => $this->t('Path to the gatsby deployment log file.'),
      '#default_value' => $this->settings->getSettingsFormStateValue('frontend_log_file'),
    ];
    $form['node_path'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Node path'),
      '#placeholder' => '/Users/ibt/.nvm/versions/node/v14.8.0/bin/',
      '#description' => $this->t('Path to the node executable. When in doubt, try the result of <code>which node</code> without the <code>node</code>'),
      '#default_value' => $this->settings->getSettingsFormStateValue('node_path'),
    ];
    $form['yarn_path'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Yarn path'),
      '#placeholder' => '/usr/local/bin/',
      '#description' => $this->t('Path to the yarn executable. When in doubt, try the result of <code>which yarn</code> without the <code>yarn</code>'),
      '#default_value' => $this->settings->getSettingsFormStateValue('yarn_path'),
    ];
    $form['user'] = [
      '#type' => 'textfield',
      '#title' => $this->t('User to apply for the frontend directories (.cache, public, public_dist)'),
      '#description' => $this->t('Example: www-data'),
      '#default_value' => $this->settings->getSettingsFormStateValue('user'),
    ];
    $form['group'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Group to apply for the frontend directories (.cache, public, public_dist)'),
      '#description' => $this->t('Example: www-data'),
      '#default_value' => $this->settings->getSettingsFormStateValue('group'),
    ];
    $form['mail_to'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Mail address to send error reports to'),
      '#description' => $this->t('Example: www-data'),
      '#default_value' => $this->settings->getSettingsFormStateValue('mail_to'),
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
    ];
    return $form;
//    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
//    if ($form_state->getValue('example') != 'example') {
//      $form_state->setErrorByName('example', $this->t('The value is not correct.'));
//    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $keys = array_keys($values);
    foreach ($values as $key => $value) {
      $this->settings->setSettingsFormStateValue($key, $value);
    }
    \Drupal::messenger()->addStatus(t('Updated form values: %v for form: %f', ['%v' => implode(' ', $keys), '%f' => $this->settings->getSettingsFormId()]));
  }

}
