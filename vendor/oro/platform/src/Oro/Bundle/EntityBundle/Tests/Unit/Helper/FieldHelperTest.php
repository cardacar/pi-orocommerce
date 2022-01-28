<?php

namespace Oro\Bundle\EntityBundle\Tests\Unit\Helper;

use Oro\Bundle\EntityBundle\Helper\FieldHelper;
use Oro\Bundle\EntityBundle\Provider\EntityFieldProvider;
use Oro\Bundle\EntityConfigBundle\Config\ConfigInterface;
use Oro\Bundle\EntityConfigBundle\Provider\ConfigProvider;
use Oro\Bundle\EntityExtendBundle\Configuration\EntityExtendConfigurationProvider;
use Oro\Bundle\EntityExtendBundle\Extend\FieldTypeHelper;
use Oro\Bundle\ImportExportBundle\Tests\Unit\Strategy\Stub\ImportEntity;
use Symfony\Component\PropertyAccess\Exception\NoSuchPropertyException;

/**
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 */
class FieldHelperTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var array
     */
    protected array $config = [
        'TestEntity' => [
            'testField' => [
                'testParameter' => 1,
                'identity' => true,
                'excluded' => false,
            ],
        ],
        'TestEntityScalar' => [
            'ScalarField' => [
                'process_as_scalar' => true,
                'identity' => false,
                'excluded' => false,
            ],
        ],
        'TestEntity2' => [
            'testField' => [
                'identity' => -1,
                'excluded' => true,
            ],
        ],
    ];

    /**
     * @var \PHPUnit\Framework\MockObject\MockObject
     */
    protected $fieldProvider;

    /**
     * @var \PHPUnit\Framework\MockObject\MockObject
     */
    protected $configProvider;

    /**
     * @var FieldHelper
     */
    protected FieldHelper $helper;

    protected function setUp(): void
    {
        $this->fieldProvider = $this->createMock(EntityFieldProvider::class);
        $this->configProvider = $this->prepareConfigProvider();

        $entityExtendConfigurationProvider = $this->createMock(EntityExtendConfigurationProvider::class);
        $entityExtendConfigurationProvider->expects(self::any())
            ->method('getUnderlyingTypes')
            ->willReturn([]);

        $this->helper = new FieldHelper(
            $this->fieldProvider,
            $this->configProvider,
            new FieldTypeHelper($entityExtendConfigurationProvider)
        );
    }

    /**
     * @return \PHPUnit\Framework\MockObject\MockObject|ConfigProvider
     */
    protected function prepareConfigProvider()
    {
        $configProvider = $this->createMock(ConfigProvider::class);
        $configProvider->expects(self::any())->method('hasConfig')
            ->with(self::isType('string'), self::isType('string'))
            ->willReturnCallback(
                function ($entityName, $fieldName) {
                    return isset($this->config[$entityName][$fieldName]);
                }
            );
        $configProvider->expects(self::any())->method('getConfig')
            ->with(self::isType('string'), self::isType('string'))
            ->willReturnCallback(
                function ($entityName, $fieldName) {
                    $entityConfig = $this->createMock(ConfigInterface::class);
                    $entityConfig->expects($this->any())->method('has')->with($this->isType('string'))
                        ->willReturnCallback(
                            function ($parameter) use ($entityName, $fieldName) {
                                return isset($this->config[$entityName][$fieldName][$parameter]);
                            }
                        );
                    $entityConfig->expects($this->any())->method('get')->with($this->isType('string'))
                        ->willReturnCallback(
                            function ($parameter, $isStrict, $default) use ($entityName, $fieldName) {
                                return $this->config[$entityName][$fieldName][$parameter] ?? $default;
                            }
                        );

                    return $entityConfig;
                }
            );

        return $configProvider;
    }

    public function testGetFields(): void
    {
        $entityName = 'TestEntity';
        $withRelations = true;
        $expectedFields = [['name' => 'field']];
        $expectedFieldsLocalized = [['nameLocalized' => 'fieldLocalized']];

        $this->fieldProvider->expects(self::exactly(4))
            ->method('getLocale')
            ->willReturnOnConsecutiveCalls(
                null,
                null,
                'de-DE',
                'de-DE'
            );

        $this->fieldProvider->expects(self::exactly(2))
            ->method('getFields')
            ->with($entityName, $withRelations)
            ->willReturnOnConsecutiveCalls(
                $expectedFields,
                $expectedFieldsLocalized
            );

        // first result without locale
        self::assertEquals($expectedFields, $this->helper->getFields($entityName, $withRelations));

        // cached result without locale
        self::assertEquals($expectedFields, $this->helper->getFields($entityName, $withRelations));

        // first result with locale
        self::assertEquals($expectedFieldsLocalized, $this->helper->getFields($entityName, $withRelations));

        // cached result with locale
        self::assertEquals($expectedFieldsLocalized, $this->helper->getFields($entityName, $withRelations));
    }

    public function testGetEntityFields(): void
    {
        $entityName = 'TestEntity';
        $expectedFields = [['name' => 'field']];
        $expectedFieldsLocalized = [['nameLocalized' => 'fieldLocalized']];

        $this->fieldProvider->expects(self::exactly(4))
            ->method('getLocale')
            ->willReturnOnConsecutiveCalls(
                null,
                null,
                'de-DE',
                'de-DE'
            );

        $options = EntityFieldProvider::OPTION_WITH_RELATIONS;
        $optionsWithTranslate = $options | EntityFieldProvider::OPTION_TRANSLATE;
        $this->fieldProvider->expects(self::exactly(2))
            ->method('getEntityFields')
            ->withConsecutive([$entityName, $options], [$entityName, $optionsWithTranslate])
            ->willReturnOnConsecutiveCalls(
                $expectedFields,
                $expectedFieldsLocalized
            );

        // first result without locale
        self::assertEquals($expectedFields, $this->helper->getEntityFields($entityName, $options));

        // cached result without locale
        self::assertEquals($expectedFields, $this->helper->getEntityFields($entityName, $options));

        // first result with locale
        self::assertEquals(
            $expectedFieldsLocalized,
            $this->helper->getEntityFields($entityName, $optionsWithTranslate)
        );

        // cached result with locale
        self::assertEquals(
            $expectedFieldsLocalized,
            $this->helper->getEntityFields($entityName, $optionsWithTranslate)
        );
    }

    /**
     * @param mixed $expected
     * @param string $entityName
     * @param string $fieldName
     * @param string $parameter
     * @param mixed|null $default
     * @param bool $hasConfig
     *
     * @dataProvider getConfigValueDataProvider
     */
    public function testGetConfigValue(
        $expected,
        string $entityName,
        string $fieldName,
        string $parameter,
        $default,
        bool $hasConfig = true
    ): void {
        if (!is_null($expected)) {
            self::assertTrue($this->helper->hasConfig($entityName, $fieldName));
        }

        $value = $this->helper->getConfigValue($entityName, $fieldName, $parameter, $default);
        self::assertSame($expected, $value);
        self::assertSame($value, $this->helper->getConfigValue($entityName, $fieldName, $parameter, $default));

        // has config from caches
        self::assertEquals($hasConfig, $this->helper->hasConfig($entityName, $fieldName));
    }

    public function getConfigValueDataProvider(): array
    {
        return [
            'unknown entity or field' => [
                'expected' => null,
                'entityName' => 'UnknownEntity',
                'fieldName' => 'unknownField',
                'parameter' => 'someParameter',
                'default' => null,
                'hasConfig' => false,
            ],
            'no parameter with default' => [
                'expected' => false,
                'entityName' => 'TestEntity',
                'fieldName' => 'testField',
                'parameter' => 'unknownParameter',
                'default' => false,
            ],
            'existing parameter' => [
                'expected' => 1,
                'entityName' => 'TestEntity',
                'fieldName' => 'testField',
                'parameter' => 'testParameter',
                'default' => null,
            ],
        ];
    }

    /**
     * @param boolean $expected
     * @param array $field
     *
     * @dataProvider relationDataProvider
     */
    public function testIsRelation(bool $expected, array $field): void
    {
        self::assertSame($expected, $this->helper->isRelation($field));
    }

    public function relationDataProvider(): array
    {
        return [
            'no relation type' => [
                'expected' => false,
                'field' => [
                    'related_entity_name' => 'TestEntity',
                ],
            ],
            'no related entity name' => [
                'expected' => false,
                'field' => [
                    'relation_type' => 'ref-one',
                ],
            ],
            'valid relation' => [
                'expected' => true,
                'field' => [
                    'relation_type' => 'ref-one',
                    'related_entity_name' => 'TestEntity',
                ],
            ],
        ];
    }

    /**
     * @param boolean $expected
     * @param array $field
     *
     * @dataProvider singleRelationDataProvider
     */
    public function testIsSingleRelation(bool $expected, array $field): void
    {
        self::assertSame($expected, $this->helper->isSingleRelation($field));
    }

    public function singleRelationDataProvider(): array
    {
        return [
            'single relation ref-one' => [
                'expected' => true,
                'field' => [
                    'relation_type' => 'ref-one',
                    'related_entity_name' => 'TestEntity',
                ],
            ],
            'single relation manyToOne' => [
                'expected' => true,
                'field' => [
                    'relation_type' => 'manyToOne',
                    'related_entity_name' => 'TestEntity',
                ],
            ],
            'multiple relation' => [
                'expected' => false,
                'field' => [
                    'relation_type' => 'ref-many',
                    'related_entity_name' => 'TestEntity',
                ],
            ],
        ];
    }

    /**
     * @param boolean $expected
     * @param array $field
     *
     * @dataProvider multipleRelationDataProvider
     */
    public function testIsMultipleRelation(bool $expected, array $field): void
    {
        self::assertSame($expected, $this->helper->isMultipleRelation($field));
    }

    public function multipleRelationDataProvider(): array
    {
        return [
            'multiple relation ref-many' => [
                'expected' => true,
                'field' => [
                    'relation_type' => 'ref-many',
                    'related_entity_name' => 'TestEntity',
                ],
            ],
            'multiple relation oneToMany' => [
                'expected' => true,
                'field' => [
                    'relation_type' => 'oneToMany',
                    'related_entity_name' => 'TestEntity',
                ],
            ],
            'multiple relation manyToMany' => [
                'expected' => true,
                'field' => [
                    'relation_type' => 'manyToMany',
                    'related_entity_name' => 'TestEntity',
                ],
            ],
            'single relation' => [
                'expected' => false,
                'field' => [
                    'relation_type' => 'ref-one',
                    'related_entity_name' => 'TestEntity',
                ],
            ],
        ];
    }

    /**
     * @param bool $expected
     * @param array $field
     *
     * @dataProvider dateTimeDataProvider
     */
    public function testIsDateTimeField(bool $expected, array $field): void
    {
        self::assertSame($expected, $this->helper->isDateTimeField($field));
    }

    public function dateTimeDataProvider(): array
    {
        return [
            'date' => [
                'expected' => true,
                'field' => ['type' => 'date'],
            ],
            'time' => [
                'expected' => true,
                'field' => ['type' => 'time'],
            ],
            'datetime' => [
                'expected' => true,
                'field' => ['type' => 'datetime'],
            ],
            'string' => [
                'expected' => false,
                'field' => ['type' => 'string'],
            ],
        ];
    }

    /**
     * @param object $object
     * @param string $fieldName
     * @param mixed $value
     * @param array $exception
     *
     * @dataProvider objectValueProvider
     */
    public function testSetObjectValue(object $object, string $fieldName, $value, array $exception): void
    {
        if ($exception) {
            [$class, $message] = $exception;
            $this->expectException($class);
            $this->expectExceptionMessage($message);
        }

        $this->helper->setObjectValue($object, $fieldName, $value);

        self::assertSame($value, $this->helper->getObjectValue($object, $fieldName));
    }

    /**
     * @param object $object
     * @param string $fieldName
     * @param mixed $value
     * @param array $exception
     *
     * @dataProvider objectValueProvider
     */
    public function testGetObjectValue(object $object, string $fieldName, $value, array $exception): void
    {
        if ($exception) {
            [$class, $message] = $exception;
            $this->expectException($class);
            $this->expectExceptionMessage($message);
        }

        self::assertEquals(null, $this->helper->getObjectValue($object, $fieldName));
        $this->helper->setObjectValue($object, $fieldName, $value);
        self::assertEquals($value, $this->helper->getObjectValue($object, $fieldName));
    }

    public function objectValueProvider(): array
    {
        $object = new ImportEntity();

        return [
            'not_exists' => [
                'object' => $object,
                'fieldName' => 'not_exists',
                'value' => 'test',
                'exception' => [
                    NoSuchPropertyException::class,
                    'Neither the property "not_exists" nor one of the methods ',
                ],
            ],
            'protected' => [
                'object' => $object,
                'fieldName' => 'twitter',
                'value' => 'username',
                'exception' => [],
            ],
            'private' => [
                'object' => $object,
                'fieldName' => 'private',
                'value' => 'should_be_set',
                'exception' => [],
            ],
            'private of the parent' => [
                'object' => $object,
                'fieldName' => 'basePrivate',
                'value' => 'val',
                'exception' => [],
            ],
        ];
    }

    /**
     * @param mixed $data
     * @param string|null $fieldName
     * @param array $expected
     *
     * @dataProvider getItemDataDataProvider
     */
    public function testGetItemData($data, ?string $fieldName, array $expected): void
    {
        self::assertSame($expected, $this->helper->getItemData($data, $fieldName));
    }

    public function getItemDataDataProvider(): array
    {
        return [
            'not an array' => [
                'data' => new \stdClass(),
                'fieldName' => 'field',
                'expected' => [],
            ],
            'null field' => [
                'data' => ['field' => 'value'],
                'fieldName' => null,
                'expected' => ['field' => 'value'],
            ],
            'existing field' => [
                'data' => ['field' => ['value']],
                'fieldName' => 'field',
                'expected' => ['value'],
            ],
            'not existing field' => [
                'data' => [],
                'fieldName' => 'field',
                'expected' => [],
            ],
        ];
    }

    public function testGetIdentityValues(): void
    {
        $this->config['stdClass'] = [
            'excludedField' => ['excluded' => true],
            'identityField' => ['identity' => true],
            'onlyWhenNotEmptyIdentityField' => ['identity' => true],
            'regularField' => [],
        ];

        $fields = [
            ['name' => 'excludedField'],
            ['name' => 'identityField'],
            ['name' => 'onlyWhenNotEmptyIdentityField'],
            ['name' => 'regularField'],
        ];

        $entity = new \stdClass();
        $entity->excludedField = 'excludedValue';
        $entity->identityField = 'identityValue';
        $entity->onlyWhenNotEmptyIdentityField = 'onlyWhenNotEmptyIdentityValue';
        $entity->regularField = 'regularValue';

        $this->fieldProvider->expects(self::once())
            ->method('getFields')
            ->with(get_class($entity), true)
            ->willReturn($fields);

        $value = $this->helper->getIdentityValues($entity);
        self::assertEquals(
            [
                'identityField' => 'identityValue',
                'onlyWhenNotEmptyIdentityField' => 'onlyWhenNotEmptyIdentityValue',
            ],
            $value
        );
        self::assertSame($value, $this->helper->getIdentityValues($entity));
    }

    /**
     * @dataProvider isRequiredIdentityFieldProvider
     */
    public function testIsRequiredIdentityField($identityValue, $expectedResult): void
    {
        $this->config['stdClass'] = [
            'testField' => ['identity' => $identityValue],
        ];

        self::assertEquals(
            $expectedResult,
            $this->helper->isRequiredIdentityField('stdClass', 'testField')
        );
    }

    public function isRequiredIdentityFieldProvider(): array
    {
        return [
            [false, false],
            [true, true],
            [FieldHelper::IDENTITY_ONLY_WHEN_NOT_EMPTY, false],
        ];
    }

    public function testProcessAsScalar(): void
    {
        self::assertFalse($this->helper->processRelationAsScalar('TestEntity', 'testField'));
        self::assertTrue($this->helper->processRelationAsScalar('TestEntityScalar', 'ScalarField'));
    }

    public function testGetRelations(): void
    {
        $entityName = 'TestEntity';
        $expectedRelations = [['name' => 'field']];

        $this->fieldProvider->expects(self::once())->method('getRelations')->with($entityName)
            ->willReturn($expectedRelations);

        self::assertEquals($expectedRelations, $this->helper->getRelations($entityName));

        // do not call twice
        self::assertEquals($expectedRelations, $this->helper->getRelations($entityName));
    }

    public function testTranslateUsingLocale(): void
    {
        $locale = 'it_IT';
        $this->fieldProvider->expects(self::once())
            ->method('setLocale')
            ->with($locale);

        $this->helper->setLocale($locale);
    }

    /**
     * @param string $entityName
     * @param string $fieldName
     * @param array $data
     * @param bool $expected
     *
     * @dataProvider isFieldExcludedProvider
     */
    public function testIsFieldExcluded(string $entityName, string $fieldName, array $data, bool $expected): void
    {
        self::assertEquals($expected, $this->helper->isFieldExcluded($entityName, $fieldName, $data));
    }

    public function isFieldExcludedProvider(): array
    {
        return [
            'non identity field, empty data' => [
                'entityName' => 'TestEntityScalar',
                'fieldName' => 'ScalarField',
                'data' => [],
                'expected' => true,
            ],
            'non identity field' => [
                'entityName' => 'TestEntityScalar',
                'fieldName' => 'ScalarField',
                'data' => ['ScalarField' => 'test'],
                'expected' => false,
            ],
            'identity field, empty data' => [
                'entityName' => 'TestEntity',
                'fieldName' => 'testField',
                'data' => [],
                'expected' => false,
            ],
            'identity field' => [
                'entityName' => 'TestEntity',
                'fieldName' => 'testField',
                'data' => ['testField' => 'test'],
                'expected' => false,
            ],
            'excluded field, empty data' => [
                'entityName' => 'TestEntity2',
                'fieldName' => 'testField',
                'data' => [],
                'expected' => true,
            ],
            'excluded field' => [
                'entityName' => 'TestEntity2',
                'fieldName' => 'testField',
                'data' => ['testField' => 'test'],
                'expected' => true,
            ],
        ];
    }
}
