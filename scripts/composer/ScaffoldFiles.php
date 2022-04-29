<?php

declare(strict_types=1);

namespace UniversityOfAdelaide\ShepherdDrupalScaffold\actions;

use Composer\Script\Event;
use Symfony\Component\Filesystem\Filesystem;
use UniversityOfAdelaide\ShepherdDrupalScaffold\ScaffoldTrait;
use UniversityOfAdelaide\ShepherdDrupalScaffold\tasks\CopyFile;

/**
 * Updates the Shepherd scaffold files.
 */
final class ScaffoldFiles implements ActionInterface
{
    use ScaffoldTrait;

    public function onEvent(Event $event): void
    {
        $event->getIO()->write('Updating Shepherd scaffold files.');

        $scaffoldPath = $this->getScaffoldDirectory();
        $projectPath = $this->getProjectPath();
        foreach (static::tasks($this->filesystem, $scaffoldPath, $projectPath) as $task) {
            $task->execute();
        }
    }

    /**
     * @return \UniversityOfAdelaide\ShepherdDrupalScaffold\tasks\CopyFile[]
     */
    public static function tasks(Filesystem $filesystem, string $scaffoldPath, string $projectPath): array
    {
        return array_map(fn ($args): CopyFile => new CopyFile($filesystem, $projectPath, ...$args), [
            // Always copy and replace these files.
//            [$scaffoldPath . '/required', 'dsh', true],
            [$scaffoldPath . '/required', 'RoboFileBase.php', true],

            // Only copy these files if they do not exist at the destination.
            [$scaffoldPath . '/optional', 'docker-compose.linux.yml'],
            [$scaffoldPath . '/optional', 'docker-compose.osx.yml'],
            [$scaffoldPath . '/optional', 'dsh_bash'],
            [$scaffoldPath . '/optional', 'phpcs.xml'],
            [$scaffoldPath . '/optional', 'RoboFile.php'],
            [$scaffoldPath . '/optional', 'docker/Dockerfile'],
            [$scaffoldPath . '/optional', 'docker/xdebug.ini'],
            [$scaffoldPath . '/optional', 'docker/php_custom.ini'],
        ]);
    }
}
