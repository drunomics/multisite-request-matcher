<?php

namespace drunomics\MultisiteRequestMatcher\Tests;

use drunomics\MultisiteRequestMatcher\RequestMatchException;
use PHPUnit\Framework\TestCase;
use drunomics\MultisiteRequestMatcher\RequestMatcher;
use Symfony\Component\HttpFoundation\Request;

/**
 * @coversDefaultClass  \drunomics\MultisiteRequestMatcher\RequestMatcher
 */
class RequestMatcherTest extends TestCase {

  /**
   * Tests matching.
   *
   * @covers ::match
   */
  public function testMatch() {
    // Test with multisite domain settings.
    putenv("APP_MULTISITE_DOMAIN_PREFIX_SEPARATOR=.");
    putenv("APP_MULTISITE_DOMAIN=localdev.space");
    putenv("APP_SITE_VARIANT_SEPARATOR=--");
    putenv("APP_SITE_VARIANTS=api admin");
    putenv("APP_DEFAULT_SITE=site-a");
    putenv("APP_SITES=site-a site-b");
    $this->assertHostMatches('site-a.localdev.space', 'site-a', '');
    $this->assertHostMatches('api--site-a.localdev.space', 'site-a', 'api');
    $this->assertHostMatches('admin--site-a.localdev.space', 'site-a', 'admin');
    $this->assertHostMatches('api--site-b.localdev.space', 'site-b', 'api');
    $this->assertHostMatches('admin--site-b.localdev.space', 'site-b', 'admin');
    $this->assertHostMatches('site-b.localdev.space', 'site-b', '');
    $this->assertHostMatches('localdev.space', 'site-a', '');
    $this->assertHostDoesNotMatch('foo.localdev.space');
    $this->assertHostDoesNotMatch('foo--site-a.localdev.space');

    // Test with multisite domain settings.
    putenv("APP_MULTISITE_DOMAIN_PREFIX_SEPARATOR=.");
    putenv("APP_MULTISITE_DOMAIN=localdev.space");
    putenv("APP_SITE_VARIANT_SEPARATOR=--");
    putenv("APP_SITE_VARIANTS");
    putenv("APP_DEFAULT_SITE=site-a");
    putenv("APP_SITES=site-a site-b");
    $this->assertHostMatches('site-a.localdev.space', 'site-a', '');
    $this->assertHostMatches('site-b.localdev.space', 'site-b', '');
    $this->assertHostMatches('localdev.space', 'site-a', '');
    $this->assertHostDoesNotMatch('foo.localdev.space');
    $this->assertHostDoesNotMatch('api--site-a.localdev.space');

    // Test with per-site domains.
    putenv("APP_MULTISITE_DOMAIN");
    putenv("APP_SITE_VARIANT_SEPARATOR=.");
    putenv("APP_SITE_VARIANTS=api admin");
    putenv("APP_DEFAULT_SITE=site-a");
    putenv("APP_SITES=site-a site-b");
    putenv("APP_SITE_DOMAIN--site-a=site-a.com");
    putenv("APP_SITE_DOMAIN--site-b=site-b.com");
    $this->assertHostMatches('site-a.com', 'site-a', '');
    $this->assertHostMatches('api.site-a.com', 'site-a', 'api');
    $this->assertHostMatches('admin.site-a.com', 'site-a', 'admin');
    $this->assertHostMatches('api.site-b.com', 'site-b', 'api');
    $this->assertHostMatches('admin.site-b.com', 'site-b', 'admin');
    $this->assertHostMatches('site-b.com', 'site-b', '');
    $this->assertHostDoesNotMatch('com');
    $this->assertHostDoesNotMatch('foo.site-b.com');
    $this->assertHostDoesNotMatch('api--site-b.com');

    putenv("APP_MULTISITE_DOMAIN");
    putenv("APP_SITE_VARIANTS");
    putenv("APP_SITE_VARIANT_SEPARATOR=.");
    putenv("APP_DEFAULT_SITE=site-a");
    putenv("APP_SITES=site-a site-b");
    putenv("APP_SITE_DOMAIN--site-a=site-a.com");
    putenv("APP_SITE_DOMAIN--site-b=site-b.com");
    $this->assertHostMatches('site-a.com', 'site-a', '');
    $this->assertHostMatches('site-b.com', 'site-b', '');
    $this->assertHostDoesNotMatch('com');
    $this->assertHostDoesNotMatch('foo.site-b.com');
    $this->assertHostDoesNotMatch('admin.site-b.com');
    $this->assertHostDoesNotMatch('api.site-b.com');
    $this->assertHostDoesNotMatch('api--site-b.com');
  }

  /**
   * Asserts the host matches.
   */
  protected function assertHostMatches($host, $site, $site_variant = '') {
    $request = $this->prophesize(Request::class);
    $request->getHost()->willReturn($host);
    (new RequestMatcher())->match($request->reveal());
    $this->assertEquals($site, getenv('SITE'));
    $this->assertEquals($site_variant, getenv('SITE_VARIANT'));
  }

  /**
   * Asserts the host does not match.
   */
  protected function assertHostDoesNotMatch($host) {
    try {
      $request = $this->prophesize(Request::class);
      $request->getHost()->willReturn($host);
      (new RequestMatcher())->match($request->reveal());
      $this->fail("$host should not match.");
    }
    catch (RequestMatchException $e) {
      $this->assertInstanceOf(RequestMatchException::class, $e);
    }
  }

}
