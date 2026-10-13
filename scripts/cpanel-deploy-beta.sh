#!/usr/bin/env bash
set -euo pipefail
umask 077

fail() { printf 'SIMAWAR beta deployment stopped: %s\n' "$*" >&2; exit 1; }

domain=beta.simawar.web.id
repo="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd -P)"
expected="$(cd "$HOME" && pwd -P)/beta.simawar.web.id"
[[ "$repo" == "$expected" ]] || fail "expected checkout at $expected; found $repo"
[[ -f "$repo/public/index.php" && -f "$repo/public/.htaccess" ]] || fail 'Laravel public entrypoint is missing'
[[ -z "$(git -C "$repo" status --porcelain --untracked-files=no)" ]] || fail 'tracked Git files have changed on the server'
sha="$(git -C "$repo" rev-parse HEAD)"
[[ "$sha" =~ ^[0-9a-f]{40}$ ]] || fail 'invalid Git commit'

php=''
for candidate in /opt/cpanel/ea-php82/root/usr/bin/php /opt/alt/php82/usr/bin/php "$(command -v php || true)"; do
    if [[ -n "$candidate" && -x "$candidate" ]] && "$candidate" -r 'exit(version_compare(PHP_VERSION,"8.2.0",">=") ? 0 : 1);'; then
        php="$candidate"
        break
    fi
done
[[ -n "$php" ]] || fail 'PHP CLI 8.2 or newer is unavailable'
for program in curl unzip sha256sum; do command -v "$program" >/dev/null || fail "$program is unavailable"; done

# A temporary, unpredictable file proves that the domain serves this public
# directory and runs PHP 8.2+. It is removed before downloading anything.
probe="$(mktemp "$repo/public/php-check-XXXXXXXX.php")"
stage=''
cleanup() {
    [[ -z "${probe:-}" || ! -f "$probe" ]] || rm -f -- "$probe"
    if [[ -n "${stage:-}" && -d "$stage" && -n "${deploy_root:-}" && "$stage" == "$deploy_root"/.incoming-"$sha"-* ]]; then
        rm -r -- "$stage"
    fi
}
trap cleanup EXIT
printf '%s\n' '<?php echo "SIMAWAR_PHP_", PHP_VERSION_ID;' > "$probe"
chmod 0644 "$probe"
web_version="$(curl -fsSL --proto '=https' --proto-redir '=https' --connect-timeout 10 --max-time 25 "https://$domain/$(basename "$probe")")" ||
    fail "cannot run PHP through https://$domain; check DNS, HTTPS and Document Root"
rm -f -- "$probe"
probe=''
[[ "$web_version" =~ ^SIMAWAR_PHP_([0-9]+)$ ]] || fail 'the web server did not execute PHP in this public directory'
[[ "${BASH_REMATCH[1]}" -ge 80200 ]] || fail "web PHP is ${BASH_REMATCH[1]}; hosting must set $domain to PHP 8.2+"

[[ -f "$repo/.env" && ! -L "$repo/.env" ]] || fail "create a private .env in $repo"
[[ -d "$repo/storage" && ! -L "$repo/storage" ]] || fail 'persistent storage directory is missing'
deploy_root="$HOME/simawar-beta-deploy"
mkdir -p "$deploy_root/releases"
chmod 0700 "$deploy_root" "$deploy_root/releases"
release="$deploy_root/releases/$sha"
asset="simawar-beta-$sha.zip"

