<?php

declare(strict_types=1);

use CmsProvenance\ProvenanceAudit\Middleware\AuditEndpoint;

return [
    'frontend' => [
        'cms-provenance/audit-endpoint' => [
            'target' => AuditEndpoint::class,
            'after' => ['typo3/cms-frontend/site'],
            'before' => ['typo3/cms-frontend/base-redirect-resolver'],
        ],
    ],
];
