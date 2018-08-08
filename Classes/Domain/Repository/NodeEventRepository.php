<?php
namespace AE\History\Domain\Repository;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Persistence\Doctrine\Query;
use Neos\Flow\Persistence\QueryResultInterface;
use Neos\Neos\EventLog\Domain\Model\NodeEvent;
use Neos\Neos\EventLog\Domain\Repository\EventRepository;

/**
 * The repository for events
 *
 * @Flow\Scope("singleton")
 */
class NodeEventRepository extends EventRepository
{
    public const ENTITY_CLASSNAME = NodeEvent::class;


    /**
     * Find all events which are "top-level" and in a given workspace (or are not NodeEvents)
     *
     * @param integer $offset
     * @param integer $limit
     * @param string $workspaceName
     * @param string $siteIdentifier
     * @param string $nodeIdentifier
     *
     * @return QueryResultInterface
     */
    public function findRelevantEventsByWorkspace(
        $offset,
        $limit,
        $workspaceName,
        ?string $siteIdentifier = null,
        ?string $nodeIdentifier = null
    ) : QueryResultInterface {
        /** @var Query $query */
        $query = parent::findRelevantEventsByWorkspace($offset, $limit, $workspaceName)->getQuery();
        $query->getQueryBuilder()->andWhere('e.eventType = :eventType')->setParameter('eventType', 'Node.Published');
        if ($siteIdentifier !== null) {
            $siteCondition = '%' . \trim(\json_encode(['site' => $siteIdentifier], JSON_PRETTY_PRINT), "{}\n\t ") . '%';
            $query->getQueryBuilder()->andWhere('NEOSCR_TOSTRING(e.data) LIKE :site')->setParameter(
                'site',
                $siteCondition
            );
        }
        if ($nodeIdentifier !== null) {
            $query->getQueryBuilder()->andWhere('e.nodeIdentifier = :nodeIdentifier')->setParameter(
                'nodeIdentifier',
                $nodeIdentifier
            );
        }

        return $query->execute();
    }
}
