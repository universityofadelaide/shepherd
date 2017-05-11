<?php

namespace Drupal\shp_orchestration\Plugin\OrchestrationProvider;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\shp_orchestration\OrchestrationProviderBase;
use Maclof\Kubernetes\Client as KubernetesClient;
use GuzzleHttp\Client;
use Maclof\Kubernetes\Models\Secret;
use Maclof\Kubernetes\Models\Service;
use Maclof\Kubernetes\Models\Pod;
use Maclof\Kubernetes\Models\ReplicationController;

/**
 * Class OpenShiftOrchestrationProvider.
 *
 * @OrchestrationProvider(
 *   id = "openshift_orchestration_provider",
 *   name = "OpenShift",
 *   description = @Translation("OpenShift provider to perform orchestration tasks"),
 *   schema = "openshift.orchestration_provider",
 *   config_entity_id = "openshift"
 * )
 */
class OpenShiftOrchestrationProvider extends OrchestrationProviderBase {

  protected $client;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_type_manager);

    $options = [
      'master' => $this->configEntity->endpoint,
      'token' => $this->configEntity->token,
      'namespace' => $this->configEntity->namespace,
    ];

    $guzzle_options = [
     'verify' => TRUE,
      'base_uri' => $this->configEntity->endpoint,
      'headers' => [
        'Authorization' => 'Bearer ' . $this->configEntity->token
      ],
    ];

    if ($this->configEntity->mode === "dev") {
      // Turn off SSL cert verification for development.
      $guzzle_options['verify'] = FALSE;
    }

    $this->client = new KubernetesClient($options, new Client($guzzle_options));

  }

  /**
   * Returns the Kubernetes Client.
   *
   * @return \Maclof\Kubernetes\Client
   *   Kubernetes client.
   */
  public function getClient() {
    return $this->client;
  }

  /**
   * {@inheritdoc}
   */
  public function getSecret($name) {
    return $this->client->secrets()->setFieldSelector([
      'metadata.name' => $name
    ])->find();
  }

  /**
   * {@inheritdoc}
   */
  public function createSecret($name, array $data) {
    $config = [
      'metadata' => [
        'name' => $name
      ],
      'type' => 'Opaque',
      'data' => $data
    ];

    $secret = new Secret($config);

    return $this->client->secrets()->create($secret);
  }

  /**
   * {@inheritdoc}
   */
  public function updateSecret($name, array $data) {
    $config = [
      'metadata' => [
        'name' => $name
      ],
      'type' => 'Opaque',
      'data' => $data,
    ];

    $secret = new Secret($config);

    return $this->client->secrets()->update($secret);
  }

  /**
   * {@inheritdoc}
   */
  public function getService($name) {
    // @todo only finds by name.
    // @todo test first to see if exists.
    return $this->client->services()->setFieldSelector([
      'metadata.name' => $name
    ])->find();
  }

  /**
   * {@inheritdoc}
   */
  public function createService($name, array $data) {

    $config = [
      'metadata' => [
        'name' => $name,
        'labels' => [
          'name' => $name
        ],
      ],
      'spec' => [
        'ports' => [
          [
            // @todo Not actually passed through yet, nor defined.
            "protocol" => $data['protocol'] || 'TCP',
            "port" => $data['src_port'],
            "targetPort" => $data['target_port']
          ]
        ],
        'selector' => [
          "name" => $name
        ]
      ],
      'status' => [
        'loadBalancer' => []
      ]
    ];

    $service = new Service($config);

    return $this->client->services()->create($service);

  }

  /**
   * {@inheritdoc}
   */
  public function getPods($name) {
    return $this->client->pods()->setFieldSelector([
      'metadata.name' => $name
    ])->find();
  }

  /**
   * {@inheritdoc}
   */
  public function createPod($name, array $config) {
    $config = [
      'metadata' => [
        'name' => $name,
        'labels' => [
          'name' => $name,
        ],
      ],
      'spec' => [
        'replicas' => 1,
        'template' => [
          'metadata' => [
            'labels' => [
              'name' => $name,
            ],
          ],
          'spec' => [
            'containers' => [
              [
                'name'  => $config['container_name'],
                'image' => $config['image'],
                'ports' => [
                  [
                    'containerPort' => $config['port'] || '',
                    'protocol'      => 'TCP',
                  ],
                ],
              ],
            ],
          ],
        ],
      ],
    ];

    $replication_controller = new ReplicationController($config);

    return $this->client->replicationControllers()->create($replication_controller);
  }

}
