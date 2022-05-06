<?php

namespace Drupal\shp_roles\ParamConverter;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\ParamConverter\EntityConverter;
use Drupal\Core\ParamConverter\ParamNotConvertedException;
use Drupal\Core\TypedData\TranslatableInterface;
use Symfony\Component\Routing\Route;

/**
 * Parameter converter for upcasting entity IDs to full objects.
 */
class UserNameConverter extends EntityConverter {

  /**
   * {@inheritdoc}
   */
  public function convert($value, $definition, $name, array $defaults) {
    $entity_type_id = $this->getEntityTypeFromDefaults($definition, $name, $defaults);
    if ($storage = $this->entityTypeManager->getStorage($entity_type_id)) {
      $entities = $storage->loadByProperties(['name' => $value]);
      $entity = reset($entities);
      // If the entity type is translatable, ensure we return the proper
      // translation object for the current context.
      if ($entity instanceof EntityInterface && $entity instanceof TranslatableInterface) {
        $entity = $this->entityRepository->getTranslationFromContext($entity, NULL, ['operation' => 'entity_upcast']);
      }
      // Per interface, param converters return NULL when not found.
      return $entity ?: NULL;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function applies($definition, $name, Route $route) {
    if (!empty($definition['type']) && strpos($definition['type'], 'username:user') === 0) {
      $entity_type_id = 'user';
      if (strpos($definition['type'], '{') !== FALSE) {
        $entity_type_slug = substr($entity_type_id, 1, -1);
        return $name !== $entity_type_slug && in_array($entity_type_slug, $route->compile()->getVariables(), TRUE);
      }
      return $this->entityTypeManager->hasDefinition($entity_type_id);
    }
    return FALSE;
  }

  /**
   * Determines the entity type ID given a route definition and route defaults.
   *
   * @param mixed $definition
   *   The parameter definition provided in the route options.
   * @param string $name
   *   The name of the parameter.
   * @param array $defaults
   *   The route defaults array.
   *
   * @return string
   *   The entity type ID.
   *
   * @throws \Drupal\Core\ParamConverter\ParamNotConvertedException
   *   Thrown when the dynamic entity type is not found in the route defaults.
   */
  protected function getEntityTypeFromDefaults($definition, $name, array $defaults) {
    $entity_type_id = 'user';

    // If the entity type is dynamic, it will be pulled from the route defaults.
    if (strpos($entity_type_id, '{') === 0) {
      $entity_type_slug = substr($entity_type_id, 1, -1);
      if (!isset($defaults[$entity_type_slug])) {
        throw new ParamNotConvertedException(sprintf('The "%s" parameter was not converted because the "%s" parameter is missing', $name, $entity_type_slug));
      }
      $entity_type_id = $defaults[$entity_type_slug];
    }
    return $entity_type_id;
  }

}
