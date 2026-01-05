<?php

namespace Xima\XimaTypo3Calendar\Domain\Repository;

use TYPO3\CMS\Extbase\Persistence\QueryInterface;
use TYPO3\CMS\Extbase\Persistence\Repository;

class EventRepository extends Repository
{
    protected $defaultOrderings = [
        'publishDate' => QueryInterface::ORDER_DESCENDING,
    ];

    /**
     * Find published events
     *
     * @param int $limit Maximum number of events to return (0 for unlimited)
     * @return array
     */
    public function findPublished(int $limit = 0): array
    {
        $query = $this->createQuery();
        $query->matching(
            $query->logicalAnd(
                $query->equals('type', 'live'),
                $query->equals('publishToWebsite', true)
            )
        );

        if ($limit > 0) {
            $query->setLimit($limit);
        }

        return $query->execute()->toArray();
    }

    /**
     * Find latest published events
     *
     * @param int $limit Maximum number of events to return
     * @return array
     */
    public function findLatest(int $limit = 3): array
    {
        return $this->findPublished($limit);
    }
}
