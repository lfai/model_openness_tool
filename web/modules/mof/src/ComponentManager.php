<?php

declare(strict_types=1);

namespace Drupal\mof;

use Drupal\mof\ComponentManagerInterface;
use Drupal\mof\Component;
use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * ComponentManager class.
 */
final class ComponentManager implements ComponentManagerInterface {

  /** @var \Drupal\mof\Component[] */
  private array $components;

  /**
   * Construct a ComponentManager instance.
   */
  public function __construct(ConfigFactoryInterface $config_factory) {
    foreach ($config_factory
      ->get('mof.settings')
      ->get('components') as $component) {
      $this->components[] = new Component($component);
    }
  }

  /**
   * Get all model components sorted by weight.
   */
  public function getComponents(): array {
    usort($this->components, fn($a, $b) => $a->weight - $b->weight);
    return $this->components;
  }

  /**
   * Get component by name.
   */
  public function getComponentByName(string $name): Component {
    $key = array_search($name, array_column($this->components, 'name'));
    return $this->components[$key];
  }

  /**
   * Get a single component by ID.
   */
  public function getComponent(int $component_id): Component {
    return current(array_filter($this->components, fn($c) => $c->id === $component_id));
  }

  /**
   * Get component IDs required for the specified class.
   */
  public function getRequired(int $class): array {
    $components = array_filter($this->components, fn($c) => $c->class === $class && $c->required === true);
    return array_column($components, 'id');
  }

}

