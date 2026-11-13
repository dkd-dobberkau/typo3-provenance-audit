<?php

declare(strict_types=1);

namespace CmsProvenance\ProvenanceAudit\Tests\Functional;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Configuration\SiteWriter;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class AuditEndpointWithoutSigningTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'cms-provenance/provenance',
        'cms-provenance/provenance-audit',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/AuditScenarioWithoutSigning.csv');
        $this->get(SiteWriter::class)->createNewBasicSite('main', 1, 'http://localhost/');
    }

    #[Test]
    public function listsEachDocumentAtItsLevelAndTheSiteAtTheLowest(): void
    {
        $response = $this->executeFrontendSubRequest(new InternalRequest('http://localhost/.well-known/content-provenance'));
        $listing = json_decode((string)$response->getBody(), false, 512, JSON_THROW_ON_ERROR);

        self::assertSame(200, $response->getStatusCode());
        // The page in the storage folder has a record without author, so it stays at L1.
        self::assertSame(1, $listing->cpsConformance);
        self::assertSame([2, 2, 1], array_column($listing->documents, 'cpsConformance'));
        self::assertFalse(isset($listing->site->keys));
        self::assertSame(3, $listing->totalItems);
        foreach ($listing->documents as $document) {
            self::assertFalse(isset($document->signature));
        }
    }
}
