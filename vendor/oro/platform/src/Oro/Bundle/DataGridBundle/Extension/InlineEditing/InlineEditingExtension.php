<?php

namespace Oro\Bundle\DataGridBundle\Extension\InlineEditing;

use Oro\Bundle\DataGridBundle\Datagrid\Common\DatagridConfiguration;
use Oro\Bundle\DataGridBundle\Datagrid\Common\MetadataObject;
use Oro\Bundle\DataGridBundle\Extension\AbstractExtension;
use Oro\Bundle\DataGridBundle\Extension\Formatter\Configuration as FormatterConfiguration;
use Oro\Bundle\DataGridBundle\Extension\Formatter\Property\PropertyInterface;
use Oro\Bundle\DataGridBundle\Provider\DatagridModeProvider;
use Oro\Bundle\EntityBundle\Tools\EntityClassNameHelper;
use Symfony\Component\Security\Acl\Domain\ObjectIdentity;
use Symfony\Component\Security\Acl\Voter\FieldVote;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Enables inline editing on supported columns for grids which has inline editing enabled.
 */
class InlineEditingExtension extends AbstractExtension
{
    /** @var InlineEditColumnOptionsGuesser */
    protected $guesser;

    /** @var EntityClassNameHelper */
    protected $entityClassNameHelper;

    /** @var AuthorizationCheckerInterface */
    protected $authorizationChecker;

    /** {@inheritdoc} */
    protected $excludedModes = [
        DatagridModeProvider::DATAGRID_IMPORTEXPORT_MODE
    ];

    public function __construct(
        InlineEditColumnOptionsGuesser $inlineEditColumnOptionsGuesser,
        EntityClassNameHelper $entityClassNameHelper,
        AuthorizationCheckerInterface $authorizationChecker
    ) {
        $this->guesser = $inlineEditColumnOptionsGuesser;
        $this->entityClassNameHelper = $entityClassNameHelper;
        $this->authorizationChecker = $authorizationChecker;
    }

    /**
     * Validate configs nad fill default values
     */
    public function processConfigs(DatagridConfiguration $config)
    {
        if (!$config->offsetGetByPath(Configuration::ENABLED_CONFIG_PATH)) {
            return;
        }

        $entityName = $this->getEntityName($config);
        $configItems = $config->offsetGetOr(Configuration::BASE_CONFIG_KEY, []);
        $configItems[Configuration::CONFIG_ENTITY_KEY] = $entityName;

        $configuration = new Configuration(Configuration::BASE_CONFIG_KEY);
        $normalizedConfigItems = $this->validateConfiguration(
            $configuration,
            [Configuration::BASE_CONFIG_KEY => $configItems]
        );

        //according to ACL disable inline editing for the whole grid
        if (!$this->isGranted($configItems)) {
            $normalizedConfigItems[Configuration::CONFIG_ENABLE_KEY] = false;
        }

        // replace config values by normalized, extra keys passed directly
        $resultConfigItems = array_replace_recursive($configItems, $normalizedConfigItems);
        if (is_null($resultConfigItems['save_api_accessor']['default_route_parameters']['className'])) {
            $resultConfigItems['save_api_accessor']['default_route_parameters']['className'] =
                $this->entityClassNameHelper->getUrlSafeClassName($entityName);
        }
        $config->offsetSet(Configuration::BASE_CONFIG_KEY, $resultConfigItems);

        $this->applyInlineEditingForColumns($config, $configuration, $entityName);
    }

    /**
     * @param array $configItems
     * @return bool
     */
    protected function isGranted(array $configItems)
    {
        $acl = !empty($configItems[Configuration::CONFIG_ACL_KEY]) ?
            $configItems[Configuration::CONFIG_ACL_KEY] :
            'EDIT;entity:' . $configItems[Configuration::CONFIG_ENTITY_KEY];

        return $this->authorizationChecker->isGranted($acl);
    }

    /**
     * {@inheritDoc}
     */
    public function visitMetadata(DatagridConfiguration $config, MetadataObject $data)
    {
        if (!$config->offsetGetByPath(Configuration::ENABLED_CONFIG_PATH)) {
            return;
        }

        $data->offsetSet(
            Configuration::BASE_CONFIG_KEY,
            $config->offsetGetOr(Configuration::BASE_CONFIG_KEY, [])
        );
    }

