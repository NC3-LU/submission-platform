#!/usr/bin/env bash
set -euo pipefail
image=${1:?Usage: publish-assets.sh IMAGE [PUBLIC_DIRECTORY]}
public_dir=${2:-public}
asset_tmp=$(mktemp -d)
asset_container=$(docker create --entrypoint /bin/true "$image")
trap 'docker rm -v "$asset_container" >/dev/null; rm -rf "$asset_tmp"' EXIT
docker cp "$asset_container:/var/www/html/public/." "$asset_tmp/"
docker run --rm --network none --entrypoint php "$image" -r '$m=json_decode(file_get_contents($argv[1]."/build/manifest.json"), true, 512, JSON_THROW_ON_ERROR); foreach($m as $e) { foreach(array_merge([$e["file"]],$e["css"]??[]) as $f) { if (!is_file($argv[1]."/build/".$f)) exit(1); }}' /var/www/html/public
# mktemp creates a 0700 directory; do not copy that mode onto the web root.
find "$asset_tmp" -type d -exec chmod 755 {} +
find "$asset_tmp" -type f -exec chmod 644 {} +
mkdir -p "$public_dir"
# Keep old hashed assets until after the rollback window; never overwrite public uploads.
tar -C "$asset_tmp" --exclude='./storage' --exclude='./hot' -cf - . | tar -C "$public_dir" -xf -
echo 'Published and verified assets from the release image.'
