#!/usr/bin/env bash
set -euo pipefail
umask 077

fail() {
    printf 'Deployment stopped: %s\n' "$*" >&2
    exit 1
}

[[ $# -eq 2 ]] || fail 'expected commit SHA and ZIP SHA-256'
commit="$1"
expected_zip_hash="$2"
[[ "$commit" =~ ^[0-9a-f]{40}$ ]] || fail 'invalid commit SHA'
[[ "$expected_zip_hash" =~ ^[0-9a-f]{64}$ ]] || fail 'invalid ZIP SHA-256'

home_dir="$(cd "$HOME" && pwd -P)"
deploy_root="$home_dir/simawars-deploy"
docroot="$home_dir/public_html/simawars.web.id"
archive="$deploy_root/inbox/$commit.zip"
release="$deploy_root/releases/$commit"
shared="$deploy_root/shared"
current="$deploy_root/current"

[[ -d "$docroot" && ! -L "$docroot" ]] || fail "domain document root is missing or is a symlink: $docroot"
[[ "$(cd "$docroot" && pwd -P)" == "$docroot" ]] || fail 'domain document root resolves elsewhere'
[[ -f "$archive" && ! -L "$archive" ]] || fail 'release ZIP is missing'
[[ -f "$shared/.env" && ! -L "$shared/.env" ]] || fail "configure $shared/.env before enabling automatic deployment"
[[ ! -e "$current" || -L "$current" ]] || fail 'current release path is not a symlink'

actual_zip_hash="$(sha256sum "$archive" | cut -d ' ' -f 1)"
[[ "$actual_zip_hash" == "$expected_zip_hash" ]] || fail 'release ZIP checksum mismatch'
command -v unzip >/dev/null 2>&1 || fail 'unzip is unavailable'
unzip -tq "$archive" >/dev/null || fail 'release ZIP is damaged'

while IFS= read -r entry; do
    [[ "$entry" == 'RELEASE.json' || "$entry" == simaster/* ]] || fail "unexpected archive entry: $entry"
    [[ "$entry" != /* && "$entry" != *'/../'* && "$entry" != ../* && "$entry" != *'/..' ]] ||
        fail "unsafe archive entry: $entry"
done < <(unzip -Z1 "$archive")

php_bin=''
for candidate in /opt/cpanel/ea-php82/root/usr/bin/php /opt/alt/php82/usr/bin/php "$(command -v php || true)"; do
    if [[ -n "$candidate" && -x "$candidate" ]] && "$candidate" -r 'exit(version_compare(PHP_VERSION, "8.2.0", ">=") ? 0 : 1);'; then
        php_bin="$candidate"
        break
    fi
done
[[ -n "$php_bin" ]] || fail 'PHP CLI 8.2 or newer is unavailable'

mkdir -p "$deploy_root/releases" "$shared"
if [[ -d "$release" && ! -L "$release" ]]; then
    [[ -f "$release/RELEASE.json" && -f "$release/vendor/autoload.php" ]] ||
        fail 'existing release is incomplete'
    [[ "$(readlink "$release/.env")" == "$shared/.env" ]] || fail 'existing release uses a different .env'
    [[ "$(readlink "$release/storage")" == "$shared/storage" ]] || fail 'existing release uses different storage'
else
    [[ ! -e "$release" ]] || fail 'release path exists but is not a regular directory'
    incoming="$(mktemp -d "$deploy_root/releases/.incoming-$commit-XXXXXXXX")"
    cleanup() {
        if [[ -n "${incoming:-}" && -d "$incoming" ]]; then
            rm -r -- "$incoming"
        fi
    }
    trap cleanup EXIT
    unzip -q "$archive" -d "$incoming"
    app="$incoming/simaster"
    [[ -f "$app/artisan" && -f "$app/composer.lock" && -f "$app/vendor/autoload.php" ]] ||
        fail 'release is missing Laravel files or production dependencies'
    [[ -f "$app/public/build/manifest.json" ]] || fail 'release has no Vite build'
    [[ ! -e "$app/.env" ]] || fail 'release unexpectedly contains .env'

    if [[ ! -d "$shared/storage" ]]; then
        mv -- "$app/storage" "$shared/storage"
    else
        [[ -d "$app/storage" && ! -L "$app/storage" ]] || fail 'release storage layout is invalid'
        [[ -z "$(find "$app/storage" -type f -print -quit)" ]] || fail 'release unexpectedly contains storage files'
        find "$app/storage" -depth -type d -exec rmdir -- {} \;
    fi
    mkdir -p "$shared/storage/app/private" "$shared/storage/app/public" \
        "$shared/storage/framework/cache/data" "$shared/storage/framework/sessions" \
        "$shared/storage/framework/views" "$shared/storage/logs"
    ln -s "$shared/storage" "$app/storage"
    ln -s "$shared/.env" "$app/.env"

    mv -- "$app" "$release"
    mv -- "$incoming/RELEASE.json" "$release/RELEASE.json"
    rmdir -- "$incoming"
    incoming=''
fi

[[ -f "$release/artisan" && -f "$release/public/build/manifest.json" ]] ||
    fail 'release is missing Laravel or Vite files'
package_commit="$("$php_bin" -r '$r=json_decode(file_get_contents($argv[1]),true,512,JSON_THROW_ON_ERROR); echo $r["source_commit"] ?? "";' "$release/RELEASE.json")"
[[ "$package_commit" == "$commit" ]] || fail 'package commit does not match GitHub push'
(
    cd "$release"
    "$php_bin" deploy/cpanel/preflight.php
    "$php_bin" artisan optimize
    "$php_bin" artisan filament:optimize
)

while IFS= read -r -d '' source_file; do
    relative="${source_file#"$release/public/"}"
    [[ "$relative" != 'index.php' && "$relative" != .well-known/* ]] || continue
    destination="$docroot/$relative"
    mkdir -p "$(dirname "$destination")"
    install -m 0644 -- "$source_file" "$destination"
done < <(find "$release/public" -type f -print0)

index_next="$docroot/.index-next-$commit"
install -m 0644 -- "$release/deploy/cpanel/simawars-index.php" "$index_next"
link_next="$deploy_root/.current-next-$commit"
ln -s "$release" "$link_next"
mv -Tf -- "$link_next" "$current"
mv -f -- "$index_next" "$docroot/index.php"

rm -f -- "$archive"
printf 'Activated SIMAWARS release %s at %s\n' "$commit" "$current"
