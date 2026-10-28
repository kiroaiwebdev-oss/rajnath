#!/usr/bin/env bash
# Reproduces the portable development toolchain used to build this project in a
# Linux x86_64 workspace without root package installation.
# Usage: scripts/setup-dev-toolchain.sh [/path/to/tools]   (default: ../.tools)
set -euo pipefail
T="${1:-$(cd "$(dirname "$0")/../.." && pwd)/.tools}"
mkdir -p "$T"/{bin,lib,log,run,tmp,home,config,android-user}
cd "$T"

PHP_VER=8.4.23; MARIADB_VER=11.4.13; FLUTTER_VER=3.47.8; SWE_TAG=v2.10.03

# PHP (static build with gd, intl, pdo_mysql, sodium, bcmath, redis, ...)
[ -x bin/php ] || { curl -fsSL https://dl.static-php.dev/static-php-cli/bulk/php-${PHP_VER}-cli-linux-x86_64.tar.gz | tar xz -C bin; }
[ -f bin/composer.phar ] || curl -fsSL https://getcomposer.org/download/latest-stable/composer.phar -o bin/composer.phar
printf '#!/bin/sh\nexec %s/bin/php %s/bin/composer.phar "$@"\n' "$T" "$T" > bin/composer; chmod +x bin/composer

# MariaDB (+ libcrypt.so.1 compat library if the host lacks it)
if [ ! -d mariadb ]; then
  curl -fsSL https://archive.mariadb.org/mariadb-${MARIADB_VER}/bintar-linux-systemd-x86_64/mariadb-${MARIADB_VER}-linux-systemd-x86_64.tar.gz | tar xz
  mv mariadb-${MARIADB_VER}-linux-systemd-x86_64 mariadb
fi
if ! ldconfig -p | grep -q 'libcrypt.so.1 '; then
  echo "NOTE: host lacks libcrypt.so.1; copy it from the libxcrypt-compat package into $T/lib"
fi

# JDK 17, Android SDK, Flutter
[ -d jdk17 ] || { mkdir jdk17; curl -fsSL "https://api.adoptium.net/v3/binary/latest/17/ga/linux/x64/jdk/hotspot/normal/eclipse" | tar xz -C jdk17 --strip-components=1; }
if [ ! -d android-sdk/cmdline-tools/latest ]; then
  CT=$(curl -fsSL https://dl.google.com/android/repository/repository2-1.xml | grep -oE 'commandlinetools-linux-[0-9]+_latest\.zip' | sort -u | tail -1)
  curl -fsSL -o ct.zip "https://dl.google.com/android/repository/$CT"
  mkdir -p android-sdk/cmdline-tools && python3 -c "import zipfile;zipfile.ZipFile('ct.zip').extractall('android-sdk/cmdline-tools')"
  mv android-sdk/cmdline-tools/cmdline-tools android-sdk/cmdline-tools/latest; chmod +x android-sdk/cmdline-tools/latest/bin/*; rm ct.zip
fi
[ -d flutter ] || curl -fsSL https://storage.googleapis.com/flutter_infra_release/releases/stable/linux/flutter_linux_${FLUTTER_VER}-stable.tar.xz | tar xJ

# Swiss Ephemeris (AGPL-3.0) source + ephemeris files 1800-2400
if [ ! -x swisseph/swetest ]; then
  mkdir -p swisseph && curl -fsSL https://github.com/aloistr/swisseph/archive/refs/tags/${SWE_TAG}.tar.gz | tar xz -C swisseph --strip-components=1
  (cd swisseph && make -s libswe.a swetest)
  mkdir -p swisseph/ephe
  for f in sepl_18.se1 semo_18.se1 seas_18.se1; do curl -fsSL -o swisseph/ephe/$f https://raw.githubusercontent.com/aloistr/swisseph/master/ephe/$f; done
fi

cat > env.sh <<EOF
export TOOLS=$T JAVA_HOME=$T/jdk17 ANDROID_HOME=$T/android-sdk ANDROID_SDK_ROOT=$T/android-sdk
export PUB_CACHE=$T/pub-cache COMPOSER_HOME=$T/composer-home GRADLE_USER_HOME=$T/gradle-home
export XDG_CONFIG_HOME=$T/config ANDROID_USER_HOME=$T/android-user DART_CLI_HOME=$T/config HOME=$T/home
export FLUTTER_SUPPRESS_ANALYTICS=true
export PATH=$T/bin:$T/jdk17/bin:$T/flutter/bin:$T/android-sdk/cmdline-tools/latest/bin:$T/android-sdk/platform-tools:\$PATH
EOF
. ./env.sh
yes | sdkmanager --licenses >/dev/null 2>&1 || true
sdkmanager --install "platform-tools" "platforms;android-36" "build-tools;36.0.0" >/dev/null
echo "Toolchain ready. Run: . $T/env.sh"
