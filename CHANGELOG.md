# Changelog

All notable changes to this project are documented in this file.
The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/).

## [Unreleased]

### Added

- Documentation pointing to the manual of the core extension (milestone M7).
- Audit endpoint `/.well-known/content-provenance` (milestone M5): site,
  keys link, specification version and a paginated list of published
  documents with timestamps, and with content hash and signature when the
  signing extension is installed.
- Repository skeleton: composer.json, ext_emconf.php, Services.yaml,
  extension icon, contribution guide (milestone M1).

### Changed

- Each document is listed with the level it reaches, and the listing claims
  the lowest of them, instead of a level derived from the installed
  extensions.
- The listing takes its public pages from `PublicPageRepository` of the core
  extension, the same rule the facts endpoint uses.

### Fixed

- The listing published relative URLs (site, keys, documents, `Link` header)
  when the site base was relative, such as `/`. They are now absolute,
  completed from the request.
