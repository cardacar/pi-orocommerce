<?php

namespace Oro\Bundle\EntityMergeBundle\Tests\Unit\Validator\Constraints;

use Oro\Bundle\EntityMergeBundle\Tests\Unit\Stub\EntityStub;
use Oro\Bundle\EntityMergeBundle\Validator\Constraints\MasterEntity;
use Oro\Bundle\EntityMergeBundle\Validator\Constraints\MasterEntityValidator;
use Symfony\Component\Validator\Context\ExecutionContext;

class MasterEntityValidatorTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var MasterEntityValidator
     */
    protected $validator;

    protected function setUp(): void
    {
        $doctrineHelper = $this
            ->getMockBuilder('Oro\Bundle\EntityMergeBundle\Doctrine\DoctrineHelper')
            ->disableOriginalConstructor()
            ->getMock();

        $doctrineHelper
            ->expects($this->any())
            ->method('isEntityEqual')
            ->will(
                $this->returnCallback(
                    function ($entity, $other) {
                        return $entity->getId() === $other->getId();
                    }
                )
            );

        $this->validator = new MasterEntityValidator($doctrineHelper);
    }

    /**
     * @dataProvider invalidArgumentProvider
     */
    public function testInvalidArgument($value, $expectedExceptionMessage)
    {
        $this->expectException(\Oro\Bundle\EntityMergeBundle\Exception\InvalidArgumentException::class);
        $this->expectExceptionMessage($expectedExceptionMessage);

        $constraint = $this
            ->createMock('Oro\Bundle\EntityMergeBundle\Validator\Constraints\MasterEntity');
        $this->validator->validate($value, $constraint);
    }

    public function invalidArgumentProvider()
    {
        return [
            'scalar'  => [
                'value'                    => 'string',
                'expectedExceptionMessage' =>
                    'Oro\Bundle\EntityMergeBundle\Data\EntityData supported only, string given'
            ],
            'integer' => [
                'value'                    => 5,
                'expectedExceptionMessage' =>
                    'Oro\Bundle\EntityMergeBundle\Data\EntityData supported only, integer given'
            ],
            'null'    => [
                'value'                    => null,
                'expectedExceptionMessage' =>
                    'Oro\Bundle\EntityMergeBundle\Data\EntityData supported only, NULL given'
            ],
            'object'  => [
                'value'                    => new \stdClass(),
                'expectedExceptionMessage' =>
                    'Oro\Bundle\EntityMergeBundle\Data\EntityData supported only, stdClass given'
            ],
            'array'   => [
                'value'                    => [],
                'expectedExceptionMessage' =>
                    'Oro\Bundle\EntityMergeBundle\Data\EntityData supported only, array given'
            ],
        ];
    }

    /**
     * @dataProvider validArgumentProvider
     */
    public function testValidate($value, $addViolation, $masterEntity)
    {
        $context = $this->createMock(ExecutionContext::class);

        $context->expects($this->$addViolation())
            ->method('addViolation');

        $constraint = $this->createMock(MasterEntity::class);
        $this->validator->initialize($context);

        $value
            ->expects($this->any())
            ->method('getMasterEntity')
            ->will($this->returnValue($masterEntity));

        $this->validator->validate($value, $constraint);
    }

    public function validArgumentProvider()
    {
        return [
            'valid' => [
                'value'        => $this->createEntityData(),
                'addViolation' => 'never',
                'masterEntity' => new EntityStub('entity'),
            ],
            'non-valid'     => [
                'value'        => $this->createEntityData(),
                'addViolation' => 'once',
                'masterEntity' => new EntityStub('non-valid'),
            ],
        ];
    }

    /**
     * @return object
     */
    private function createEntityData()
    {
        $entityData = $this
            ->getMockBuilder('Oro\Bundle\EntityMergeBundle\Data\EntityData')
            ->disableOriginalConstructor()
            ->getMock();

        $entityData
            ->expects($this->any())
            ->method('getEntities')
            ->will(
                $this->returnValue(
                    [
                        new EntityStub('entity'),
                        new EntityStub('entity-2'),
                    ]
                )
            );

        return $entityData;
    }
}
