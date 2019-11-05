#!/bin/bash

set -euo pipefail

SNIFF=1
DIR=$(basename $(pwd))

if [ "$DIR" != "bin" ]; then
  echo "You must run this script from the bin directory."
  exit 1
fi

cd ..

if [[ "$SNIFF" == "1" ]]; then export PHPCS_DIR=/tmp/phpcs; export PHPCS_VERSION=3.3.2; fi

# Syntax check all PHP files and fail for any error text in STDERR
! find . -type f -name "*.php" -exec php -d error_reporting=32767 -l {} \; 2>&1 >&- | grep "^"
# More extensive PHP Style Check
if [[ "$SNIFF" == "1" ]]; then $PHPCS_DIR/bin/phpcs -i; $PHPCS_DIR/bin/phpcs -n --standard=phpcs.xml; fi
# Run PHPUnit tests, disabled due to permanent Travis failure
/tmp/phpunit --verbose
