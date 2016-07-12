<?php

namespace Drupal\ua_sm_custom\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use \DateTime;
use \DateTimeZone;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Controller to list all backups for a site.
 */
class SiteBackupListController extends ControllerBase {

  /**
   * {@inheritdoc}
   */
  public function index(NodeInterface $node) {
    if (is_null($node) || $node->getType() !== "ua_sm_site") {
      throw new NotFoundHttpException();
    }

    // With the site id.
    $backups = [];
    $environments = \Drupal::service('ua_sm_custom.backup')->get($node->id());
    foreach ($environments as $environment) {
      $environment_name = Node::load($environment)->getTitle();
      $backup_dates = \Drupal::service('ua_sm_custom.backup')->get($node->id(), $environment);
      // Make table rows from each of the backup dates returned.
      foreach ($backup_dates as $date) {
        $converted_date = DateTime::createFromFormat('U', $date)
          ->setTimezone(new DateTimeZone(date_default_timezone_get()))
          ->format('Y-m-d H:i:s');
        $backups[] = [
          $environment_name,
          // Convert to a readable time format.
          $converted_date
        ];
      }
    }

    $output = [
      '#type' => 'table',
      '#header' => [
        t('Environment Name'),
        t('Backup')
      ],
      '#rows' => $backups,
      '#attributes' => [
        'class' => ['c-table']
      ]
    ];
    return $output;
  }

  /**
   * Callback to get page title for the name of the site.
   *
   * @param \Drupal\node\NodeInterface $node
   *   Site node.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   Translated markup.
   */
  public function getPageTitle(NodeInterface $node = NULL) {
    return t('Backups for @site_title', ['@site_title' => $node->getTitle()]);
  }

}
