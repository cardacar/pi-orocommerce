<?php

namespace Oro\Bundle\DataGridBundle\ImportExport;

use Akeneo\Bundle\BatchBundle\Entity\StepExecution;
use Oro\Bundle\DataGridBundle\Datagrid\DatagridInterface;
use Oro\Bundle\DataGridBundle\Datagrid\Manager;
use Oro\Bundle\DataGridBundle\Datagrid\ParameterBag;
use Oro\Bundle\DataGridBundle\Exception\LogicException;
use Oro\Bundle\DataGridBundle\ImportExport\FilteredEntityReader\FilteredEntityIdentityReaderInterface;
use Oro\Bundle\ImportExportBundle\Reader\BatchIdsReaderInterface;
use Oro\Bundle\ImportExportBundle\Reader\EntityReader;
use Oro\Bundle\ImportExportBundle\Reader\ReaderInterface;
use Oro\Bundle\SecurityBundle\ORM\Walker\AclHelper;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Reader for export filtered entities.
 */
class FilteredEntityReader implements ReaderInterface, BatchIdsReaderInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    public const FILTERED_RESULTS_GRID = 'filteredResultsGrid';
    public const FILTERED_RESULTS_GRID_PARAMS = 'filteredResultsGridParams';

    /** @var Manager */
    private $datagridManager;

    /** @var AclHelper */
    private $aclHelper;

    /** @var EntityReader */
    private $entityReader;

    /** @var EventDispatcherInterface */
    private $eventDispatcher;

    /** @var iterable|FilteredEntityIdentityReaderInterface[]|null */
    private $entityIdentityReaders;

    public function __construct(
        Manager $datagridManager,
        AclHelper $aclHelper,
        EntityReader $entityReader,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->datagridManager = $datagridManager;
        $this->aclHelper = $aclHelper;
        $this->entityReader = $entityReader;
        $this->eventDispatcher = $eventDispatcher;
        $this->logger = new NullLogger();
    }

    /**
     * {@inheritdoc}
     */
    public function setStepExecution(StepExecution $stepExecution)
    {
        $this->entityReader->setStepExecution($stepExecution);
    }

    /**
     * @param iterable|FilteredEntityIdentityReaderInterface[] $entityIdentityReaders
     */
    public function setEntityIdentityReaders(iterable $entityIdentityReaders)
    {
        $this->entityIdentityReaders = $entityIdentityReaders;
    }

    /**
     * {@inheritdoc}
     */
    public function read()
    {
        return $this->entityReader->read();
    }

    /**
     * {@inheritdoc}
     */
    public function getIds($entityName, array $options = [])
    {
        if (!isset($options[self::FILTERED_RESULTS_GRID])) {
            return $this->entityReader->getIds($entityName, $options);
        }

        $gridName = $options[self::FILTERED_RESULTS_GRID];
        $queryString = $options[self::FILTERED_RESULTS_GRID_PARAMS] ?? '';

        if (!is_string($queryString)) {
            throw new LogicException(sprintf(
                'filteredResultsGridParams parameter should be of string type, %s given.',
                gettype($queryString)
            ));
        }

        parse_str($queryString, $parameters);

        // Creates grid based on parameters from query string
        try {
            $datagrid = $this->datagridManager->getDatagrid(
                $gridName,
                [ParameterBag::MINIFIED_PARAMETERS => $parameters]
            );
        } catch (\Exception $exception) {
            $this->logger->error('Unable to create datagrid.', [
                'exception' => $exception,
                'datagridOptions' => $options
            ]);

            return [0];
        }

        $entityIdentityReader = $this->getApplicableIdentityReader($datagrid, $entityName, $options);

        if (!$entityIdentityReader) {
            throw new LogicException('Applicable entity identity reader is not found');
        }
        return $entityIdentityReader->getIds($datagrid, $entityName, $options);
    }

    private function getApplicableIdentityReader(
        DatagridInterface $datagrid,
        string $entityName,
        array $options
    ): ?FilteredEntityIdentityReaderInterface {
        foreach ($this->entityIdentityReaders as $entityIdentityReader) {
            if ($entityIdentityReader->isApplicable($datagrid, $entityName, $options)) {
                return $entityIdentityReader;
            }
        }

        return null;
    }
}
