<?php
declare(strict_types=1);

namespace Drupal\mof;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Provides a list controller for the license entity type.
 */
final class LicenseListBuilder extends EntityListBuilder {

  use PageLimitTrait;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    EntityTypeInterface $entity_type,
    EntityStorageInterface $storage,
    private readonly FormBuilderInterface $formBuilder,
    private readonly Request $request
  ) {
    parent::__construct($entity_type, $storage);
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(
    ContainerInterface $container,
    EntityTypeInterface $entity_type
  ) {
    return new static(
      $entity_type,
      $container->get('entity_type.manager')->getStorage($entity_type->id()),
      $container->get('form_builder'),
      $container->get('request_stack')->getCurrentRequest()
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader(): array {
    $header = [
      'name' => [
        'data' => $this->t('Name'),
        'field' => 'name',
        'specifier' => 'name',
      ],
      'license_id' => [
        'data' => $this->t('License ID'),
        'field' => 'license_id',
        'specifier' => 'license_id',
      ],
    ];

    // Conditionally add the Operations column header for users with the administer licenses permission.
    if (\Drupal::currentUser()->hasPermission('administer licenses')) {
      $header += parent::buildHeader();
    }

    return $header;
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity): array {
    $row = [
      'name' => $entity->getName(),
      'license_id' => $entity->getLicenseId(),
    ];

    // Conditionally add the Operations column rows for users with the administer licenses permission.
    if (\Drupal::currentUser()->hasPermission('administer licenses')) {
      $row += parent::buildRow($entity);
    }

    return $row;
  }

  /**
   * {@inheritdoc}
   */
  protected function getEntityListQuery(): QueryInterface {
    $this->setPageLimit();
    $header = $this->buildHeader();

    $query = $this
      ->getStorage()
      ->getQuery()
      ->accessCheck(TRUE)
      ->tableSort($header);

    if ($name = $this->request->get('name')) {
      $name = addcslashes($name, '\\%_');
      $query->condition('name', "%{$name}%", 'LIKE');
    }

    if ($license_id = $this->request->get('license_id')) {
      $license_id = addcslashes($license_id, '\\%_');
      $query->condition('license_id', "%{$license_id}%", 'LIKE');
    }

    if ($this->limit) {
      $query->pager($this->limit);
    }

    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function render(): array|RedirectResponse {
    if (($url = $this->getPageRedirectUrl()) !== NULL) {
      return $this->redirectPage($url);
    }

    $build = parent::render();
    $build['#attached']['library'][] = 'mof/license-list';
    $build['search'] = $this->formBuilder->getForm('\Drupal\mof\Form\LicenseSearchForm');
    $build['search']['#weight'] = -100;
    $build['table']['#attributes']['class'][] = 'tablesaw';
    $build['table']['#attributes']['class'][] = 'tablesaw-stack';
    $build['table']['#attributes']['data-tablesaw-mode'] = 'stack';

    $build['#cache'] = [
      'contexts' => [
        'url.query_args:name',
        'url.query_args:license_id',
        'url.query_args:page',
        'url.query_args:limit',
        'url.query_args:sort',
        'url.query_args:order',
      ],
    ];

    return $build;
  }

}
