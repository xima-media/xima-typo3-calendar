<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\OperationHandler;

use Psr\Http\Message\ResponseInterface;
use SourceBroker\T3api\Domain\Model\ItemOperation;
use SourceBroker\T3api\Domain\Model\OperationInterface;
use SourceBroker\T3api\Exception\OperationNotAllowedException;
use SourceBroker\T3api\Exception\ResourceNotFoundException;
use SourceBroker\T3api\Exception\ValidationException;
use SourceBroker\T3api\OperationHandler\AbstractItemOperationHandler;
use Symfony\Component\HttpFoundation\Request;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\DomainObject\AbstractDomainObject;
use TYPO3\CMS\Extbase\Persistence\Exception\UnknownObjectException;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;
use Xima\XimaTypo3Calendar\Domain\Model\Api\Event;

class EventChangeRequestOperationHandler extends AbstractItemOperationHandler
{
    public static function supports(OperationInterface $operation, Request $request): bool
    {
        return parent::supports($operation, $request) && $operation->isMethodPost() && $operation->getKey() === 'change_request';
    }

    /**
     * @noinspection ReferencingObjectsInspection
     * @throws UnknownObjectException
     * @throws OperationNotAllowedException
     * @throws ValidationException
     * @throws ResourceNotFoundException
     */
    public function handle(
        OperationInterface $operation,
        Request $request,
        array $route,
        ?ResponseInterface &$response
    ): AbstractDomainObject {
        /** @var ItemOperation $operation */
        $repository = $this->getRepositoryForOperation($operation);
        $object = parent::handle($operation, $request, $route, $response);

        $newEvent = new Event();
        foreach ($object->_getProperties() as $propertyName => $propertyValue) {
            if ($propertyName === 'uid') {
                continue;
            }
            $newEvent->_setProperty($propertyName, $propertyValue);
        }
        $this->deserializeOperation($operation, $request, $newEvent);

        $newEvent->setTargetEvent($object);
        $newEvent->setType('change');

        $this->validationService->validateObject($object);
        $repository->add($newEvent);
        GeneralUtility::makeInstance(PersistenceManager::class)->persistAll();

        return $newEvent;
    }
}
