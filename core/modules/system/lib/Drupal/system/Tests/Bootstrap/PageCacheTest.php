<?php

/**
 * @file
 * Definition of Drupal\system\Tests\Bootstrap\PageCacheTest.
 */

namespace Drupal\system\Tests\Bootstrap;

use Drupal\simpletest\WebTestBase;

/**
 * Enables the page cache and tests it with various HTTP requests.
 */
class PageCacheTest extends WebTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('test_page_test', 'system_test');

  public static function getInfo() {
    return array(
      'name' => 'Page cache test',
      'description' => 'Enable the page cache and test it with various HTTP requests.',
      'group' => 'Bootstrap'
    );
  }

  function setUp() {
    parent::setUp();

    config('system.site')
      ->set('name', 'Drupal')
      ->set('page.front', 'test-page')
      ->save();
  }

  /**
   * Tests support of requests with If-Modified-Since and If-None-Match headers.
   */
  function testConditionalRequests() {
    $config = config('system.performance');
    $config->set('cache.page.use_internal', 1);
    $config->set('cache.page.max_age', 300);
    $config->save();

    // Fill the cache.
    $this->drupalGet('');
    // Verify the page is not printed twice when the cache is cold.
    $this->assertNoPattern('#<html.*<html#');

    $this->drupalHead('');
    $this->assertEqual($this->drupalGetHeader('X-Drupal-Cache'), 'HIT', 'Page was cached.');
    $etag = $this->drupalGetHeader('ETag');
    $last_modified = $this->drupalGetHeader('Last-Modified');

    $this->drupalGet('', array(), array('If-Modified-Since: ' . $last_modified, 'If-None-Match: ' . $etag));
    $this->assertResponse(304, 'Conditional request returned 304 Not Modified.');

    $this->drupalGet('', array(), array('If-Modified-Since: ' . gmdate(DATE_RFC822, strtotime($last_modified)), 'If-None-Match: ' . $etag));
    $this->assertResponse(304, 'Conditional request with obsolete If-Modified-Since date returned 304 Not Modified.');

    $this->drupalGet('', array(), array('If-Modified-Since: ' . gmdate(DATE_RFC850, strtotime($last_modified)), 'If-None-Match: ' . $etag));
    $this->assertResponse(304, 'Conditional request with obsolete If-Modified-Since date returned 304 Not Modified.');

    $this->drupalGet('', array(), array('If-Modified-Since: ' . $last_modified));
    // Verify the page is not printed twice when the cache is warm.
    $this->assertNoPattern('#<html.*<html#');
    $this->assertResponse(200, 'Conditional request without If-None-Match returned 200 OK.');
    $this->assertEqual($this->drupalGetHeader('X-Drupal-Cache'), 'HIT', 'Page was cached.');

    $this->drupalGet('', array(), array('If-Modified-Since: ' . gmdate(DATE_RFC1123, strtotime($last_modified) + 1), 'If-None-Match: ' . $etag));
    $this->assertResponse(200, 'Conditional request with new a If-Modified-Since date newer than Last-Modified returned 200 OK.');
    $this->assertEqual($this->drupalGetHeader('X-Drupal-Cache'), 'HIT', 'Page was cached.');

    $user = $this->drupalCreateUser();
    $this->drupalLogin($user);
    $this->drupalGet('', array(), array('If-Modified-Since: ' . $last_modified, 'If-None-Match: ' . $etag));
    $this->assertResponse(200, 'Conditional request returned 200 OK for authenticated user.');
    $this->assertFalse($this->drupalGetHeader('X-Drupal-Cache'), 'Absense of Page was not cached.');
  }

  /**
   * Tests cache headers.
   */
  function testPageCache() {
    $config = config('system.performance');
    $config->set('cache.page.use_internal', 1);
    $config->set('cache.page.max_age', 300);
    $config->save();

    // Fill the cache.
    $this->drupalGet('system-test/set-header', array('query' => array('name' => 'Foo', 'value' => 'bar')));
    $this->assertEqual($this->drupalGetHeader('X-Drupal-Cache'), 'MISS', 'Page was not cached.');
    $this->assertEqual($this->drupalGetHeader('Vary'), 'Cookie,Accept-Encoding', 'Vary header was sent.');
    $this->assertEqual($this->drupalGetHeader('Cache-Control'), 'public, max-age=300', 'Cache-Control header was sent.');
    $this->assertEqual($this->drupalGetHeader('Expires'), 'Sun, 19 Nov 1978 05:00:00 GMT', 'Expires header was sent.');
    $this->assertEqual($this->drupalGetHeader('Foo'), 'bar', 'Custom header was sent.');

    // Check cache.
    $this->drupalGet('system-test/set-header', array('query' => array('name' => 'Foo', 'value' => 'bar')));
    $this->assertEqual($this->drupalGetHeader('X-Drupal-Cache'), 'HIT', 'Page was cached.');
    $this->assertEqual($this->drupalGetHeader('Vary'), 'Cookie,Accept-Encoding', 'Vary: Cookie header was sent.');
    $this->assertEqual($this->drupalGetHeader('Cache-Control'), 'public, max-age=300', 'Cache-Control header was sent.');
    $this->assertEqual($this->drupalGetHeader('Expires'), 'Sun, 19 Nov 1978 05:00:00 GMT', 'Expires header was sent.');
    $this->assertEqual($this->drupalGetHeader('Foo'), 'bar', 'Custom header was sent.');

    // Check replacing default headers.
    $this->drupalGet('system-test/set-header', array('query' => array('name' => 'Expires', 'value' => 'Fri, 19 Nov 2008 05:00:00 GMT')));
    $this->assertEqual($this->drupalGetHeader('Expires'), 'Fri, 19 Nov 2008 05:00:00 GMT', 'Default header was replaced.');
    $this->drupalGet('system-test/set-header', array('query' => array('name' => 'Vary', 'value' => 'User-Agent')));
    $this->assertEqual($this->drupalGetHeader('Vary'), 'User-Agent,Accept-Encoding', 'Default header was replaced.');

    // Check that authenticated users bypass the cache.
    $user = $this->drupalCreateUser();
    $this->drupalLogin($user);
    $this->drupalGet('system-test/set-header', array('query' => array('name' => 'Foo', 'value' => 'bar')));
    $this->assertFalse($this->drupalGetHeader('X-Drupal-Cache'), 'Caching was bypassed.');
    $this->assertTrue(strpos($this->drupalGetHeader('Vary'), 'Cookie') === FALSE, 'Vary: Cookie header was not sent.');
    $this->assertEqual($this->drupalGetHeader('Cache-Control'), 'must-revalidate, no-cache, post-check=0, pre-check=0, private', 'Cache-Control header was sent.');
    $this->assertEqual($this->drupalGetHeader('Expires'), 'Sun, 19 Nov 1978 05:00:00 GMT', 'Expires header was sent.');
    $this->assertEqual($this->drupalGetHeader('Foo'), 'bar', 'Custom header was sent.');

    // Check the omit_vary_cookie setting.
    $this->drupalLogout();
    $settings['settings']['omit_vary_cookie'] = (object) array(
      'value' => TRUE,
      'required' => TRUE,
    );
    $this->writeSettings($settings);
    $this->drupalGet('system-test/set-header', array('query' => array('name' => 'Foo', 'value' => 'bar')));
    $this->assertTrue(strpos($this->drupalGetHeader('Vary'), 'Cookie') === FALSE, 'Vary: Cookie header was not sent.');
  }

  /**
   * Tests page compression.
   *
   * The test should pass even if zlib.output_compression is enabled in php.ini,
   * .htaccess or similar, or if compression is done outside PHP, e.g. by the
   * mod_deflate Apache module.
   */
  function testPageCompression() {
    $config = config('system.performance');
    $config->set('cache.page.use_internal', 1);
    $config->set('cache.page.max_age', 300);
    $config->save();

    // Fill the cache and verify that output is compressed.
    $this->drupalGet('', array(), array('Accept-Encoding: gzip,deflate'));
    $this->assertEqual($this->drupalGetHeader('X-Drupal-Cache'), 'MISS', 'Page was not cached.');
    $this->drupalSetContent(gzinflate(substr($this->drupalGetContent(), 10, -8)));
    $this->assertRaw('</html>', 'Page was gzip compressed.');

    // Verify that cached output is compressed.
    $this->drupalGet('', array(), array('Accept-Encoding: gzip,deflate'));
    $this->assertEqual($this->drupalGetHeader('X-Drupal-Cache'), 'HIT', 'Page was cached.');
    $this->assertEqual($this->drupalGetHeader('Content-Encoding'), 'gzip', 'A Content-Encoding header was sent.');
    $this->drupalSetContent(gzinflate(substr($this->drupalGetContent(), 10, -8)));
    $this->assertRaw('</html>', 'Page was gzip compressed.');

    // Verify that a client without compression support gets an uncompressed page.
    $this->drupalGet('');
    $this->assertEqual($this->drupalGetHeader('X-Drupal-Cache'), 'HIT', 'Page was cached.');
    $this->assertFalse($this->drupalGetHeader('Content-Encoding'), 'A Content-Encoding header was not sent.');
    $this->assertTitle(t('Test page | @site-name', array('@site-name' => config('system.site')->get('name'))), 'Site title matches.');
    $this->assertRaw('</html>', 'Page was not compressed.');
  }
}
