<?php

/**
 * @file
 * Contains \DrupalProject\composer\ScriptHandler.
 */

namespace DrupalProject\composer;

use Composer\Script\Event;
use Composer\Semver\Comparator;
use Symfony\Component\Filesystem\Filesystem;

class ScriptHandler {

  protected static function getDrupalRoot($project_root) {
    return $project_root . '/web';
  }

  public static function createRequiredFiles(Event $event) {
    $fs = new Filesystem();
    $root = static::getDrupalRoot(getcwd());

    $dirs = [
      'modules',
      'profiles',
      'themes',
    ];

    // Required for unit testing
    foreach ($dirs as $dir) {
      if (!$fs->exists($root . '/'. $dir)) {
        $fs->mkdir($root . '/'. $dir);
        $fs->touch($root . '/'. $dir . '/.gitkeep');
      }
    }

    // Prepare the settings file for installation
    if (!$fs->exists($root . '/sites/default/settings.php') and $fs->exists($root . '/sites/default/default.settings.php')) {
      $fs->copy($root . '/sites/default/default.settings.php', $root . '/sites/default/settings.php');
      $fs->chmod($root . '/sites/default/settings.php', 0666);
      $event->getIO()->write("Create a sites/default/settings.php file with chmod 0666");

      // Append Shepherd-specific environment variable settings to settings.php.
      file_put_contents(
        $root . '/sites/default/settings.php',
        "\n/**\n * START SHEPHERD CONFIG\n */\n" .
        "\$databases['default']['default'] = array (\n" .
        "  'database' => getenv('DATABASE_NAME'),\n" .
        "  'username' => getenv('DATABASE_USER'),\n" .
        "  'password' => getenv('DATABASE_PASSWORD_FILE') ? file_get_contents(getenv('DATABASE_PASSWORD_FILE')) : getenv('DATABASE_PASSWORD'),\n" .
        "  'host' => getenv('DATABASE_HOST'),\n" .
        "  'port' => getenv('DATABASE_PORT') ?: '3306',\n" .
        "  'driver' => getenv('DATABASE_DRIVER') ?: 'mysql',\n" .
        "  'prefix' => getenv('DATABASE_PREFIX') ?: '',\n" .
        "  'collation' => getenv('DATABASE_COLLATION') ?: 'utf8mb4_general_ci',\n" .
        "  'namespace' => getenv('DATABASE_NAMESPACE') ?: 'Drupal\\\\Core\\\\Database\\\\Driver\\\\mysql',\n" .
        ");\n" .
        "\$settings['file_private_path'] = getenv('PRIVATE_DIR');\n" .
        "\$settings['hash_salt'] = getenv('HASH_SALT') ?: '" . str_replace(['+', '/', '='], ['-', '_', ''], base64_encode(random_bytes(55))) . "';\n" .
        "\$config_directories['sync'] = getenv('CONFIG_SYNC_DIRECTORY') ?: 'sites/default/files/config_" . str_replace(['+', '/', '='], ['-', '_', ''], base64_encode(random_bytes(55))) . "/sync';\n" .
        "if (! is_dir(\$app_root . '/' . \$config_directories['sync'])) mkdir(\$app_root . '/' . \$config_directories['sync'], 0777, true);\n" .
        "/**\n * END SHEPHERD CONFIG\n */\n\n",
        FILE_APPEND
      );

      // Append inclusion of settings.local.php to settings.php.
      file_put_contents(
        $root . '/sites/default/settings.php',
        "/**\n * START LOCAL CONFIG\n */\n" .
        "if (file_exists(__DIR__ . '/settings.local.php')) {\n" .
        "  include __DIR__ . '/settings.local.php';\n" .
        "}\n" .
        "/**\n * END LOCAL CONFIG\n */\n\n",
        FILE_APPEND
      );

      $event->getIO()->write("Added Shepherd env var parsing to sites/default/settings.php file");
    }

    // Prepare the services file for installation
    if (!$fs->exists($root . '/sites/default/services.yml') and $fs->exists($root . '/sites/default/default.services.yml')) {
      $fs->copy($root . '/sites/default/default.services.yml', $root . '/sites/default/services.yml');
      $fs->chmod($root . '/sites/default/services.yml', 0666);
      $event->getIO()->write("Create a sites/default/services.yml file with chmod 0666");
    }
  }

  /**
   * Checks if the installed version of Composer is compatible.
   *
   * Composer 1.0.0 and higher consider a `composer install` without having a
   * lock file present as equal to `composer update`. We do not ship with a lock
   * file to avoid merge conflicts downstream, meaning that if a project is
   * installed with an older version of Composer the scaffolding of Drupal will
   * not be triggered. We check this here instead of in drupal-scaffold to be
   * able to give immediate feedback to the end user, rather than failing the
   * installation after going through the lengthy process of compiling and
   * downloading the Composer dependencies.
   *
   * @see https://github.com/composer/composer/pull/5035
   */
  public static function checkComposerVersion(Event $event) {
    $composer = $event->getComposer();
    $io = $event->getIO();

    $version = $composer::VERSION;

    // The dev-channel of composer uses the git revision as version number,
    // try to the branch alias instead.
    if (preg_match('/^[0-9a-f]{40}$/i', $version)) {
      $version = $composer::BRANCH_ALIAS_VERSION;
    }

    // If Composer is installed through git we have no easy way to determine if
    // it is new enough, just display a warning.
    if ($version === '@package_version@' || $version === '@package_branch_alias_version@') {
      $io->writeError('<warning>You are running a development version of Composer. If you experience problems, please update Composer to the latest stable version.</warning>');
    }
    elseif (Comparator::lessThan($version, '1.0.0')) {
      $io->writeError('<error>Drupal-project requires Composer version 1.0.0 or higher. Please update your Composer before continuing</error>.');
      exit(1);
    }
  }

}