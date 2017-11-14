<?php

namespace Drupal\Tests\shp_orchestration;

use Drupal\shp_orchestration\Exception\JobInProgressException;
use Drupal\shp_orchestration\Service\ActiveJobManager;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the ActiveJobManagerService class.
 *
 * @group ua
 * @group shepherd
 * @group shp_orchestration
 * @coversDefaultClass \Drupal\shp_orchestration\Service\ActiveJobManager
 */
class ActiveJobManagerTest extends UnitTestCase {

  /**
   * State service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * A simple test job.
   *
   * @var \stdClass
   */
  protected $job;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->state = $this->getMock('Drupal\Core\State\StateInterface');

    $this->job = (object) [
      'jobId' => '123',
      'entityId' => '456',
      'queueWorker' => 'Environment',
    ];
  }

  /**
   * Test the add method.
   */
  public function testAddJob() {
    $this->state->expects($this->once())
      ->method('get')
      ->with(ActiveJobManager::STATE_KEY_PREFIX . $this->job->entityId)
      ->will($this->returnValue(NULL));

    $this->state->expects($this->once())
      ->method('set')
      ->with(ActiveJobManager::STATE_KEY_PREFIX . $this->job->entityId, $this->job);

    $activeJobManager = new ActiveJobManager($this->state);

    $activeJobManager->add($this->job);
  }

  /**
   * Test the add method.
   */
  public function testAddDuplicateJob() {
    $this->setExpectedException('Drupal\shp_orchestration\Exception\JobInProgressException');
    $this->state->expects($this->once())
      ->method('get')
      ->willThrowException(new JobInProgressException('A job is already in progress for this environment.'));

    $activeJobManager = new ActiveJobManager($this->state);

    $activeJobManager->add($this->job);
  }

  /**
   * Test the remove job method.
   */
  public function testRemoveJob() {
    $this->state->set(ActiveJobManager::STATE_KEY_PREFIX . $this->job->entityId, $this->job);

    $this->state->expects($this->once())
      ->method('delete')
      ->with(ActiveJobManager::STATE_KEY_PREFIX . $this->job->entityId);

    $activeJobManager = new ActiveJobManager($this->state);

    $activeJobManager->remove($this->job->entityId);
  }

  /**
   * Test the get job method.
   */
  public function testGetMethod() {
    $this->state->set(ActiveJobManager::STATE_KEY_PREFIX . $this->job->entityId, $this->job);

    $this->state->expects($this->once())
      ->method('getMultiple')
      ->with([ActiveJobManager::STATE_KEY_PREFIX . $this->job->entityId])
      ->willReturn([$this->job]);

    $activeJobManager = new ActiveJobManager($this->state);

    $output = $activeJobManager->get([$this->job->entityId]);

    $this->assertEquals(
      [$this->job],
      $output,
      'The get method returned the job.'
    );
  }

  /**
   * Test that State API keys are prefixed correctly.
   */
  public function testApplyKeyPrefixMethod() {
    $jobId = '123';
    $jobIds = [$jobId];
    $activeJobManager = new ActiveJobManagerDouble($this->state);
    $this->assertArrayEquals(
      [ActiveJobManager::STATE_KEY_PREFIX . $jobId],
      $activeJobManager->applyKeyPrefix($jobIds),
      'State API key prefixed correctly.'
    );
  }

}

/**
 * Class ActiveJobManagerDouble.
 */
class ActiveJobManagerDouble extends ActiveJobManager {

  /**
   * {@inheritdoc}
   */
  public function applyKeyPrefix(array $entityIds) {
    // Overridden to change the method visibility.
    return parent::applyKeyPrefix($entityIds);
  }

}
