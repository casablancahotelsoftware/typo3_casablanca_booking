<?php

declare(strict_types=1);

/**
 * Demo setup: room detail page, plugins, flexforms.
 * Run: docker exec typo3_web_1 php /app/packages/casablanca_booking/Scripts/setup-demo-room-pages.php
 */

use TYPO3\CMS\Core\Core\Bootstrap;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;

(static function (): void {
    $classLoader = require '/app/vendor/autoload.php';
    SystemEnvironmentBuilder::run(0, SystemEnvironmentBuilder::REQUESTTYPE_CLI);
    Bootstrap::init($classLoader);

    $pages = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable('pages');
    $content = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable('tt_content');
    $now = time();

    $detailPageUid = (int)($pages->fetchOne(
        'SELECT uid FROM pages WHERE deleted = 0 AND pid = 1 AND title = ? LIMIT 1',
        ['Room detail']
    ) ?: 0);

    if ($detailPageUid <= 0) {
        $pages->insert('pages', [
            'pid' => 1,
            'tstamp' => $now,
            'crdate' => $now,
            'title' => 'Room detail',
            'slug' => '/room-detail',
            'doktype' => 1,
        ]);
        $detailPageUid = (int)$pages->lastInsertId();
    }

    if ($detailPageUid <= 0) {
        fwrite(STDERR, "Failed to create detail page.\n");
        exit(1);
    }

    $detailFlexform = <<<'XML'
<?xml version="1.0" encoding="utf-8" standalone="yes" ?>
<T3FlexForms>
    <data>
        <sheet index="sDisplay">
            <language index="lDEF">
                <field index="settings.displayMode">
                    <value index="vDEF">detail</value>
                </field>
            </language>
        </sheet>
    </data>
</T3FlexForms>
XML;

    $detailContentUid = (int)($content->fetchOne(
        'SELECT uid FROM tt_content WHERE deleted = 0 AND pid = ? AND CType = ? LIMIT 1',
        [$detailPageUid, 'casablancabooking_roomtypes']
    ) ?: 0);

    if ($detailContentUid <= 0) {
        $content->insert('tt_content', [
            'pid' => $detailPageUid,
            'tstamp' => $now,
            'crdate' => $now,
            'CType' => 'casablancabooking_roomtypes',
            'header' => 'Room detail',
            'pi_flexform' => $detailFlexform,
        ]);
    }

    $overviewFlexform = <<<XML
<?xml version="1.0" encoding="utf-8" standalone="yes" ?>
<T3FlexForms>
    <data>
        <sheet index="sDisplay">
            <language index="lDEF">
                <field index="settings.displayMode">
                    <value index="vDEF">overview</value>
                </field>
                <field index="settings.cardLinkType">
                    <value index="vDEF">details</value>
                </field>
                <field index="settings.detailPageUid">
                    <value index="vDEF">{$detailPageUid}</value>
                </field>
            </language>
        </sheet>
    </data>
</T3FlexForms>
XML;

    $content->update('tt_content', ['pi_flexform' => $overviewFlexform, 'tstamp' => $now], ['uid' => 4]);

    echo "detail_page_uid={$detailPageUid}\n";
})();
