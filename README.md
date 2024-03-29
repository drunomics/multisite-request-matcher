# Multisite Request Matcher

Matches requests to multisites based upon configuration driven by environment variables.
The packages works well with symfony/dotenv and symfony/http-foundation as used by drupal/core.

The multisite setup supports configuration via a common base-domain ($APP_MULTISITE_DOMAIN) or via
per site domains. Via environment-dependent variables the common base-domain may be enabled for preview
environments only also. The configured default site is used when the common multisite base-domain is accessed without
any suiting prefix. Next, it's used for CLI invocations if no SITE variable is given a.

## Installation

    composer require drunomics/multisite-request-matcher

## Configuration

The package support construction site URLs via via a common base-domain ($APP_MULTISITE_DOMAIN) or via per site domains.
Optional, site variants like "admin" or "api" are supported - useful if the app uses multiple hosts for things like the
admin interface or an API endpoint.

That can be used to construct URLs like the following:

- Commom multisite domain:

        https://site-a_stage.example.com
        https://site-b_stage.example.com

- Per site domains:

        https://www.site-a.com
        https://wwww.site-b.com

- Common multisite domain with variants:

        https://api--site-a_stage.example.com
        https://api--site-b_stage.example.com
        https://admin--site-a_stage.example.com
        https://admin--site-b_stage.example.com

- Per site domains with variants:

        https://api.site-a.com
        https://api.site-b.com
        https://admin.site-a.com
        https://admin.site-b.com


The following environment variables may be set to configure the request matcher:

| Variable name | Required | Example Value | Description |
| ------------- | -------- | ------------- | ------------|
| TRUSTED_PROXIES | No |  127.0.0.1 | A list of IP addresses or subnets, separated by space. |
| HEADER_FORWARDED_HOST | No | X_FORWARDED_HOST | A non-standard value for the host header. | 
| APP_SITES | Yes | site-a site-b | The list of site names, separated by space. May contain lowercase characters and dashes only. |
| APP_DEFAULT_SITE | No | site-a | The default site to set. If not set, the first set will be set by default. |
| APP_SITE_VARIANTS| No | admin api | An optional list of variants; i.e. variants of the same site.|
| APP_SITE_VARIANT_SEPARATOR | No | -- | The separator between the variant name and the host, defaults to "--". |
| APP_MULTISITE_DOMAIN | ~ | stage.codebase.dev | A common base domain for all sites. Required when multisite base domains should be used. |
| APP_MULTISITE_DOMAIN_PREFIX_SEPARATOR | No | _ | The separator between the site name and the common multisite base domain. Defaults to '_'. |
| APP_SITE_DOMAIN__{{ SITE }} | ~ | site-a.com | The per-site domain - required when per-site domains should be used. One variable per site must be provided with dashes replaced to underscores, e.g. for site-a the variable name would be `APP_SITE_DOMAIN__site_a` |
| APP_SITE_DOMAIN_ALIASES__{{ SITE }} | No | site-a.hoster.com,site-a.hoster.com | Comma separated, per-site domain aliases that are allowed in addition to the main domain. Useful when access should be allowed via some non-primary domains also; e.g., when behind a CDN. One variable per site must be provided with dashes replaced to underscores, e.g. for site-a the variable name would be `APP_SITE_DOMAIN_ALIASES__site_a` |
| APP_SITE_DOMAIN | ~ | site-a.com | If an environment is bound to a fixed site, the site's domain. Requires SITE to be predefined. |
| APP_SITE_DOMAIN_ALIASES | No | site-a.host.com,site-a.host2.com | If an environment is bound to a fixed site, the site's domain aliases (see above). Requires SITE to be predefined. |

## Results

- The matched host is set as trusted host to the symfony/http-foundation request API via trusted host patterns.
- The following environment variables are set:

| Variable name | Example Value | Description |
| ------------- | ------------- | ----------- |
| SITE | site-a | The active site. |
| SITE_VARIANT | api | The active site variant. Empty if no variant is active.|
| SITE_HOST | api--site-b_stage.example.com | The site's full host for the active site and variant. |
| SITE_MAIN_HOST | stage.example.com | The site's main host, without any variant. |

## CLI invocations

In order to make the same environment variables available for CLI invocations, the package provides the binary
`request-matcher-site-variables` which outputs them based upon the set `$SITE` variable. Site variants are not supported
in CLI requests, thus SITE_VARIANT is is always empty.

## Usage with Drupal

* Best, invoke the request matcher via the composer autoloader; that makes sure it is invoked very early and has matched
  requests before anything else goes on. For an example refer to [this](https://github.com/drunomics/drupal-project/blob/4.x/composer.json#L58)
  
  Be sure your environment variables are set and invoke it like that:

      $site = drunomics\MultisiteRequestMatcher\RequestMatcher::getInstance()
        ->match();
  
* Add the following line to Drupal's sites.php such that Drupal can pick up the matched site. The site name of the
  APP_SITES variable should match the Drupal site directory names:

      $sites[$request->getHost()] = getenv('SITE');

* Remove any trusted host patterns from Drupal as the request matcher already checked it.

## Credits
 
  developed by drunomics GmbH, hello@drunomics.com
  Please refer to the commit log individual contributors. 
