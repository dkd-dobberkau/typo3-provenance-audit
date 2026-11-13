<?php

declare(strict_types=1);

namespace CmsProvenance\ProvenanceAudit\Tests\Functional;

use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Configuration\SiteWriter;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class AuditEndpointTest extends FunctionalTestCase
{
    private const ENDPOINT = '/.well-known/content-provenance';

    protected array $testExtensionsToLoad = [
        'cms-provenance/provenance',
        'cms-provenance/provenance-signing',
        'cms-provenance/provenance-audit',
    ];

    protected array $configurationToUseInTestInstance = [
        'EXTENSIONS' => ['provenance_audit' => ['paginationSize' => '2']],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/AuditScenario.csv');
        $siteWriter = $this->get(SiteWriter::class);
        $siteWriter->createNewBasicSite('main', 1, 'http://localhost/');
        $siteWriter->createNewBasicSite('other', 10, 'http://other.localhost/');
    }

    #[Test]
    public function describesTheSiteAndItsSpecificationVersion(): void
    {
        $listing = $this->listing($this->request('http://localhost' . self::ENDPOINT));

        self::assertSame('0.3', $listing->specVersion);
        // The lowest level among the listed documents: two of them are complete but unsigned.
        self::assertSame(2, $listing->cpsConformance);
        self::assertSame('http://localhost/', $listing->site->url);
        self::assertSame('http://localhost/.well-known/content-provenance/keys', $listing->site->keys);
        self::assertStringStartsWith('TYPO3 ', $listing->generator);
        self::assertSame(3, $listing->totalItems);
    }

    #[Test]
    public function listsOnlyPublicDocumentsOfTheSiteInPageOrder(): void
    {
        $first = $this->listing($this->request('http://localhost' . self::ENDPOINT));
        $second = $this->listing($this->request('http://localhost' . self::ENDPOINT . '?page=2'));

        self::assertSame(
            ['http://localhost/', 'http://localhost/signed', 'http://localhost/storage/page'],
            array_column([...$first->documents, ...$second->documents], 'url')
        );
    }

    #[Test]
    public function entriesCarryTimestampsAndForSignedPagesHashAndSignature(): void
    {
        [$root, $signed] = $this->listing($this->request('http://localhost' . self::ENDPOINT))->documents;

        self::assertSame('urn:uuid:00000000-0000-4000-8000-000000000001', $root->{'@id'});
        self::assertSame('2026-05-14T09:00:00Z', $root->datePublished);
        self::assertSame('2026-05-14T10:00:00Z', $root->lastSubstantiveRevision);
        self::assertFalse(isset($root->signature));

        self::assertSame('sha256:aaaa', $signed->contentHash);
        self::assertSame('key-2026-11-13-abcdef', $signed->signature->keyId);
        self::assertSame('2026-11-13T10:00:00Z', $signed->maintainedDate);
        self::assertSame(3, $signed->cpsConformance);
    }

    #[Test]
    public function paginatesWithLinkHeaders(): void
    {
        $response = $this->request('http://localhost' . self::ENDPOINT);

        $link = $response->getHeaderLine('Link');
        self::assertStringContainsString('<http://localhost/.well-known/content-provenance?page=2>; rel="next"', $link);
        self::assertStringContainsString('<http://localhost/.well-known/content-provenance?page=2>; rel="last"', $link);
        self::assertStringContainsString('<http://localhost/.well-known/content-provenance?page=1>; rel="first"', $link);
        self::assertStringNotContainsString('rel="prev"', $link);
        self::assertSame('*', $response->getHeaderLine('Access-Control-Allow-Origin'));
        self::assertStringStartsWith('application/json', $response->getHeaderLine('Content-Type'));
    }

    #[Test]
    public function pageBeyondTheLastIsNotFound(): void
    {
        self::assertSame(404, $this->request('http://localhost' . self::ENDPOINT . '?page=3')->getStatusCode());
    }

    #[Test]
    public function eachSiteListsOnlyItsOwnDocuments(): void
    {
        $listing = $this->listing($this->request('http://other.localhost' . self::ENDPOINT));

        self::assertSame(['http://other.localhost/'], array_column($listing->documents, 'url'));
    }

    private function request(string $url): ResponseInterface
    {
        return $this->executeFrontendSubRequest(new InternalRequest($url));
    }

    private function listing(ResponseInterface $response): \stdClass
    {
        self::assertSame(200, $response->getStatusCode(), substr((string)$response->getBody(), 0, 300));

        return json_decode((string)$response->getBody(), false, 512, JSON_THROW_ON_ERROR);
    }
}
