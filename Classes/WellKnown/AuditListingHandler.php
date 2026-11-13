<?php

declare(strict_types=1);

namespace CmsProvenance\ProvenanceAudit\WellKnown;

use CmsProvenance\Provenance\Domain\Repository\PublicPageRepository;
use CmsProvenance\Provenance\Service\AbsoluteUrlResolver;
use CmsProvenance\Provenance\WellKnown\WellKnownHandlerInterface;
use CmsProvenance\Provenance\WellKnown\WellKnownRouter;
use CmsProvenance\ProvenanceAudit\Domain\Repository\AuditDocumentRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Package\PackageManager;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\MathUtility;

/**
 * Audit Endpoint (specification Section 3.4) at /.well-known/content-provenance:
 * the site, its keys and a paginated list of its published documents, with
 * content hash and signature where the signing extension has signed a page.
 */
final readonly class AuditListingHandler implements WellKnownHandlerInterface
{
    private const SPEC_VERSION = '0.3';
    private const KEYS_PATH = WellKnownRouter::BASE_PATH . '/keys';
    private const DEFAULT_PAGE_SIZE = 100;
    private const MAX_PAGE_SIZE = 1000;
    private const DEFAULT_CACHE_TTL = 300;

    public function __construct(
        private AuditDocumentRepository $documentRepository,
        private ExtensionConfiguration $extensionConfiguration,
        private PackageManager $packageManager,
        private Typo3Version $typo3Version,
        private AbsoluteUrlResolver $urlResolver,
        private PublicPageRepository $publicPageRepository,
    ) {}

    public function getSubPath(): string
    {
        return '';
    }

    public function handle(ServerRequestInterface $request, string $remainder): ResponseInterface
    {
        $site = $request->getAttribute('site');
        if ($remainder !== '' || !$site instanceof Site) {
            return $this->respond(['error' => 'not found'], 404);
        }

        $pageSize = $this->pageSize();
        $requestedPage = $request->getQueryParams()['page'] ?? '1';
        $page = MathUtility::canBeInterpretedAsInteger($requestedPage) ? (int)$requestedPage : 0;

        $pageUids = $this->publicPageRepository->publicPageUids($site->getRootPageId(), time());
        $total = $this->documentRepository->countDocuments($pageUids);
        $lastPage = max(1, (int)ceil($total / $pageSize));
        if ($page < 1 || $page > $lastPage) {
            return $this->respond(['error' => 'page out of range'], 404);
        }

        $rows = $this->documentRepository->findDocuments($pageUids, ($page - 1) * $pageSize, $pageSize);
        $signedRecords = $this->documentRepository->findSignedRecords(array_map(static fn(array $row): int => (int)$row['page_uid'], $rows));
        $siteUrl = $this->urlResolver->siteUrl($site, $request);
        $signingAvailable = ExtensionManagementUtility::isLoaded('provenance_signing');
        $factsAvailable = ExtensionManagementUtility::isLoaded('provenance_facts');

        $listing = [
            '@context' => ['https://schema.org', 'https://cms-provenance.org/ns/v1'],
            'specVersion' => self::SPEC_VERSION,
            'generator' => $this->generator(),
            'cpsConformance' => $this->siteConformanceLevel($signingAvailable, $factsAvailable),
            'site' => array_filter([
                'url' => $siteUrl,
                'keys' => $signingAvailable ? rtrim($siteUrl, '/') . self::KEYS_PATH : null,
            ]),
            'totalItems' => $total,
            'page' => $page,
            'pageSize' => $pageSize,
            'documents' => array_map(
                fn(array $row): array => $this->entry($row, $site, $request, $signedRecords[(int)$row['page_uid']] ?? null),
                $rows
            ),
        ];

        return $this->respond($listing)->withHeader('Link', $this->links(rtrim($siteUrl, '/') . WellKnownRouter::BASE_PATH, $page, $lastPage));
    }

    /**
     * @return array<string, mixed>
     */
    private function entry(array $row, Site $site, ServerRequestInterface $request, ?\stdClass $signedRecord): array
    {
        $entry = [
            'url' => $this->urlResolver->pageUrl($site, (int)$row['page_uid'], $request),
            '@id' => (string)$row['document_uri'],
            'datePublished' => $this->dateTime((int)$row['first_published_at']),
            'lastSubstantiveRevision' => $this->dateTime((int)$row['last_substantive_revision_at']),
            'maintainedDate' => $this->dateTime((int)$row['last_maintained_at']),
            'cpsConformance' => (int)$row['conformance_level'],
        ];
        if ($signedRecord !== null && ($signedRecord->{'@id'} ?? null) === $row['document_uri']) {
            $entry['contentHash'] = $signedRecord->contentHash ?? null;
            $entry['signature'] = $signedRecord->signature ?? null;
            $entry['cpsConformance'] = (int)($signedRecord->cpsConformance ?? $entry['cpsConformance']);
        }

        return array_filter($entry, static fn(mixed $value): bool => $value !== null);
    }

    /**
     * Layer 1 always; Layers 2 and 4 with signing; Layer 3 with facts on top.
     */
    private function siteConformanceLevel(bool $signingAvailable, bool $factsAvailable): int
    {
        if (!$signingAvailable) {
            return 2;
        }

        return $factsAvailable ? 4 : 3;
    }

    private function links(string $endpoint, int $page, int $lastPage): string
    {
        $links = ['first' => 1, 'prev' => $page > 1 ? $page - 1 : null, 'next' => $page < $lastPage ? $page + 1 : null, 'last' => $lastPage];
        $headers = [];
        foreach ($links as $relation => $target) {
            if ($target !== null) {
                $headers[] = sprintf('<%s?page=%d>; rel="%s"', $endpoint, $target, $relation);
            }
        }

        return implode(', ', $headers);
    }

    private function respond(array $data, int $status = 200): ResponseInterface
    {
        return (new JsonResponse($data, $status, [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE))
            ->withHeader('Cache-Control', 'public, max-age=' . $this->setting('cacheTtl', self::DEFAULT_CACHE_TTL));
    }

    private function pageSize(): int
    {
        return min(self::MAX_PAGE_SIZE, max(1, $this->setting('paginationSize', self::DEFAULT_PAGE_SIZE)));
    }

    private function setting(string $name, int $default): int
    {
        try {
            $value = $this->extensionConfiguration->get('provenance_audit', $name);
        } catch (\Throwable) {
            return $default;
        }

        return MathUtility::canBeInterpretedAsInteger($value) ? (int)$value : $default;
    }

    private function generator(): string
    {
        $version = $this->packageManager->getPackage('provenance_audit')->getPackageMetaData()->getVersion();

        return sprintf('TYPO3 %s + provenance_audit %s', $this->typo3Version->getVersion(), $version);
    }

    private function dateTime(int $timestamp): ?string
    {
        return $timestamp > 0 ? gmdate('Y-m-d\TH:i:s\Z', $timestamp) : null;
    }
}
