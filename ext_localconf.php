<?php

declare(strict_types=1);

use In2code\In2shortcutcache\Backend\DataHandler\ShortcutCacheFlushHook;

defined('TYPO3') || die();

/**
 * When editors change elements with starttime/endtime in backend. Commands: Edit, New
 */
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass'][]
    = ShortcutCacheFlushHook::class;

/**
 * When editors change elements with starttime/endtime in backend. Commands: Delete, hide, copy, move
 */
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processCmdmapClass'][]
    = ShortcutCacheFlushHook::class;
