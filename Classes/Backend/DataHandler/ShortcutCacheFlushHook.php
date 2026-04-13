<?php

declare(strict_types=1);

namespace In2code\In2shortcutcache\Backend\DataHandler;

use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\DataHandling\DataHandler;

/**
 * Flushes page caches for all pages that contain shortcut elements referencing
 * a tt_content record that was just saved or deleted in the backend.
 */
final class ShortcutCacheFlushHook
{
    private const TABLE = 'tt_content';
    private const TABLE_REFINDEX = 'sys_refindex';
    private const CTYPE_SHORTCUT = 'shortcut';
    private const FIELD_RECORDS = 'records';
    private const EXTENSION_KEY = 'in2shortcutcache';
    private const RELEVANT_COMMANDS = ['delete', 'undelete'];

    public function __construct(
        private readonly ConnectionPool $connectionPool,
        private readonly ExtensionConfiguration $extensionConfiguration,
    ) {
    }

    /**
     * When editors change elements with starttime/endtime in backend. Commands: Edit, New
     */
    public function processDatamap_afterDatabaseOperations(
        string $status,
        string $table,
        int|string $id,
        array $fieldArray,
        DataHandler $dataHandler
    ): void {
        if ($table === self::TABLE && $status !== 'new' && $this->isEnabled()) {
            $this->flushShortcutPageCachesForUid((int)$id, $dataHandler);
        }
    }

    /**
     * When editors change elements with starttime/endtime in backend. Commands: Delete, hide, copy, move
     */
    public function processCmdmap_postProcess(
        string $command,
        string $table,
        int $id,
        mixed $value,
        DataHandler $dataHandler
    ): void {
        if ($table === self::TABLE && in_array($command, self::RELEVANT_COMMANDS, true) && $this->isEnabled()) {
            $this->flushShortcutPageCachesForUid($id, $dataHandler);
        }
    }

    private function isEnabled(): bool
    {
        $config = $this->extensionConfiguration->get(self::EXTENSION_KEY);
        return (bool)($config['enableDataHandlerCacheFlush'] ?? true);
    }

    private function flushShortcutPageCachesForUid(int $uid, DataHandler $dataHandler): void
    {
        $pageIds = $this->findPagesWithShortcutsReferencingUid($uid);
        foreach ($pageIds as $pageId) {
            $dataHandler->clear_cacheCmd((string)$pageId);
        }
    }

    private function findPagesWithShortcutsReferencingUid(int $uid): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE_REFINDEX);
        $rows = $queryBuilder
            ->select('tt.pid')
            ->from(self::TABLE_REFINDEX, 'sr')
            ->join('sr', self::TABLE, 'tt', 'tt.uid = sr.recuid')
            ->where(
                $queryBuilder->expr()->eq(
                    'sr.tablename',
                    $queryBuilder->createNamedParameter(self::TABLE)
                ),
                $queryBuilder->expr()->eq(
                    'sr.field',
                    $queryBuilder->createNamedParameter(self::FIELD_RECORDS)
                ),
                $queryBuilder->expr()->eq(
                    'sr.ref_table',
                    $queryBuilder->createNamedParameter(self::TABLE)
                ),
                $queryBuilder->expr()->eq(
                    'sr.ref_uid',
                    $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT)
                ),
                $queryBuilder->expr()->eq(
                    'tt.CType',
                    $queryBuilder->createNamedParameter(self::CTYPE_SHORTCUT)
                )
            )
            ->executeQuery()
            ->fetchAllAssociative();

        return array_values(array_unique(array_column($rows, 'pid')));
    }
}
