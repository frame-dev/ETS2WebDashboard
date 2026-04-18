#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

HOST="${HOST:-0.0.0.0}"
PORT="${PORT:-8000}"

RUNTIME_DIR="$SCRIPT_DIR/.runtime"
LOCAL_PHP_BIN="$RUNTIME_DIR/php/bin/php"
CONFIG_LOCAL="$SCRIPT_DIR/config.local.php"
CONFIG_LOCAL_EXAMPLE="$SCRIPT_DIR/config.local.example.php"

mkdir -p "$RUNTIME_DIR" "$SCRIPT_DIR/tmp" "$SCRIPT_DIR/snapshots"

print_install_help() {
    cat >&2 <<'EOF'
No suitable PHP runtime was found for this project.

This dashboard needs:
- PHP 8.0 or newer
- HTTPS stream support (`https` wrapper, usually from OpenSSL)
- `curl` extension enabled

Common install commands:
- macOS (Homebrew): brew install php
- Ubuntu/Debian: sudo apt install php-cli php-curl php-xml php-mbstring openssl
- Fedora: sudo dnf install php-cli php-curl php-xml php-mbstring openssl

You can also point the launcher at a specific binary:
- PHP_BIN=/path/to/php ./run.sh
EOF
}

php_meets_requirements() {
    local php_bin="$1"

    "$php_bin" -r '
        if (PHP_VERSION_ID < 80000) {
            fwrite(STDERR, "PHP 8.0+ is required.\n");
            exit(1);
        }

        if (!function_exists("curl_init")) {
            fwrite(STDERR, "Missing required PHP curl extension.\n");
            exit(1);
        }

        if (!in_array("https", stream_get_wrappers(), true)) {
            fwrite(STDERR, "Missing HTTPS stream wrapper (usually OpenSSL).\n");
            exit(1);
        }

        exit(0);
    ' >/dev/null
}

discover_php() {
    if [[ -n "${PHP_BIN:-}" && -x "${PHP_BIN:-}" ]]; then
        printf '%s\n' "$PHP_BIN"
        return
    fi

    if [[ -x "$LOCAL_PHP_BIN" ]]; then
        printf '%s\n' "$LOCAL_PHP_BIN"
        return
    fi

    if command -v php >/dev/null 2>&1; then
        command -v php
        return
    fi

    printf '%s\n' ""
}

PHP_CMD="$(discover_php)"

if [[ -z "$PHP_CMD" ]]; then
    print_install_help
    exit 1
fi

if ! php_meets_requirements "$PHP_CMD"; then
    cat >&2 <<EOF
The PHP runtime at:
  $PHP_CMD

does not meet this project's requirements.
EOF
    print_install_help
    exit 1
fi

if [[ ! -f "$CONFIG_LOCAL" && -f "$CONFIG_LOCAL_EXAMPLE" ]]; then
    cp "$CONFIG_LOCAL_EXAMPLE" "$CONFIG_LOCAL"
    echo "Created config.local.php from config.local.example.php"
fi

echo "Using PHP runtime: $PHP_CMD"
echo "Starting ETS2 Web Dashboard on http://$HOST:$PORT/"
exec "$PHP_CMD" -S "$HOST:$PORT" router.php
