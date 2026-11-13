<?php

declare(strict_types=1);

namespace CmsProvenance\ProvenanceAudit\Tests\Functional;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Configuration\SiteWriter;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * A site may use a relative base such as "/"; the listing must still
 * publish absolute URLs, taking scheme and host from the request.
 */
final class AuditEndpointWithRelativeBaseTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'cms-provenance/provenance',
        'cms-provenance/provenance-signing',
        'cms-provenance/provenance-audit',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/AuditScenario.csv');
        $this->get(SiteWriter::class)->createNewBasicSite('main', 1, '/');
    }

    #[Test]
    public function listingPublishesAbsoluteUrls(): void
    {
        $response = $this->executeFrontendSubRequest(new InternalRequest('http://localhost/.well-known/content-provenance'));
        self::assertSame(200, $response->getStatusCode(), substr((string)$response->getBody(), 0, 300));
        $listing = json_decode((string)$response->getBody(), false, 512, JSON_THROW_ON_ERROR);

        self::assertSame('http://localhost/', $listing->site->url);
        self::assertSame('http://localhost/.well-known/content-provenance/keys', $listing->site->keys);
        self::assertSame(
            ['http://localhost/', 'http://localhost/signed', 'http://localhost/storage/page'],
            array_column($listing->documents, 'url')
        );
        self::assertStringContainsString('<http://localhost/.well-known/content-provenance?page=1>; rel="first"', $response->getHeaderLine('Link'));
    }
}
