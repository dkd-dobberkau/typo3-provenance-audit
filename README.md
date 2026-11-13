# Content Provenance: Audit

Well-known audit endpoint listing signed documents, public keys and facts.

TYPO3 reference implementation of **Layer 4** of the
[Content Provenance Specification](https://github.com/dkd-dobberkau/content-provenance-spec). Conformance level reached: **L4 Citable (with facts)**.

Optional extension. Requires the core extension [`provenance`](https://github.com/dkd-dobberkau/typo3-provenance).

> **Status: experimental, pre-release.** This repository currently contains the
> skeleton only (milestone M1 of the [PRD](https://github.com/dkd-dobberkau/cms-provenance.org/blob/main/docs/prd-typo3-extensions.md)). Nothing here is
> ready for production use.

## Scope

- Route `/.well-known/content-provenance` with RFC 8288 pagination
- Public key exposure as JWK or PEM
- Web Annotation endpoint for individual facts
- CLI: `provenance:audit:rebuild-index`

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
