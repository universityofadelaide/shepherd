<?php
/**
 * @file
 * Contains UniversityOfAdelaide\ShepherdDrupalScaffold\Plugin.
 */

namespace UniversityOfAdelaide\Shepherd;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\ScriptEvents;
use Composer\Util\RemoteFilesystem;

/**
 * Composer plugin for handling Shepherd Drupal scaffold.
 */
class Plugin implements PluginInterface, EventSubscriberInterface
{

    /**
     * @var \UniversityOfAdelaide\Shepherd\ScriptHandler
     */
    protected $handler;

    /**
     * {@inheritdoc}
     */
    public function activate(Composer $composer, IOInterface $io)
    {
        $this->handler = new ScriptHandler($composer, $io);
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return array(
            // ScriptEvents::POST_INSTALL_CMD => 'postCmd',
            ScriptEvents::POST_UPDATE_CMD => 'postCmd',
        );
    }

    /**
    * Post command event callback.
    *
    * @param \Composer\Script\Event $event
    */
    public function postCmd(\Composer\Script\Event $event)
    {
        $this->handler->onPostCmdEvent($event);
    }


    /**
     * Script callback for putting in composer scripts to download the
     * scaffold files.
     *
     * @param \Composer\Script\Event $event
    */
    public static function scaffold(\Composer\Script\Event $event)
    {
        $handler = new ScriptHandler($event->getComposer(), $event->getIO());
        $handler->onPostCmdEvent($event);
    }
}
