#!/bin/sh
set -eu

project_directory="${1:-$(pwd -P)}"

if [ ! -d "$project_directory/public" ]; then
    echo "Refusing to change permissions: $project_directory/public does not exist." >&2
    exit 1
fi

# The host Apache serves public assets directly. rsync may copy a restrictive
# mode from its source checkout onto the destination root, preventing Apache
# from traversing to public/ even though the asset files themselves are 0644.
# Add only the traversal bit; do not expose a directory listing.
chmod o+x "$project_directory"
