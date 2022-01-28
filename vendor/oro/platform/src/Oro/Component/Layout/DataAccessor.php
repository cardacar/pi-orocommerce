<?php

namespace Oro\Component\Layout;

use Oro\Component\Layout\Exception\InvalidArgumentException;

/**
 * The data accessor that falls back to the layout context if a data provider
 * is not registered in the layout registry.
 * This means that at first the data provider is searched in the layout registry and
 * if it is not registered there, data are searched in the context.
 */
class DataAccessor implements DataAccessorInterface
{
    private LayoutRegistryInterface $registry;
    private ContextInterface $context;
    private array $dataProviders = [];

    public function __construct(LayoutRegistryInterface $registry, ContextInterface $context)
    {
        $this->registry = $registry;
        $this->context = $context;
    }

    /**
     * {@inheritdoc}
     */
    public function get($name)
    {
        return $this->offsetGet($name);
    }

    /**
     * {@inheritdoc}
     */
    public function offsetGet($name)
    {
        $dataProvider = $this->getDataProvider($name);
        if (null !== $dataProvider) {
            return $dataProvider;
        }
        if ($this->context->data()->has($name)) {
            return $this->context->data()->get($name);
        }

        throw new InvalidArgumentException(sprintf('Could not load the data provider "%s".', $name));
    }

    /**
     * {@inheritdoc}
     */
    public function offsetExists($name)
    {
        return
            null !== $this->getDataProvider($name)
            || $this->context->data()->has($name);
    }

    /**
     * {@inheritdoc}
     */
    public function offsetSet($name, $value)
    {
        throw new \BadMethodCallException('Not supported');
    }

    /**
     * {@inheritdoc}
     */
    public function offsetUnset($name)
    {
        throw new \BadMethodCallException('Not supported');
    }

    private function getDataProvider(string $name): ?object
    {
        if (\array_key_exists($name, $this->dataProviders)) {
            return $this->dataProviders[$name];
        }

        $dataProvider = $this->registry->findDataProvider($name);
        if (null !== $dataProvider) {
            $dataProvider = new DataProviderDecorator($dataProvider, ['get', 'has', 'is']);
        }
        $this->dataProviders[$name] = $dataProvider;

        return $dataProvider;
    }
}
