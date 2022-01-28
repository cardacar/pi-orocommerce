<?php

namespace Oro\Component\ConfigExpression\Condition;

use Oro\Component\ConfigExpression\Exception;

/**
 * Implements logical TRUE constant.
 */
class TrueCondition extends AbstractCondition
{
    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'true';
    }

    /**
     * {@inheritdoc}
     */
    public function toArray()
    {
        return $this->convertToArray(null);
    }

    /**
     * {@inheritdoc}
     */
    public function compile($factoryAccessor)
    {
        return $this->convertToPhpCode(null, $factoryAccessor);
    }

    /**
     * {@inheritdoc}
     */
    public function initialize(array $options)
    {
        if (!empty($options)) {
            throw new Exception\InvalidArgumentException('Options are prohibited.');
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    protected function isConditionAllowed($context)
    {
        return true;
    }
}
