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
HOST_WORKDIR="${1:-}"
DOCKER_USER="${DOCKER_USER:-$(id -u):$(id -g)}"

if [[ -z "$HOST_WORKDIR" || ! -d "$HOST_WORKDIR" ]]; then
  HOST_WORKDIR="$(mktemp -d)"
  chmod 0700 "$HOST_WORKDIR"
  trap 'rm -rf "$HOST_WORKDIR"' EXIT
fi

EXPORT_DIR="$HOST_WORKDIR/revert-export"
PROOF_WORK="$HOST_WORKDIR/revert-proof-work"
mkdir -p "$EXPORT_DIR" "$PROOF_WORK"

tar -C "$REPO_ROOT" \
  --exclude='./.git' \
  --exclude='./vendor' \
  --exclude='./composer.lock' \
  --exclude='./.phpunit.cache' \
  --exclude='./.phpunit.result.cache' \
  -cf - . | tar -xf - -C "$EXPORT_DIR"

run_mutation_proof() {
  local name="$1"
  local expected_test="$2"
  local mutate_cmd="$3"
  local tree="$PROOF_WORK/$name"

  rm -rf "$tree"
  cp -a "$EXPORT_DIR/." "$tree/"
  (
    cd "$tree"
    eval "$mutate_cmd"
  )

  if [[ -d "$HOST_WORKDIR/src/vendor" ]]; then
    cp -a "$HOST_WORKDIR/src/vendor" "$tree/vendor"
  fi

  local stdout_file="$HOST_WORKDIR/revert-${name}.stdout"
  local stderr_file="$HOST_WORKDIR/revert-${name}.stderr"
  local junit_file="$HOST_WORKDIR/revert-${name}.xml"
  local exit_code=0

  if [[ -d "$tree/vendor" ]]; then
    "${DOCKER_CMD[@]}" run --rm \
      --tmpfs /tmp:mode=1777 \
      --network=none \
      -v "$tree:/work/src:ro" \
      -v "$HOST_WORKDIR:/work" \
      -w /work/src \
      "$PHP_IMAGE" \
      bash -lc "
        set -euo pipefail
        php -d error_reporting=-1 vendor/bin/phpunit --configuration phpunit.xml.dist --do-not-cache-result --colors=never \
          --filter '$expected_test' --log-junit /work/revert-${name}.xml
      " >"$stdout_file" 2>"$stderr_file" || exit_code=$?
  else
    "${DOCKER_CMD[@]}" run --rm \
      --tmpfs /tmp:mode=1777 \
      --network=default \
      -v "$tree:/work/src:rw" \
      -v "$HOST_WORKDIR:/work" \
      -e COMPOSER_HOME=/work/composer-home-revert \
      -w /work/src \
      "$PHP_IMAGE" \
      bash -lc "
        set -euo pipefail
        if grep -q debian /etc/os-release; then
          sed -i -e 's|deb.debian.org|archive.debian.org|g' -e 's|security.debian.org|archive.debian.org|g' /etc/apt/sources.list
          apt-get -o Acquire::Check-Valid-Until=false update -qq
        else
          apt-get update -qq
        fi
        DEBIAN_FRONTEND=noninteractive apt-get install -y -qq unzip
        mkdir -p /work/composer-home-revert
        if [[ ! -x /work/composer ]]; then
          curl -fsSL https://getcomposer.org/installer -o /work/composer-setup.php
          expected=\$(curl -fsSL https://getcomposer.org/installer.sig 2>/dev/null || true)
          if [[ -z \"\$expected\" ]]; then
            expected=\$(curl -fsSL https://composer.github.io/installer.sig)
          fi
          actual=\$(php -r \"echo hash_file('sha384', '/work/composer-setup.php');\")
          if [[ \"\$actual\" != \"\$(echo \"\$expected\" | tr -d '[:space:]')\" ]]; then
            echo \"Composer installer SHA-384 mismatch\" >&2
            exit 1
          fi
          php /work/composer-setup.php --install-dir=/work --filename=composer --version=2.11.1
          rm -f /work/composer-setup.php
        fi
        /work/composer install --no-interaction --prefer-dist --no-progress
        php -d error_reporting=-1 vendor/bin/phpunit --configuration phpunit.xml.dist --do-not-cache-result --colors=never \
          --filter '$expected_test' --log-junit /work/revert-${name}.xml
      " >"$stdout_file" 2>"$stderr_file" || exit_code=$?
  fi

  if grep -E '(Parse error:|Fatal error:)' "$stdout_file" "$stderr_file" >/dev/null 2>&1; then
    echo "Revert proof $name hit parse/fatal error (proof failure)" >&2
    cat "$stdout_file" >&2
    cat "$stderr_file" >&2
    exit 1
  fi

  if [[ "$exit_code" -eq 0 ]]; then
    echo "Revert proof $name passed PHPUnit but should have failed" >&2
    cat "$stdout_file" >&2
    exit 1
  fi

  if ! grep -Eq '(FAILURES!|ERRORS!|Failed asserting)' "$stdout_file"; then
    echo "Revert proof $name did not report PHPUnit failures/errors" >&2
    cat "$stdout_file" >&2
    exit 1
  fi

  echo "Revert proof $name OK (expected failure for $expected_test)"
}

run_mutation_proof remove-throwable-extends \
  'testExtendsOnlyThrowable' \
  "perl -0pi -e 's/interface CacheException extends \\\\Throwable/interface CacheException/' src/CacheException.php"

run_mutation_proof remove-invalid-extends-cache-exception \
  'testExtendsOnlyCacheException' \
  "perl -0pi -e 's/interface InvalidArgumentException extends CacheException/interface InvalidArgumentException/' src/InvalidArgumentException.php"

run_mutation_proof change-get-return-type \
  'testGetSignature' \
  "perl -0pi -e 's/public function get\\(string \\\$key, mixed \\\$default = null\\): mixed/public function get(string \\\$key, mixed \\\$default = null): string/' src/CacheInterface.php"

echo "All revert proofs passed."
