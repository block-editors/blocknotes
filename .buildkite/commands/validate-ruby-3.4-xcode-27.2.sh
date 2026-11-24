#!/usr/bin/env bash

set -euo pipefail

echo "--- :mag: Toolchain"
ruby_version=$(ruby -e 'print RUBY_VERSION')
xcode_version=$(xcodebuild -version | head -1)
echo "Ruby: $ruby_version"
echo "Xcode: $xcode_version"
[[ "$ruby_version" == "$(cat .ruby-version)" ]] || { echo "^^^ +++ Expected Ruby $(cat .ruby-version), got $ruby_version"; exit 1; }
[[ "$xcode_version" == "Xcode 27.2"* ]] || { echo "^^^ +++ Expected Xcode 27.2, got $xcode_version"; exit 1; }

echo "--- :rubygems: Setting up Ruby gems"
install_gems
bundle exec ruby -e 'require "abbrev"; puts "abbrev loads: #{Abbrev.abbrev(%w[ok]).inspect}"'

echo "--- :node: Installing NPM Dependencies and Syncing Project"
npm ci
npm run sync

echo "--- :hammer: Build signed iOS and Mac Catalyst archives (no upload)"
rm -rf build
bundle exec fastlane build_for_app_store

echo "--- :white_check_mark: Assert artifacts"
for artifact in build/blocknotes-ios.ipa build/blocknotes-mac-catalyst.pkg; do
  [[ -s "$artifact" ]] || { echo "^^^ +++ Missing or empty: $artifact"; exit 1; }
  ls -lh "$artifact"
done

unzip -q -o build/blocknotes-ios.ipa -d build/ipa
codesign --verify --deep --strict build/ipa/Payload/*.app
codesign -dvv build/ipa/Payload/*.app 2>&1 | grep -E '^(Authority|TeamIdentifier)='
pkgutil --check-signature build/blocknotes-mac-catalyst.pkg
