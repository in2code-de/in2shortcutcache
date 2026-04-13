<?php

declare(strict_types=1);

namespace In2code\In2shortcutcache\EventListener;

use In2code\In2shortcutcache\Domain\Service\In2frequentlyLifetimeCalculator;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\EndTimeRestriction;
use TYPO3\CMS\Core\Database\Query\Restriction\StartTimeRestriction;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Event\ModifyCacheLifetimeForPageEvent;

/**
 * Respect cache lifetime for shortcut elements that refers to a content
 * element with starttime/endtime
 */
class ShortcutCacheLifetimeEventListener
{
    private const TABLE = 'tt_content';
    private const CTYPE_SHORTCUT = 'shortcut';
    private const PREFIX_TT_CONTENT = 'tt_content_';

    public function __construct(
        private readonly ConnectionPool $connectionPool,
        private readonly In2frequentlyLifetimeCalculator $in2frequentlyCalculator,
    ) {
    }

    #[AsEventListener(identifier: 'in2shortcutcache/shortcut-cache-lifetime')]
    public function __invoke(ModifyCacheLifetimeForPageEvent $event): void
    {
        $referencedUids = $this->getReferencedUidsFromShortcuts($event->getPageId());
        if ($referencedUids !== []) {
            $minimumLifetime = $this->calculateMinimumLifetime($referencedUids, $event->getCacheLifetime());
            $minimumLifetime = $this->in2frequentlyCalculator->reduceLifetimeForUids($referencedUids, $minimumLifetime);
            if ($minimumLifetime < $event->getCacheLifetime()) {
                $event->setCacheLifetime($minimumLifetime);
            }
        }
    }

    protected function getReferencedUidsFromShortcuts(int $pageId): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE);
        $rows = $queryBuilder
            ->select('records')
            ->from(self::TABLE)
            ->where(
                $queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter($pageId, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('CType', $queryBuilder->createNamedParameter(self::CTYPE_SHORTCUT)),
                $queryBuilder->expr()->neq('records', $queryBuilder->createNamedParameter(''))
            )
            ->executeQuery()
            ->fetchAllAssociative();

        $uids = [];
        foreach ($rows as $row) {
            foreach ($this->parseRecordsField((string)$row['records']) as $uid) {
                $uids[] = $uid;
            }
        }

        return array_values(array_unique($uids));
    }

    /**
     * Parses the tt_content.records field which can contain:
     * - Plain integer UIDs: "123,456"
     * - Table-prefixed UIDs: "tt_content_123,tt_content_456"
     * - Mixed with other tables: "pages_123,tt_content_234" (non-tt_content entries are ignored)
     *
     * @return int[]
     */
    protected function parseRecordsField(string $records): array
    {
        $uids = [];
        foreach (GeneralUtility::trimExplode(',', $records, true) as $item) {
            if (is_numeric($item)) {
                $uids[] = (int)$item;
            } elseif (str_starts_with($item, self::PREFIX_TT_CONTENT)) {
                $uid = (int)substr($item, strlen(self::PREFIX_TT_CONTENT));
                if ($uid > 0) {
                    $uids[] = $uid;
                }
            }
        }

        return array_values(array_filter($uids, static fn (int $uid): bool => $uid > 0));
    }

    protected function calculateMinimumLifetime(array $uids, int $currentLifetime): int
    {
        $now = (int)($GLOBALS['ACCESS_TIME'] ?? time());

        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE);
        $queryBuilder->getRestrictions()
            ->removeByType(StartTimeRestriction::class)
            ->removeByType(EndTimeRestriction::class);

        $rows = $queryBuilder
            ->select('starttime', 'endtime')
            ->from(self::TABLE)
            ->where(
                $queryBuilder->expr()->in(
                    'uid',
                    $queryBuilder->createNamedParameter($uids, Connection::PARAM_INT_ARRAY)
                )
            )
            ->executeQuery()
            ->fetchAllAssociative();

        $minimumLifetime = $currentLifetime;
        foreach ($rows as $row) {
            $starttime = (int)$row['starttime'];
            $endtime = (int)$row['endtime'];

            if ($starttime > $now) {
                $minimumLifetime = min($minimumLifetime, $starttime - $now);
            }

            if ($endtime > $now) {
                $minimumLifetime = min($minimumLifetime, $endtime - $now);
            }
        }

        return $minimumLifetime;
    }
}
