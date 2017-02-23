<?php

namespace Drupal\shp_custom\Controller;

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
    if (is_null($node) || $node->getType() !== "shp_site") {
      throw new NotFoundHttpException();
    }

    $formatted_backups = [];
    $backups = \Drupal::service('shp_custom.backup')->getAll($node->id());
    foreach ($backups as $backup) {
      // Make table rows from each of the backup dates returned.
      if ($environment = Node::load($backup['environment'])) {
        $environment_name = $environment->getTitle();
      }
      else {
        $environment_name = 'Env:' . $backup['environment'];
      }

      $converted_date = \Drupal::service('date.formatter')->format($backup['backup']);
      $formatted_backups[] = [
        $environment_name,
        // Convert to a readable time format.
        $converted_date,
      ];
    }

    $output = [
      '#type' => 'table',
      '#header' => [
        t('Environment Name'),
        t('Backup'),
      ],
      '#rows' => $formatted_backups,
      '#attributes' => [
        'class' => ['c-table'],
      ],
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
