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

# Syntax check all PHP files and fail for any error text in STDERR
! find . -type f -name "*.php" -exec php -d error_reporting=32767 -l {} \; 2>&1 >&- | grep "^"

# More extensive PHP Style Check if SNIFF is set
if [ -n "$SNIFF" ] && [ "$SNIFF" != "0" ]
then
  if [ -f "/tmp/phpcs/bin/phpcs" ]
  then
    PHPCS_BIN="/tmp/phpcs/bin/phpcs"
  elif which phpcs
  then
    PHPCS_BIN="$(which phpcs)"
  else
    echo "ERROR: No 'phpcs' was not found, cannot run tests!"
    exit 1
  fi

  echo "Running $PHPCS_BIN:"
  $PHPCS_BIN -i
  $PHPCS_BIN --extensions=php -n --standard=phpcs.xml
else
  echo "Skipping PHPCS as SNIFF is not set."
fi

# Run PHPUnit tests
if [ -f "/tmp/phpunit" ]
then
  PHPUNIT_BIN="/tmp/phpunit"
elif which phpunit
then
  PHPUNIT_BIN="$(which phpunit)"
else
  echo "ERROR: No 'phpunit' was not found, cannot run tests!"
  exit 1
fi
echo "Running $PHPUNIT_BIN:"
$PHPUNIT_BIN --verbose
