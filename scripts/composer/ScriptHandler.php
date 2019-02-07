<?php

namespace UniversityOfAdelaide\Shepherd;

use UniversityOfAdelaide\ShepherdDrupalScaffold\Handler;

class ScriptHandler extends Handler
{

    /**
     * Update the Shepherd scaffold files.
     *
     * Shepherd has customised the following files:
     * dsh
     *
     */
    public function updateShepherdScaffoldFiles()
    {
        $packagePath = $this->getPackagePath();
        $projectPath = $this->getProjectPath();

        // Always copy and replace these files.
        $this->copyFiles(
            $packagePath,
            $projectPath,
            [
//                'dsh',
                'RoboFileBase.php',
            ],
            true
        );

        // Only copy these files if they do not exist at the destination.
        $this->copyFiles(
            $packagePath,
            $projectPath,
            [
                'docker-compose.linux.yml',
                'docker-compose.osx.yml',
                'RoboFile.php',
                'drush/config-ignore.yml',
                'drush/config-delete.yml',
                'phpcs.xml',
            ]
        );
    }

}
