<?php

namespace drunomics\MultisiteRequestMatcher;


use Symfony\Component\HttpFoundation\Request;

/**
 * Matches requests to multisites.
 */
class RequestMatcher {

  /**
   * Statically cached instance.
   *
   * @var static
   */
  static $instance;

  /**
   * Gets an instance, while making sure there is only one instantiated.
   *
   * @return static
   */
  public static function getInstance() {
    if (!isset(static::$instance)) {
      static::$instance = new static();
    }
    return static::$instance;
  }

  /**
   * The allowed site variants (like admin, api, ...).
   *
   * @var string[]
   */
  protected $variants = [];

  /**
   * The site variant separator, e.g., '--' or '.'.
   *
   * @var string
   */
  protected $variantSeparator = '--';

  /**
   * The list of site names.
   *
   * @var string[]
   */
  protected $sites = [];

  /**
   * The name of the default site.
   *
   * @var string
   */
  protected $defaultSite;

  /**
   * The multisite base domain if used.
   *
   * @var string|null
   */
  protected $multisiteDomain;

  /**
   * The mutlisite domain prefix separator.
   *
   * @var string
   */
  protected $multisiteDomainSeparator = '_';

  /**
   * Creates the object.
   */
  public function __construct() {
    if ($ips = getenv('TRUSTED_PROXIES')) {
      Request::setTrustedProxies(explode(' ', $ips));
    }
    if ($host_header = getenv('HEADER_FORWARDED_HOST')) {
      Request::setTrustedHeaderName(Request::HEADER_X_FORWARDED_HOST, $host_header);
    }
    if ($variants = getenv('APP_SITE_VARIANTS')) {
      $this->variants = explode(' ', $variants);
    }
    if ($separator = getenv('APP_SITE_VARIANT_SEPARATOR')) {
      $this->variantSeparator = $separator;
    }
    $this->sites = explode(' ', getenv('APP_SITES'));
    $this->defaultSite = getenv('APP_DEFAULT_SITE');
    if (!$this->defaultSite) {
      $this->defaultSite = reset($this->sites);
    }

    if ($domain = getenv('APP_MULTISITE_DOMAIN')) {
      $this->multisiteDomain = $domain;
    }
    if ($separator = getenv('APP_MULTISITE_DOMAIN_PREFIX_SEPARATOR')) {
      $this->multisiteDomainSeparator = $separator;
    }
  }

  /**
   * Gets a request from globals without parsing forms.
   *
   * @return \Symfony\Component\HttpFoundation\Request
   */
  private function getRequestFromGlobals() {
    // With the php's bug #66606, the php's built-in web server
    // stores the Content-Type and Content-Length header values in
    // HTTP_CONTENT_TYPE and HTTP_CONTENT_LENGTH fields.
    $server = $_SERVER;
    if ('cli-server' === \PHP_SAPI) {
      if (array_key_exists('HTTP_CONTENT_LENGTH', $_SERVER)) {
        $server['CONTENT_LENGTH'] = $_SERVER['HTTP_CONTENT_LENGTH'];
      }
      if (array_key_exists('HTTP_CONTENT_TYPE', $_SERVER)) {
        $server['CONTENT_TYPE'] = $_SERVER['HTTP_CONTENT_TYPE'];
      }
    }
    return (new Request($_GET, $_POST, array(), $_COOKIE, $_FILES, $server));
  }

  /**
   * Matches the request based upon the given config.
   *
   * If successful, the following environment variables are set:
   *  - SITE
   *  - SITE_VARIANT
   *  - SITE_HOST
   *  - SITE_MAIN_HOST
   *
   * @param \Symfony\Component\HttpFoundation\Request|NULL $request
   *   (optional) The request object.
   *
   * @return string
   *   The name of the matched site.
   *
   * @throws \drunomics\MultisiteRequestMatcher\RequestMatchException
   *   Thrown if the request cannot be matched.
   */
  public function match(Request $request = NULL) {
    // Do not attempt to match on CLI but apply the default site.
    if (!$request && php_sapi_name() == "cli") {
      $site = getenv('SITE') ?: $this->defaultSite;
      putenv('SITE=' . $site);
      putenv('SITE_VARIANT=');
      if ($this->multisiteDomain) {
        putenv('SITE_HOST=' . $this->defaultSite . $this->multisiteDomainSeparator . $this->multisiteDomain);
      }
      else {
        putenv('SITE_HOST=' . getenv('APP_SITE_DOMAIN--' . $site));
      }
      return $site;
    }

    if (!$request) {
      $request = $this->getRequestFromGlobals();
    }

    $host = $request->getHost();
    $site_host = $host;

    // Match per common multisite domain.
    if ($this->multisiteDomain) {
      $matches = [];
      $suffix = $this->multisiteDomainSeparator . $this->multisiteDomain;
      $variants = implode('|', $this->variants);
      if (preg_match('/^(' . $variants . ')' . str_replace('.', '\.', $this->variantSeparator . '([a-z\-]+)' . $suffix) . '$/', $host, $matches)) {
        $site_variant = $matches[1];
        $site = $matches[2];
        $site_main_host = $site . $this->multisiteDomainSeparator . $this->multisiteDomain;
      }
      elseif (preg_match('/^([a-z\-]+)' . str_replace('.', '\.', $suffix) . '$/', $host, $matches)) {
        $site_variant = NULL;
        $site = $matches[1];
        $site_main_host = $site . $this->multisiteDomainSeparator . $this->multisiteDomain;
      }
      elseif ($host == $this->multisiteDomain) {
        $site = $this->defaultSite;
        $site_variant = NULL;
        $site_host = $site . $this->multisiteDomainSeparator . $this->multisiteDomain;
        $site_main_host = $site_host;
      }
      else {
        throw new RequestMatchException("Unable to match given multisite domain.");
      }
      if (!in_array($site, $this->sites)) {
        throw new RequestMatchException("Unknown site " . strip_tags($site) . " given.");
      }
    }
    else {
      // There is no common base domain, thus collect of possible hosts.
      $matches = [];
      $variants = implode('|', $this->variants);
      foreach ($this->sites as $current_site) {
        $site_domain = getenv('APP_SITE_DOMAIN--' . $current_site);
        if (empty($site_domain)) {
          throw new RequestMatchException("Missing API_SITE_DOMAIN environment variable for site " . strip_tags($current_site) . ".");
        }

        if (preg_match('/^' . str_replace('.', '\.', $site_domain) . '$/', $host)) {
          $site_main_host = $site_domain;
          $site = $current_site;
          $site_variant = NULL;
          break;
        }
        // Check for a site-variant match.
        if (preg_match('/^' . str_replace('.', '\.', '(' . $variants . ')' . $this->variantSeparator . $site_domain . '$') . '/', $host, $matches)) {
          $site_main_host = $site_domain;
          $site = $current_site;
          $site_variant = $matches[1];
          break;
        }
      }
      if (empty($site)) {
        throw new RequestMatchException("Unable to match a site domain.");
      }
    }
    putenv('SITE=' . $site);
    putenv('SITE_VARIANT=' . $site_variant);
    putenv('SITE_HOST=' . $site_host);
    putenv('SITE_MAIN_HOST=' . $site_main_host);
    return $site;
  }

}
