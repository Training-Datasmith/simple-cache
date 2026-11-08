#!/usr/bin/env bash
set -euo pipefail

if [[ -n "${DOCKER:-}" ]]; then
  # shellcheck disable=SC2206
  DOCKER_CMD=($DOCKER)
else
  DOCKER_CMD=(docker)
fi
PHP_IMAGE='php@sha256:0569e384b9064c04dec55dc6e41be41b494a878dfbb6577a7d76bd50cfd5bc00'
REPO_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
HOST_WORKDIR="$(mktemp -d)"
chmod 0700 "$HOST_WORKDIR"
DOCKER_USER="${DOCKER_USER:-$(id -u):$(id -g)}"
GIT_STATUS_BEFORE="$(mktemp)"
trap 'rm -rf "$HOST_WORKDIR"; rm -f "$GIT_STATUS_BEFORE"' EXIT
git -C "$REPO_ROOT" status --porcelain >"$GIT_STATUS_BEFORE"

DIAGNOSTIC_PATTERN='(Notice:|Warning:|Deprecated:|Strict Standards:|Parse error:|Fatal error:)'

run_install_container() {
  local network_mode="$1"
  shift
  "${DOCKER_CMD[@]}" run --rm \
    --tmpfs /tmp:mode=1777 \
    --network="$network_mode" \
    -v "$REPO_ROOT:/repo:ro" \
    -v "$HOST_WORKDIR:/work" \
    -e COMPOSER_HOME=/work/composer-home \
    -e DOCKER_CHOWN="$DOCKER_USER" \
    -w /work \
    "$PHP_IMAGE" \
    bash -lc "$*"
}

run_in_php_container() {
  local network_mode="$1"
  shift
  "${DOCKER_CMD[@]}" run --rm --read-only \
    --tmpfs /tmp:mode=1777 \
    --network="$network_mode" \
    --user "$DOCKER_USER" \
    -v "$HOST_WORKDIR:/work" \
    -e COMPOSER_HOME=/work/composer-home \
    -w /work/src \
    "$PHP_IMAGE" \
    bash -lc "$*"
}

assert_no_php_diagnostics() {
  local label="$1"
  local stdout_file="$2"
  local stderr_file="$3"

  if grep -E "$DIAGNOSTIC_PATTERN" "$stdout_file" "$stderr_file" >/dev/null 2>&1; then
    echo "PHP diagnostic emitted during $label" >&2
    cat "$stdout_file" >&2
    cat "$stderr_file" >&2
    exit 1
  fi
}

assert_phpunit_ok_summary() {
  local label="$1"
  local stdout_file="$2"
  local exit_code="$3"

  if [[ "$exit_code" -ne 0 ]]; then
    echo "PHPUnit failed for $label with exit $exit_code" >&2
    cat "$stdout_file" >&2
    exit "$exit_code"
  fi

  if grep -E '^(OK \(|Tests: ).*(skipped|incomplete|risky|warnings)' "$stdout_file" >/dev/null; then
    echo "PHPUnit summary for $label reports skipped/risky/incomplete/warnings" >&2
    cat "$stdout_file" >&2
    exit 1
  fi

  if ! grep -qE '^OK \(30 tests,' "$stdout_file"; then
    echo "PHPUnit summary for $label missing expected OK (30 tests,...)" >&2
    cat "$stdout_file" >&2
    exit 1
  fi
}

run_phpunit() {
  local label="$1"
  local extra_args="$2"
  local stdout_file="$HOST_WORKDIR/phpunit-${label}.stdout"
  local stderr_file="$HOST_WORKDIR/phpunit-${label}.stderr"
  local exit_code=0

  run_in_php_container none \
    "php -d error_reporting=-1 -d display_errors=1 -d display_startup_errors=1 \
      vendor/bin/phpunit --configuration phpunit.xml.dist --do-not-cache-result --colors=never \
      $extra_args" \
    >"$stdout_file" 2>"$stderr_file" || exit_code=$?

  assert_no_php_diagnostics "$label" "$stdout_file" "$stderr_file"
  assert_phpunit_ok_summary "$label" "$stdout_file" "$exit_code"
}

echo "Preparing work copy and installing dependencies..."
run_install_container default "
  set -euo pipefail
  rm -rf /work/src /work/composer-home
  mkdir -p /work/src /work/composer-home
  cp -a /repo/. /work/src/
  if grep -q debian /etc/os-release; then
    sed -i -e 's|deb.debian.org|archive.debian.org|g' -e 's|security.debian.org|archive.debian.org|g' /etc/apt/sources.list
    apt-get -o Acquire::Check-Valid-Until=false update -qq
  else
    apt-get update -qq
  fi
  DEBIAN_FRONTEND=noninteractive apt-get install -y -qq unzip
  php -r 'if (PHP_VERSION !== \"8.0.30\") { fwrite(STDERR, \"Unexpected PHP \" . PHP_VERSION . \"\\n\"); exit(1); }'
  for ext in dom filter json libxml mbstring xml xmlwriter; do
    php -m | grep -qx \"\$ext\" || { echo \"Missing ext \$ext\" >&2; exit 1; }
  done
  command -v unzip >/dev/null
  cd /work
  curl -fsSL https://getcomposer.org/installer -o composer-setup.php
  if ! expected=\$(curl -fsSL https://getcomposer.org/installer.sig 2>/dev/null || true); then
    expected=
  fi
  if [[ -z \"\$expected\" ]]; then
    expected=\$(curl -fsSL https://composer.github.io/installer.sig)
  fi
  actual=\$(php -r \"echo hash_file('sha384', 'composer-setup.php');\")
  if [[ \"\$actual\" != \"\$(echo \"\$expected\" | tr -d '[:space:]')\" ]]; then
    echo \"Composer installer SHA-384 mismatch\" >&2
    exit 1
  fi
  php composer-setup.php --install-dir=/work --filename=composer --version=2.11.1
  rm -f composer-setup.php
  /work/composer --version | grep -q '2.11.1'
  cd /work/src
  /work/composer install --no-interaction --prefer-dist --no-progress
  /work/composer audit --no-interaction
  /work/composer check-platform-reqs
  chown -R \"\${DOCKER_CHOWN}\" /work
"

run_phpunit default-order "--order-by=default"
run_phpunit random-order-20261108 "--order-by=random --random-order-seed=20261108"

echo "Running revert proofs..."
bash "$REPO_ROOT/tests/run-revert-proofs.sh" "$HOST_WORKDIR"

GIT_STATUS_AFTER="$(mktemp)"
git -C "$REPO_ROOT" status --porcelain >"$GIT_STATUS_AFTER"
if ! diff -q "$GIT_STATUS_BEFORE" "$GIT_STATUS_AFTER" >/dev/null 2>&1; then
  echo "Repository working tree changed during tests" >&2
  diff -u "$GIT_STATUS_BEFORE" "$GIT_STATUS_AFTER" >&2 || true
  rm -f "$GIT_STATUS_AFTER"
  exit 1
fi
rm -f "$GIT_STATUS_AFTER"

echo "All test runs passed."
