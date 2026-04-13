<?php

declare(strict_types=1);

namespace In2code\In2shortcutcache\Domain\Service;

use In2code\In2frequently\Domain\Service\ExpressionResolver;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;

class In2frequentlyLifetimeCalculator
{
    private const TABLE = 'tt_content';
    private const FIELD_ACTIVE = 'tx_in2frequently_active';
    private const FIELD_STARTTIME = 'tx_in2frequently_starttime';
    private const FIELD_ENDTIME = 'tx_in2frequently_endtime';

    public function __construct(
        private readonly ConnectionPool $connectionPool,
    ) {
    }

    public function reduceLifetimeForUids(array $uids, int $currentLifetime): int
    {
        if (class_exists(ExpressionResolver::class) === false) {
            return $currentLifetime;
        }

        $rows = $this->fetchIn2frequentlyRows($uids);
        if ($rows === []) {
            return $currentLifetime;
        }

        $resolver = new ExpressionResolver();
        $now = (int)($GLOBALS['ACCESS_TIME'] ?? time());
        $minimumLifetime = $currentLifetime;

        foreach ($rows as $row) {
            foreach ([self::FIELD_STARTTIME, self::FIELD_ENDTIME] as $field) {
                $expression = (string)$row[$field];
                if ($expression === '') {
                    continue;
                }
                try {
                    $nextChange = $resolver->resolveUpcomingDate($expression)->getTimestamp();
                    if ($nextChange > $now) {
                        $minimumLifetime = min($minimumLifetime, $nextChange - $now);
                    }
                } catch (\Throwable) {
                    // Invalid cron expression – skip silently
                }
            }
        }

        return $minimumLifetime;
    }

    protected function fetchIn2frequentlyRows(array $uids): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE);
        return $queryBuilder
            ->select(self::FIELD_STARTTIME, self::FIELD_ENDTIME)
            ->from(self::TABLE)
            ->where(
                $queryBuilder->expr()->in(
                    'uid',
                    $queryBuilder->createNamedParameter($uids, Connection::PARAM_INT_ARRAY)
                ),
                $queryBuilder->expr()->eq(
                    self::FIELD_ACTIVE,
                    $queryBuilder->createNamedParameter(1, Connection::PARAM_INT)
                )
            )
            ->executeQuery()
            ->fetchAllAssociative();
    }
}
