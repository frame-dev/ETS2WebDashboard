#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

HOST="${HOST:-localhost}"
PORT="${PORT:-8000}"
PHP_VERSION="${PHP_VERSION:-8.3.29}"
PHP_SHA256="${PHP_SHA256:-8565fa8733c640b60da5ab4944bf2d4081f859915b39e29b3af26cf23443ed97}"

RUNTIME_DIR="$SCRIPT_DIR/.runtime"
PHP_ROOT="$RUNTIME_DIR/php"
PHP_BIN="$PHP_ROOT/bin/php"
SRC_ARCHIVE="$RUNTIME_DIR/php-$PHP_VERSION.tar.gz"
SRC_DIR="$RUNTIME_DIR/src/php-$PHP_VERSION"
PHP_URL="https://www.php.net/distributions/php-$PHP_VERSION.tar.gz"

need_cmd() {
    command -v "$1" >/dev/null 2>&1 || {
        echo "Missing required command: $1" >&2
        exit 1
    }
}

download_file() {
    local url="$1"
    local output="$2"

    if command -v curl >/dev/null 2>&1; then
        curl -fL "$url" -o "$output"
        return
    fi

    if command -v wget >/dev/null 2>&1; then
        wget -O "$output" "$url"
        return
    fi

    echo "Missing required command: curl or wget" >&2
    exit 1
}

verify_sha256() {
    local file="$1"
    local expected="$2"
    local actual=""

    if command -v sha256sum >/dev/null 2>&1; then
        actual="$(sha256sum "$file" | awk '{print $1}')"
    elif command -v shasum >/dev/null 2>&1; then
        actual="$(shasum -a 256 "$file" | awk '{print $1}')"
    else
        echo "Warning: sha256sum/shasum not found, skipping checksum verification." >&2
        return
    fi

    if [[ "$actual" != "$expected" ]]; then
        echo "Checksum verification failed for $file" >&2
        echo "Expected: $expected" >&2
        echo "Actual:   $actual" >&2
        exit 1
    fi
}

build_local_php() {
    need_cmd tar
    need_cmd make

    if ! command -v cc >/dev/null 2>&1 && ! command -v gcc >/dev/null 2>&1 && ! command -v clang >/dev/null 2>&1; then
        echo "A C compiler is required to build PHP locally." >&2
        echo "Install Xcode Command Line Tools on macOS or build-essential on Linux." >&2
        exit 1
    fi

    mkdir -p "$RUNTIME_DIR/src"

    echo "Downloading PHP $PHP_VERSION source..."
    download_file "$PHP_URL" "$SRC_ARCHIVE"
    verify_sha256 "$SRC_ARCHIVE" "$PHP_SHA256"

    rm -rf "$SRC_DIR"
    mkdir -p "$SRC_DIR"
    tar -xzf "$SRC_ARCHIVE" -C "$RUNTIME_DIR/src"

    echo "Building local PHP runtime in $PHP_ROOT ..."
    rm -rf "$PHP_ROOT"
    mkdir -p "$PHP_ROOT"

    (
        cd "$SRC_DIR"
        ./configure \
            --prefix="$PHP_ROOT" \
            --disable-all \
            --enable-cli
        make -j"$(getconf _NPROCESSORS_ONLN 2>/dev/null || echo 2)"
        make install
    )
}

if [[ ! -x "$PHP_BIN" ]]; then
    build_local_php
fi

if [[ ! -x "$PHP_BIN" ]]; then
    echo "Local PHP runtime was not created successfully." >&2
    exit 1
fi

echo "Starting ETS2 Web Dashboard on http://$HOST:$PORT/"
exec "$PHP_BIN" -S "$HOST:$PORT" router.php