    /**
     * Returns column data field name
     *
     * @param string $columnName
     * @param array $column
     *
     * @return string
     */
    protected function getColumnFieldName($columnName, $column)
    {
        $dadaFieldName = $columnName;
        if (array_key_exists(Configuration::BASE_CONFIG_KEY, $column)
            && isset($column[Configuration::BASE_CONFIG_KEY][Configuration::EDITOR_KEY])
            && isset(
                $column[Configuration::BASE_CONFIG_KEY][Configuration::EDITOR_KEY][Configuration::VIEW_OPTIONS_KEY]
            )
            && isset(
                $column[Configuration::BASE_CONFIG_KEY]
                [Configuration::EDITOR_KEY][Configuration::VIEW_OPTIONS_KEY][Configuration::VALUE_FIELD_NAME_KEY]
            )
        ) {
            $dadaFieldName = $column[Configuration::BASE_CONFIG_KEY]
            [Configuration::EDITOR_KEY][Configuration::VIEW_OPTIONS_KEY][Configuration::VALUE_FIELD_NAME_KEY];
        }

        return $dadaFieldName;
    }

    /**
     * @param array $newColumn
     * @param array $column
     *
     * @return bool
     */
    protected function isEnabledEdit($newColumn, $column)
    {
        if (array_key_exists(Configuration::BASE_CONFIG_KEY, $newColumn)
            && isset($newColumn[Configuration::BASE_CONFIG_KEY][Configuration::CONFIG_ENABLE_KEY])) {
            return $newColumn[Configuration::BASE_CONFIG_KEY][Configuration::CONFIG_ENABLE_KEY];
        }
        if (array_key_exists(Configuration::BASE_CONFIG_KEY, $column)
            && isset($column[Configuration::BASE_CONFIG_KEY][Configuration::CONFIG_ENABLE_KEY])) {
            return $column[Configuration::BASE_CONFIG_KEY][Configuration::CONFIG_ENABLE_KEY];
        }

        return false;
    }

    /**
     * @param array $column
     *
     * @return mixed
     */
    protected function disableColumnEdit($column)
    {
        if (array_key_exists(Configuration::BASE_CONFIG_KEY, $column)) {
            $column[Configuration::BASE_CONFIG_KEY][Configuration::CONFIG_ENABLE_KEY] = false;
        }

        return $column;
    }

    /**
     * @param array $newColumn
     * @param $objectIdentity
     * @param string $columnName
     * @param array $column
     *
     * @return bool
     */
    protected function isFieldEditable($newColumn, $objectIdentity, $columnName, $column)
    {
        return
            $this->isEnabledEdit($newColumn, $column)
            && $this->authorizationChecker->isGranted(
                'EDIT',
                new FieldVote($objectIdentity, $this->getColumnFieldName($columnName, $column))
            );
    }

    private function getEntityName(DatagridConfiguration $config): ?string
    {
        $configItems = $config->offsetGetOr(Configuration::BASE_CONFIG_KEY, []);

        if (!empty($configItems[Configuration::CONFIG_ENTITY_KEY])) {
            return $configItems[Configuration::CONFIG_ENTITY_KEY];
        }

        return  $config->getExtendedEntityClassName();
    }

    /**
     * Add inline editing where it is possible, do not use ACL, because additional parameters for columns needed
     */
    private function applyInlineEditingForColumns(
        DatagridConfiguration $config,
        Configuration $configuration,
        string $entityName
    ): void {
        $columns = $config->offsetGetOr(FormatterConfiguration::COLUMNS_KEY, []);
        $blackList = $configuration->getBlackList();
        $behaviour = $config->offsetGetByPath(Configuration::BEHAVIOUR_CONFIG_PATH);
        $objectIdentity = new ObjectIdentity('entity', $entityName);

        foreach ($columns as $columnName => &$column) {
            if (!in_array($columnName, $blackList, true)) {
                $newColumn = $this->guesser->getColumnOptions(
                    $columnName,
                    $entityName,
                    $column,
                    $behaviour
                );

                // Check access to edit field in Class level.
                // If access not granted - skip inline editing for such field.
                if (!$this->isFieldEditable($newColumn, $objectIdentity, $columnName, $column)) {
                    $column = $this->disableColumnEdit($column);
                    continue;
                }

                // frontend type, type, data_name keys must not be replaced with default value
                $keys = [
                    PropertyInterface::FRONTEND_TYPE_KEY,
                    PropertyInterface::TYPE_KEY,
                    PropertyInterface::DATA_NAME_KEY,
                ];
                foreach ($keys as $key) {
                    if (!empty($newColumn[$key])) {
                        $column[$key] = $newColumn[$key];
                    }
                }

                $column = array_replace_recursive($newColumn, $column);
            }
        }
        unset($column);

        $config->offsetSet(FormatterConfiguration::COLUMNS_KEY, $columns);
    }
}
