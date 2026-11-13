# Content Provenance: Audit

Well-known audit endpoint listing signed documents, public keys and facts.

TYPO3 reference implementation of **Layer 4** of the
[Content Provenance Specification](https://github.com/dkd-dobberkau/content-provenance-spec). Conformance level reached: **L4 Citable (with facts)**.

Optional extension. Requires the core extension [`provenance`](https://github.com/dkd-dobberkau/typo3-provenance).

> **Status: experimental, pre-release.** Milestone M5 of the
> [PRD](https://github.com/dkd-dobberkau/cms-provenance.org/blob/main/docs/prd-typo3-extensions.md)
> is in place. Nothing here is ready for production use.

## What it publishes

`GET /.well-known/content-provenance` on each site returns:

```json
{
  "@context": ["https://schema.org", "https://cms-provenance.org/ns/v1"],
  "specVersion": "0.3",
  "generator": "TYPO3 14.3.11 + provenance_audit 0.1.0",
  "cpsConformance": 3,
  "site": {
    "url": "https://example.org/",
    "keys": "https://example.org/.well-known/content-provenance/keys"
  },
  "totalItems": 1240,
  "page": 1,
  "pageSize": 100,
  "documents": [
    {
      "url": "https://example.org/about",
      "@id": "urn:uuid:d02f539f-07c2-482a-bef4-435f91895da9",
      "datePublished": "2026-05-14T09:00:00Z",
      "lastSubstantiveRevision": "2026-05-14T10:00:00Z",
      "cpsConformance": 3,
      "contentHash": "sha256:f049…",
      "signature": { "algorithm": "Ed25519", "keyId": "key-2026-11-13-a259e7", "signatureValue": "…" }
    }
  ]
}
```

- Further pages via `?page=N`; `Link` headers (RFC 8288) carry `first`,
  `prev`, `next` and `last`.
- Listed are standard pages in the default language with a visible provenance
  record. Hidden, scheduled and access-restricted pages are left out together
  with their subpages.
- `contentHash` and `signature` appear when the
  [signing extension](https://github.com/dkd-dobberkau/typo3-provenance-signing)
  is installed and the page has been rendered since the key was created. They
  reflect the last rendering; a verifier fetches the page and checks it.
- CORS is open (`Access-Control-Allow-Origin: *`), responses are cacheable
  for `cacheTtl` seconds.

Settings (extension configuration): `paginationSize` (default 100, maximum
1000) and `cacheTtl` (default 300).

## Requirements

- TYPO3 13.4 LTS or 14
- PHP 8.2 or later

## Installation

Not yet released. Once a first version is tagged:

```bash
composer require cms-provenance/provenance-audit
```

Extension key: [`provenance_audit`](https://extensions.typo3.org/extension/provenance_audit)

## Links

- Project site: <https://cms-provenance.org>
- Specification: <https://github.com/dkd-dobberkau/content-provenance-spec>
- Product requirements (PRD): <https://github.com/dkd-dobberkau/cms-provenance.org/blob/main/docs/prd-typo3-extensions.md>
- Conformance test suite: <https://github.com/dkd-dobberkau/content-provenance-conformance>

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md).

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