if [[ ! -d "$release" ]]; then
    [[ ! -e "$release" && ! -L "$release" ]] || fail 'release path is not a regular directory'
    stage="$(mktemp -d "$deploy_root/.incoming-$sha-XXXXXXXX")"
    url="https://github.com/Marsellns/AUTOMATION-CHARGE/releases/download/beta-$sha/$asset"
    curl -fsSL --proto '=https' --proto-redir '=https' --retry 2 --connect-timeout 10 --max-time 300 -o "$stage/$asset" "$url" ||
        fail "GitHub build package for $sha is unavailable; wait for its beta workflow"
    curl -fsSL --proto '=https' --proto-redir '=https' --retry 2 --connect-timeout 10 --max-time 30 -o "$stage/$asset.sha256" "$url.sha256" ||
        fail 'GitHub build checksum is unavailable'
    expected_hash="$(awk 'NR == 1 {print $1}' "$stage/$asset.sha256")"
    [[ "$expected_hash" =~ ^[0-9a-f]{64}$ ]] || fail 'invalid release checksum'
    actual_hash="$(sha256sum "$stage/$asset" | awk '{print $1}')"
    [[ "$actual_hash" == "$expected_hash" ]] || fail 'release checksum mismatch'
    unzip -tq "$stage/$asset" >/dev/null || fail 'release ZIP is damaged'
    while IFS= read -r entry; do
        [[ "$entry" == RELEASE.json || "$entry" == simaster/* ]] || fail "unexpected ZIP entry: $entry"
        [[ "$entry" != /* && "$entry" != *'..'* && "$entry" != *'\\'* ]] || fail "unsafe ZIP entry: $entry"
    done < <(unzip -Z1 "$stage/$asset")
    mkdir "$stage/extracted"
    unzip -q "$stage/$asset" -d "$stage/extracted"
    [[ -z "$(find "$stage/extracted" -type l -print -quit)" ]] || fail 'release ZIP contains symbolic links'
    app="$stage/extracted/simaster"
    [[ -f "$app/artisan" && -f "$app/vendor/autoload.php" && -f "$app/public/build/manifest.json" ]] ||
        fail 'release lacks Laravel dependencies or built assets'
    [[ ! -e "$app/.env" ]] || fail 'release unexpectedly contains .env'
    "$php" -r '
        $m=json_decode(file_get_contents($argv[1]),true,512,JSON_THROW_ON_ERROR);
        exit(($m["source_commit"] ?? null)===$argv[2]
          && ($m["composer_lock_sha256"] ?? null)===hash_file("sha256",$argv[3])
          && ($m["vite_manifest_sha256"] ?? null)===hash_file("sha256",$argv[4])
          && ($m["includes_credentials"] ?? true)===false ? 0 : 1);
    ' "$stage/extracted/RELEASE.json" "$sha" "$repo/composer.lock" "$app/public/build/manifest.json" ||
        fail 'release metadata differs from the checked-out source'
    [[ -z "$(find "$app/storage" -type f -print -quit)" ]] || fail 'release contains storage data'
    find "$app/storage" -depth -type d -exec rmdir -- {} \;
    ln -s "$repo/storage" "$app/storage"
    ln -s "$repo/.env" "$app/.env"
    mv -- "$app" "$release"
    mv -- "$stage/extracted/RELEASE.json" "$release/RELEASE.json"
fi

[[ -f "$release/RELEASE.json" && -f "$release/vendor/autoload.php" && -f "$release/public/build/manifest.json" ]] ||
    fail 'saved release is incomplete'
[[ "$(readlink "$release/.env")" == "$repo/.env" && "$(readlink "$release/storage")" == "$repo/storage" ]] ||
    fail 'saved release uses different persistent configuration'
"$php" -r '$m=json_decode(file_get_contents($argv[1]),true,512,JSON_THROW_ON_ERROR); exit(($m["source_commit"] ?? "")===$argv[2] ? 0 : 1);' \
    "$release/RELEASE.json" "$sha" || fail 'saved release belongs to another commit'
(
    cd "$release"
    "$php" deploy/cpanel/preflight.php "https://$domain"
    "$php" artisan optimize
    "$php" artisan filament:optimize
)

# Only Git-ignored generated paths point to the private release. One atomic
# switch of current changes the vendor and assets together.
runtime=(vendor public/build public/css/filament public/js/filament public/fonts/filament public/vendor/simaster)
for relative in "${runtime[@]}"; do
    [[ -d "$release/$relative" ]] || fail "release is missing $relative"
    target="$repo/$relative"
    link="$deploy_root/current/$relative"
    if [[ -L "$target" ]]; then
        [[ "$(readlink "$target")" == "$link" ]] || fail "unexpected symlink at $target"
    elif [[ -e "$target" ]]; then
        fail "review the existing $target before deploying generated files"
    else
        mkdir -p "$(dirname "$target")"
        ln -s "$link" "$target"
    fi
done

[[ ! -e "$deploy_root/current" || -L "$deploy_root/current" ]] || fail 'current release is not a symlink'
next="$deploy_root/.current-next-$sha-$$"
ln -s "$release" "$next"
mv -Tf -- "$next" "$deploy_root/current"
(
    cd "$repo"
    "$php" artisan optimize:clear
    "$php" artisan filament:optimize-clear
)
[[ -z "$(git -C "$repo" status --porcelain --untracked-files=no)" ]] || fail 'deployment changed tracked Git files'
printf 'SIMAWAR beta activated commit %s\n' "$sha"
