<?php

namespace Oro\Bundle\EntityConfigBundle\Tools\BackupManager;

/**
 * Provides an interface for services that adds the ability
 * to roll back the work result of command 'oro:entity-extend:update-config'.
 * It helps to prevent broken applications in the case when
 * update schema fell into exception while adding new fields, but these fields have already been marked as ready to use.
 */
interface EntityConfigBackupManagerInterface
{
    /**
     * @return bool
     */
    public function isEnabled();

    /**
     * @return void
     */
    public function makeBackup();

    /**
     * @return void
     */
    public function restoreFromBackup();

    /**
     * @return void
     */
    public function dropBackup();
}
