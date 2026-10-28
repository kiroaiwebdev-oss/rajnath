#!/usr/bin/env bash
# Builds engine/bin/arj-ephem against Swiss Ephemeris 2.10.03 (AGPL-3.0) and fetches the
# 1800–2400 CE ephemeris files. Usage: engine/build.sh [swisseph-source-dir]
set -euo pipefail
HERE="$(cd "$(dirname "$0")" && pwd)"
SRC="${1:-$HERE/swisseph}"
if [ ! -f "$SRC/swephexp.h" ]; then
  mkdir -p "$SRC"
  curl -fsSL https://github.com/aloistr/swisseph/archive/refs/tags/v2.10.03.tar.gz | tar xz -C "$SRC" --strip-components=1
fi
[ -f "$SRC/libswe.a" ] || make -s -C "$SRC" libswe.a
mkdir -p "$HERE/bin" "$HERE/ephe"
gcc -O2 -Wall -Wextra -o "$HERE/bin/arj-ephem" "$HERE/arj-ephem.c" -I"$SRC" "$SRC/libswe.a" -lm -ldl
for f in sepl_18.se1 semo_18.se1 seas_18.se1; do
  [ -f "$HERE/ephe/$f" ] || curl -fsSL -o "$HERE/ephe/$f" "https://raw.githubusercontent.com/aloistr/swisseph/master/ephe/$f"
done
echo "built $HERE/bin/arj-ephem"
