<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\Backend\UserSettings;

use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Database\Query\Restriction\HiddenRestriction;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Renders the "restrict notifications to categories" field for the User Settings
 * module ($GLOBALS['TYPO3_USER_SETTINGS']). The module has no native category-tree
 * field, so this builds the sys_category tree as nested checkboxes. A small ES
 * module syncs the checked UIDs into a hidden input named like the be_users column,
 * which the SetupModuleController then persists through DataHandler (category MM).
 */
final class NotificationCategoryField
{
    private const FIELD = 'tx_ximatypo3calendar_notify_categories';
    private const TABLE = 'be_users';
    private const LL = 'LLL:EXT:xima_typo3_calendar/Resources/Private/Language/locallang_be.xlf:';
    private const SETTING_PARENT_CATEGORY = 'xima_typo3_calendar.notifications.parentCategory';

    public function render(array $params, object $parentObject): string
    {
        $beUserId = (int)($GLOBALS['BE_USER']->user['uid'] ?? 0);
        $selected = $this->getSelectedCategoryUids($beUserId);
        $childrenMap = $this->buildChildrenMap($this->getAllCategories());

        GeneralUtility::makeInstance(PageRenderer::class)
            ->loadJavaScriptModule('@xima/xima-typo3-calendar/usersettings-notification-categories.js');

        // Optional site-set setting (xima/xima-typo3-calendar): restrict the offered
        // categories to the subtree(s) below the configured parent category UID(s).
        $tree = '';
        foreach ($this->getRootParentUids() as $rootParent) {
            $tree .= $this->renderBranch($rootParent, $childrenMap, $selected, 0);
        }
        if ($tree === '') {
            $tree = '<p class="text-body-secondary mb-0">'
                . htmlspecialchars($this->getLanguageService()->sL(self::LL . 'notifications.notify_categories.empty'))
                . '</p>';
        }

        return '<div class="xima-notify-categories" data-notify-categories>'
            . '<input type="hidden" name="data[be_users][' . self::FIELD . ']"'
            . ' value="' . htmlspecialchars(implode(',', $selected)) . '" data-notify-categories-value>'
            . $tree
            . '</div>';
    }

    /**
     * @return int[]
     */
    private function getSelectedCategoryUids(int $beUserId): array
    {
        if ($beUserId === 0) {
            return [];
        }

        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('sys_category_record_mm');
        $queryBuilder->getRestrictions()->removeAll();

        $rows = $queryBuilder
            ->select('uid_local')
            ->from('sys_category_record_mm')
            ->where(
                $queryBuilder->expr()->eq('uid_foreign', $queryBuilder->createNamedParameter($beUserId, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('tablenames', $queryBuilder->createNamedParameter(self::TABLE)),
                $queryBuilder->expr()->eq('fieldname', $queryBuilder->createNamedParameter(self::FIELD)),
            )
            ->orderBy('sorting_foreign')
            ->executeQuery()
            ->fetchFirstColumn();

        return array_map('intval', $rows);
    }

    /**
     * @return list<array{uid: int, title: string, parent: int}>
     */
    private function getAllCategories(): array
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('sys_category');
        $queryBuilder->getRestrictions()
            ->removeAll()
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class))
            ->add(GeneralUtility::makeInstance(HiddenRestriction::class));

        $rows = $queryBuilder
            ->select('uid', 'title', 'parent')
            ->from('sys_category')
            ->where($queryBuilder->expr()->in('sys_language_uid', [0, -1]))
            ->orderBy('sorting')
            ->executeQuery()
            ->fetchAllAssociative();

        $categories = [];
        foreach ($rows as $row) {
            $categories[] = [
                'uid' => (int)$row['uid'],
                'title' => (string)$row['title'],
                'parent' => (int)$row['parent'],
            ];
        }

        return $categories;
    }

    /**
     * @param list<array{uid: int, title: string, parent: int}> $categories
     * @return array<int, list<array{uid: int, title: string, parent: int}>>
     */
    private function buildChildrenMap(array $categories): array
    {
        $map = [];
        foreach ($categories as $category) {
            $map[$category['parent']][] = $category;
        }

        return $map;
    }

    /**
     * @param array<int, list<array{uid: int, title: string, parent: int}>> $childrenMap
     * @param int[] $selected
     */
    private function renderBranch(int $parent, array $childrenMap, array $selected, int $depth): string
    {
        if (empty($childrenMap[$parent])) {
            return '';
        }

        $items = '';
        foreach ($childrenMap[$parent] as $category) {
            $id = 'notify-cat-' . $category['uid'];
            $checked = in_array($category['uid'], $selected, true) ? ' checked' : '';
            $items .= '<li>'
                . '<div class="form-check">'
                . '<input type="checkbox" class="form-check-input" id="' . $id . '" value="' . $category['uid'] . '"' . $checked . '>'
                . '<label class="form-check-label" for="' . $id . '">' . htmlspecialchars($category['title']) . '</label>'
                . '</div>'
                . $this->renderBranch($category['uid'], $childrenMap, $selected, $depth + 1)
                . '</li>';
        }

        return '<ul class="list-unstyled' . ($depth > 0 ? ' ms-4' : '') . '">' . $items . '</ul>';
    }

    /**
     * Starting points for the rendered tree. If the calendar site set defines a
     * parent category UID (optional), only that category's subtree is offered;
     * UIDs are collected across all sites. Defaults to the category roots (0).
     *
     * @return int[]
     */
    private function getRootParentUids(): array
    {
        $parents = [];
        foreach (GeneralUtility::makeInstance(SiteFinder::class)->getAllSites() as $site) {
            $parent = (int)$site->getSettings()->get(self::SETTING_PARENT_CATEGORY, 0);
            if ($parent > 0) {
                $parents[$parent] = $parent;
            }
        }

        return $parents !== [] ? array_values($parents) : [0];
    }

    private function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }
}
