#!/usr/bin/env bash

# Copy project mu-plugins from .ddev/mu-plugins/ into WordPress.
# Runs as part of `ddev orchestrate`. Local development only.

SOURCE_DIR="/var/www/html/.ddev/mu-plugins"
TARGET_DIR="${WP_PATH}/wp-content/mu-plugins"

if [ ! -d "$SOURCE_DIR" ]; then
    echo "No mu-plugins source directory at ${SOURCE_DIR}. Skipping."
    # Fragments are normally sourced by `ddev orchestrate`; fall back to
    # `exit 0` if this script is ever executed directly.
    return 0 2>/dev/null || exit 0
fi

mkdir -p "$TARGET_DIR"

shopt -s nullglob
for file in "${SOURCE_DIR}"/*.php; do
    name="$(basename "$file")"
    cp -f "$file" "${TARGET_DIR}/${name}"
    echo "Installed mu-plugin: ${name}"
done
shopt -u nullglob
