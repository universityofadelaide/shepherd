<?php

/**
 * @file
 * Contains \Drupal\ua_sm_custom\Plugin\Validation\Constraint\SiteInstanceConstraintValidator.
 */

namespace Drupal\ua_sm_custom\Plugin\Validation\Constraint;

use Drupal\node\NodeInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Violation\ConstraintViolationBuilderInterface;
use Drupal\node\Entity\Node;

/**
 * Validates the SiteInstanceConstraint.
 */
class SiteInstanceConstraintValidator extends ConstraintValidator {

  /**
   * Validator 2.5 and upwards compatible execution context.
   *
   * @var \Symfony\Component\Validator\Context\ExecutionContextInterface
   */
  protected $context;

  /**
   * {@inheritdoc}
   */
  public function validate($data, Constraint $constraint) {
    if (!$constraint instanceof SiteInstanceConstraint) {
      throw new UnexpectedTypeException($constraint, __NAMESPACE__ . '\SiteInstanceConstraint');
    }
    // Validate against the defined entity.
    if ($data instanceof NodeInterface && ($data->bundle() == $constraint->entity)) {
      $this->validateNodeEntity($data, $constraint);
      return;
    }
  }

  /**
   * Handles validation of the environment Node entity.
   *
   * @param NodeInterface $node
   *    Node.
   * @param SiteInstanceConstraint $constraint
   *    Constraint.
   */
  protected function validateNodeEntity(NodeInterface $node, SiteInstanceConstraint $constraint) {
    // Get each of the web servers assigned to the platform.
    foreach ($node->field_ua_sm_platform->entity->field_ua_sm_web_servers->getValue() as $web_server) {
      $instances = \Drupal::entityQuery('node')
        ->condition('type', 'ua_sm_site_instance')
        ->condition('field_ua_sm_server', $web_server['target_id'])
        ->execute();
      // Check instance limit.
      $violation = $this->validateInstanceLimit($instances, Node::load($web_server['target_id']), $constraint);
      if ($violation instanceof  ConstraintViolationBuilderInterface) {
        $violation->addViolation();
      }
    }
  }

  /**
   * Handles validation of the limit.
   *
   * @param array $instances
   *   Number of instances.
   * @param NodeInterface $web_server
   *   Server entity.
   * @param \Drupal\ua_sm_custom\Plugin\Validation\Constraint\SiteInstanceConstraint $constraint
   *   The Constraint to validate against.
   *
   * @return \Symfony\Component\Validator\Violation\ConstraintViolationBuilderInterface
   *    ConstraintViolationBuilderInterface.
   */
  protected function validateInstanceLimit(array $instances, NodeInterface $web_server, SiteInstanceConstraint $constraint) {
    if (count($instances) >= $constraint->limit) {
      // Get the name of the web server.
      return $this->context->buildViolation($constraint->messageLimit, [
        '%limit' => $constraint->limit,
        '%server' => $web_server->getTitle(),
        ]);
    }
    return FALSE;
  }
}
