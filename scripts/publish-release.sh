#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

FILE_VERSION="$(php -r "preg_match(\"/define\\('CLUBWORX_INTEGRATION_VERSION', '([^']+)'/\", file_get_contents('clubworx-integration.php'), \$m); echo \$m[1];")"
HEADER_VERSION="$(php -r "preg_match('/\\* Version: ([^\s]+)/', file_get_contents('clubworx-integration.php'), \$m); echo \$m[1];")"

if [[ -z "$FILE_VERSION" || -z "$HEADER_VERSION" ]]; then
  echo "Could not read version from clubworx-integration.php" >&2
  exit 1
fi

if [[ "$FILE_VERSION" != "$HEADER_VERSION" ]]; then
  echo "Version mismatch: header=${HEADER_VERSION}, constant=${FILE_VERSION}" >&2
  exit 1
fi

TAG="v${FILE_VERSION}"

if ! git diff --quiet || ! git diff --cached --quiet; then
  echo "Commit your changes before publishing a release." >&2
  exit 1
fi

echo "Publishing ${TAG}..."
git push origin HEAD

if git rev-parse "$TAG" >/dev/null 2>&1; then
  echo "Tag ${TAG} already exists locally."
  read -r -p "Delete and recreate it? [y/N] " CONFIRM
  if [[ "$CONFIRM" =~ ^[Yy]$ ]]; then
    git tag -d "$TAG"
    git push origin ":refs/tags/${TAG}" 2>/dev/null || true
  else
    echo "Aborted."
    exit 1
  fi
fi

git tag -a "$TAG" -m "Release ${TAG}"
git push origin "$TAG"

echo ""
echo "Tag ${TAG} pushed."
echo "GitHub Actions will build clubworx-integration-${FILE_VERSION}.zip and publish the release."
