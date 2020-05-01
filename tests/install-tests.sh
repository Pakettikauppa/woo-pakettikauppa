#!/bin/bash

# -o pipefail is not supported by sh, must therefore use bash
set -eo pipefail

# Ensure tests run on project top level directory so that they find and scan
# the all the project files and not just some random subdirectory
if [ ! -f ".travis.yml" ]
then
  echo "ERROR: This script needs to be run from the same directory as where .travis.yml is."
  exit 1
fi

# Use function to avoid repeating same if structure many times over
function set_variable() {
  VAR_NAME="$1"
  DEFAULT_VAL="$2"
  if [ -n "${!VAR_NAME}" ]
  then
    echo "$VAR_NAME set to value '${!VAR_NAME}'"
  else
    eval "$VAR_NAME='$DEFAULT_VAL'"
    echo "$VAR_NAME set to default value '${!VAR_NAME}'"
  fi
}

set_variable WP_VERSION latest
set_variable WOO_VERSION latest

# Install WordPress PHPUnit tests
if [ -n "$TRAVIS" ]
then
  ./tests/install-wp-tests.sh wordpress_test root '' localhost "$WP_VERSION" "$WOO_VERSION"
else
  ./tests/install-wp-tests.sh wordpress_test phpunit phpunitpass localhost "$WP_VERSION" "$WOO_VERSION"
fi

# Pass on a Travis-CI value if set
if [ -n "$TRAVIS_PHP_VERSION" ]
then
  set_variable PHP_VERSION "$TRAVIS_PHP_VERSION"
else
  set_variable PHP_VERSION 7.3
fi

# Install phpunit 7.x as WordPress 5.x does not support 8.x yet
# Install phpunit 5.x for older WordPress 4.x series
if [ "$PHP_VERSION" = "7.1" ]
then
  # Default to 5.7 as later versions don't work with PHP, WordPress or both.
  # 7.5.x results in
  #   PHP Fatal error:  Class PHPUnit_Util_Test may not inherit from final class
  #   (PHPUnit\Util\Test) in /tmp/wordpress-tests-lib/includes/phpunit6-compat.php
  set_variable PHPUNIT_VERSION 5.7.27
else
  set_variable PHPUNIT_VERSION 7.5.20
fi

curl -sS "https://phar.phpunit.de/phpunit-$PHPUNIT_VERSION.phar" -o /tmp/phpunit
chmod +x /tmp/phpunit

if [ -z "$SNIFF" ]
then
  echo "Skip installing PHP Code Sniffer"
  exit 0
fi

# Use specific versions so every test run uses exactly same standards until this
# file is explicitly updated to new standards versions
set_variable PHPCS_DIR /tmp/phpcs
set_variable PHPCS_VERSION 3.3.2
set_variable WP_SNIFFS_VERSION 2.1.0
set_variable WP_SNIFFS_DIR /tmp/wp-sniffs
set_variable SECURITY_SNIFFS_VERSION 2.0.0
set_variable SECURITY_SNIFFS_DIR /tmp/security-sniffs
set_variable PHP_COMPATIBILITY_SNIFFS_VERSION 9.1.1
set_variable PHP_COMPATIBILITY_SNIFFS_DIR /tmp/compatibility-sniffs

rm -rfv $PHPCS_DIR $WP_SNIFFS_DIR $SECURITY_SNIFFS_DIR $PHP_COMPATIBILITY_SNIFFS_DIR
mkdir -pv $PHPCS_DIR $WP_SNIFFS_DIR $SECURITY_SNIFFS_DIR $PHP_COMPATIBILITY_SNIFFS_DIR

# Install PHP_CodeSniffer
curl -sSL https://github.com/squizlabs/PHP_CodeSniffer/archive/$PHPCS_VERSION.tar.gz | tar xz --strip 1 -C "$PHPCS_DIR"

# Install WordPress Coding Standards
curl -sSL https://github.com/WordPress-Coding-Standards/WordPress-Coding-Standards/archive/$WP_SNIFFS_VERSION.tar.gz | tar xz --strip 1 -C $WP_SNIFFS_DIR

# Install PHPCS Security Audit
curl -sSL https://github.com/FloeDesignTechnologies/phpcs-security-audit/archive/$SECURITY_SNIFFS_VERSION.tar.gz | tar xz --strip 1 -C $SECURITY_SNIFFS_DIR

# Install PHP Compatibility
curl -sSL https://github.com/PHPCompatibility/PHPCompatibility/archive/$PHP_COMPATIBILITY_SNIFFS_VERSION.tar.gz | tar xz --strip 1 -C $PHP_COMPATIBILITY_SNIFFS_DIR

# Set install path for sniffs
$PHPCS_DIR/bin/phpcs --config-set installed_paths $WP_SNIFFS_DIR,$SECURITY_SNIFFS_DIR,$PHP_COMPATIBILITY_SNIFFS_DIR

# Show installed sniffs
${PHPCS_DIR}/bin/phpcs -i
