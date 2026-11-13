<?php

declare(strict_types=1);

namespace CmsProvenance\ProvenanceAudit\Domain\Repository;

use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

/**
 * Finds the provenance records and signatures of the pages an audit listing
 * may show; which pages are public decides PublicPageRepository of the core.
 */
final readonly class AuditDocumentRepository
{
    private const SIGNED_RECORD_TABLE = 'tx_provenancesigning_record';

    public function __construct(
        private ConnectionPool $connectionPool,
    ) {}

    /**
     * @param list<int> $pageUids
     * @return list<array<string, mixed>> page uid and provenance record fields, ordered by page uid
     */
    public function findDocuments(array $pageUids, int $offset, int $limit): array
    {
        if ($pageUids === []) {
            return [];
        }

        return $this->documentQuery($pageUids)
            ->select('p.uid AS page_uid', 'd.document_uri', 'd.first_published_at', 'd.last_substantive_revision_at', 'd.last_maintained_at')
            ->orderBy('p.uid')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->executeQuery()
            ->fetchAllAssociative();
    }

    /**
     * @param list<int> $pageUids
     */
    public function countDocuments(array $pageUids): int
    {
        if ($pageUids === []) {
            return 0;
        }

        return (int)$this->documentQuery($pageUids)->count('p.uid')->executeQuery()->fetchOne();
    }

    /**
     * Latest signed records from the signing extension, if it is installed.
     *
     * @param list<int> $pageUids
     * @return array<int, \stdClass> signed record by page uid
     */
    public function findSignedRecords(array $pageUids): array
    {
        if ($pageUids === [] || !ExtensionManagementUtility::isLoaded('provenance_signing')) {
            return [];
        }

        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::SIGNED_RECORD_TABLE);
        $rows = $queryBuilder
            ->select('page_uid', 'signed_record')
            ->from(self::SIGNED_RECORD_TABLE)
            ->where(
                $queryBuilder->expr()->in('page_uid', $queryBuilder->createNamedParameter($pageUids, Connection::PARAM_INT_ARRAY)),
                $queryBuilder->expr()->eq('language_uid', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT))
            )
            ->executeQuery()
            ->fetchAllAssociative();

        $signed = [];
        foreach ($rows as $row) {
            $record = json_decode((string)$row['signed_record'], false);
            if ($record instanceof \stdClass) {
                $signed[(int)$row['page_uid']] = $record;
            }
        }

        return $signed;
    }

    /**
     * @param list<int> $pageUids
     */
    private function documentQuery(array $pageUids): \TYPO3\CMS\Core\Database\Query\QueryBuilder
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('pages');
        $queryBuilder->getRestrictions()->removeAll();

        return $queryBuilder
            ->from('pages', 'p')
            ->join('p', 'tx_provenance_document', 'd', (string)$queryBuilder->expr()->and(
                $queryBuilder->expr()->eq('d.uid', $queryBuilder->quoteIdentifier('p.provenance_document_uid')),
                $queryBuilder->expr()->eq('d.deleted', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('d.hidden', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('d.t3ver_wsid', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT))
            ))
            ->where(
                $queryBuilder->expr()->in('p.uid', $queryBuilder->createNamedParameter($pageUids, Connection::PARAM_INT_ARRAY))
            );
    }
}
