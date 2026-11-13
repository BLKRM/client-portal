#!/usr/bin/env bash
# Build blkrm-portal-<version>.zip for upload (Plugins → Add New → Upload Plugin).
# Leaves out development-only files (tests, vendor, composer, phpunit, build tools).
set -euo pipefail
cd "$(dirname "$0")/../.."
VERSION=$(grep -m1 "Version:" blkrm-portal/blkrm-portal.php | awk '{print $NF}')
OUT="blkrm-portal/build/blkrm-portal-${VERSION}.zip"
mkdir -p blkrm-portal/build && rm -f "$OUT"
zip -qr "$OUT" blkrm-portal \
  -x 'blkrm-portal/build/*' 'blkrm-portal/vendor/*' 'blkrm-portal/tests/*' 'blkrm-portal/bin/*' \
     'blkrm-portal/composer.json' 'blkrm-portal/composer.lock' 'blkrm-portal/phpunit.xml.dist' \
     'blkrm-portal/.gitignore' 'blkrm-portal/.editorconfig' 'blkrm-portal/.phpunit.result.cache' '*.DS_Store'
echo "$OUT"
