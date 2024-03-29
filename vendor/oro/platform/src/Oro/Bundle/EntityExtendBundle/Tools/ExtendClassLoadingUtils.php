<?php

namespace Oro\Bundle\EntityExtendBundle\Tools;

use Oro\Component\PhpUtils\OneFileClassLoader;

/**
 * A set of reusable static methods related to extended entity proxy classes registration.
 */
class ExtendClassLoadingUtils
{
    /**
     * Returns the class namespace of extended entities.
     *
     * @return string
     */
    public static function getEntityNamespace()
    {
        return 'Extend\Entity';
    }

    /**
     * Returns base cache directory where all data for extended entities should be located.
     *
     * @param string $cacheDir
     * @return string
     */
    public static function getEntityBaseCacheDir($cacheDir)
    {
        return $cacheDir . DIRECTORY_SEPARATOR . 'oro_entities' . DIRECTORY_SEPARATOR. 'Extend';
    }

    /**
     * Returns directory where extended entities should be located.
     *
     * @param string $cacheDir
     * @return string
     */
    public static function getEntityCacheDir($cacheDir)
    {
        return self::getEntityBaseCacheDir($cacheDir) . DIRECTORY_SEPARATOR . 'Entity';
    }

    /**
     * Returns directory where extended entities should be located.
     *
     * @param string $cacheDir
     * @return string
     */
    public static function getEntityClassesPath($cacheDir)
    {
        return self::getEntityCacheDir($cacheDir) . DIRECTORY_SEPARATOR . 'classes.php';
    }

    /**
     * Returns a path of a configuration file contains class aliases for extended entities.
     *
     * @param string $cacheDir
     * @return string
     */
    public static function getAliasesPath($cacheDir)
    {
        return self::getEntityCacheDir($cacheDir) . DIRECTORY_SEPARATOR . 'alias.data';
    }

    /**
     * Checks if a configuration file contains class aliases for extended entities exists.
     *
     * @param string $cacheDir
     * @return bool
     */
    public static function aliasesExist($cacheDir)
    {
        return file_exists(self::getAliasesPath($cacheDir));
    }

    /**
     * Registers the namespace of extended entities on the SPL autoload stack.
     *
     * @param string $cacheDir
     */
    public static function registerClassLoader($cacheDir)
    {
        $loader = new OneFileClassLoader(
            self::getEntityNamespace() . '\\',
            self::getEntityClassesPath($cacheDir)
        );
        $loader->register();
    }

    /**
     * Sets class aliases for extended entities.
     *
     * @param string $cacheDir
     */
    public static function setAliases($cacheDir)
    {
        $aliases = self::getAliases($cacheDir);
        foreach ($aliases as $className => $alias) {
            if (class_exists($className) && !class_exists($alias, false)) {
                class_alias($className, $alias);
            }
        }
    }

    /**
     * Gets class aliases for extended entities.
     *
     * @param string $cacheDir
     * @return array
     */
    public static function getAliases($cacheDir)
    {
        $aliases = @include self::getAliasesPath($cacheDir);
        if (false === $aliases || !\is_array($aliases)) {
            $aliases = [];
        }

        return $aliases;
    }

    /**
     * Checks if directory exists and attempts to create it if it doesn't exist.
     *
     * @param string $dir
     *
     * @throws \RuntimeException if directory creation failed
     */
    public static function ensureDirExists($dir)
    {
        if (!is_dir($dir) && false === @mkdir($dir, 0777, true)) {
            throw new \RuntimeException(sprintf('Could not create cache directory "%s".', $dir));
        }
    }
}
