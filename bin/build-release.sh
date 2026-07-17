#!/bin/bash
#
# Build a distributable plugin ZIP (wp.org submission / direct sales).
#
#   bash bin/build-release.sh            → dist/thewpfeeds-{version}.zip
#
# Ships: PHP sources, compiled block (build/), templates, fixtures, autoloader.
# Excludes: dev tooling, tests, block JS sources, repo docs.

set -e

cd "$(dirname "$0")/.."

VERSION=$(grep -m1 "THEWPFEEDS_VERSION" thewpfeeds.php | sed "s/.*'\([0-9.]*\)'.*/\1/")
STAGE="dist/thewpfeeds"
ZIP="dist/thewpfeeds-${VERSION}.zip"

echo "Building thewpfeeds ${VERSION}..."

npm run build --silent
composer install --no-dev --quiet --optimize-autoloader

rm -rf dist
mkdir -p "$STAGE"

rsync -a \
  --exclude='.git*' \
  --exclude='node_modules' \
  --exclude='dist' \
  --exclude='tests' \
  --exclude='bin' \
  --exclude='blocks' \
  --exclude='phpcs.xml.dist' \
  --exclude='phpunit.xml.dist' \
  --exclude='package.json' \
  --exclude='package-lock.json' \
  --exclude='webpack.config.js' \
  --exclude='CLAUDE.md' \
  --exclude='ROADMAP.md' \
  --exclude='README.md' \
  --exclude='.phpunit.cache' \
  --exclude='.vscode' \
  --exclude='.DS_Store' \
  ./ "$STAGE/"

(cd dist && zip -qr "$(basename "$ZIP")" thewpfeeds)
rm -rf "$STAGE"

# Restore dev dependencies for local work.
composer install --quiet

echo "Built $ZIP ($(du -h "$ZIP" | cut -f1))"
