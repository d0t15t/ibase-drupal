<?php

namespace Drupal\Tests\shopify\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests basic site functionality when the module is installed.
 *
 * @group shopify
 */
class SmokeTest extends BrowserTestBase {

  /**
   * Ignore missing schema.
   *
   * @var bool
   *
   * @see https://www.drupal.org/node/2391795
   */
  protected $strictConfigSchema = FALSE;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  public static $modules = ['shopify'];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    // Make sure to complete the normal setup steps first.
    parent::setUp();

    // Set the front page to "node".
    \Drupal::configFactory()
      ->getEditable('system.site')
      ->set('page.front', '/node')
      ->save(TRUE);
  }

  /**
   * Make sure the site still works. For now just check the front page.
   */
  public function testSiteLoads() {
    // Load the front page.
    $this->drupalGet('<front>');

    // Confirm that the site didn't throw a server error or something else.
    $this->assertSession()->statusCodeEquals(200);

    // Confirm that the front page contains the standard text.
    $this->assertText('Welcome to Drupal');
  }

  /**
   * Make sure the shop overview page still loads.
   */
  public function testShopOverviewLoads() {
    $this->drupalLogin($this->drupalCreateUser([], NULL, TRUE));

    $this->drupalGet('/admin/config/system/shopify');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertText('Shopify Store Settings');

  }

  /**
   * Make sure the API settings form is accessible.
   */
  public function testSpiSettingsFormLoads() {
    $this->drupalLogin($this->drupalCreateUser([], NULL, TRUE));

    $this->drupalGet('/admin/config/system/shopify_api');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertText('Shopify API Settings');

  }

}
