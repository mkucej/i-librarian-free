<?php

namespace Librarian\Container;

use Exception;

/**
 * Dependency injection class.
 */
final class DependencyInjector {

    /**
     * @var array Closures with class definitions.
     */
    private array $definitions;

    /**
     * @var array Registry of instantiated singletons.
     */
    private array $objects;

    /**
     * Save a class definition in the DI container.
     *
     * @param  string   $name
     * @param  callable $definition
     */
    public function set(string $name, callable $definition) {

        $this->definitions[$name] = $definition;
    }

    /**
     * Instantiate an object from the definition.
     *
     * @param  string       $name
     * @param  string|array $arguments Optional arguments for injected class.
     * @param  boolean      $shared    Set true for singletons.
     * @return mixed
     * @throws Exception
     */
    public function get(string $name, $arguments = null, bool $shared = false) {

        // Calls was not set before.
        if (isset($this->definitions[$name]) === false) {

            throw new Exception("class <kbd>$name</kbd> is not registered", 500);
        }

        // Can't instantiate singletons repeatedly with different arguments.
        if ($shared === true && $this->has($name) === true && !empty($arguments)) {

            throw new Exception("shared object $name already instantiated with different arguments", 500);
        }

        // Clean up args.
        $params_a = $arguments ?? [];
        $params_b = is_array($params_a) === true ? $params_a : [$params_a];

        if ($shared) {

            // Singletons.
            if ($this->has($name) === false) {

                $this->objects[$name] = call_user_func_array($this->definitions[$name], $params_b);
            }

            return $this->objects[$name];

        } else {

            // Regular objects.
            return call_user_func_array($this->definitions[$name], $params_b);
        }
    }

    /**
     * Instantiate a shared object (singleton) from the definition.
     *
     * @param  string       $name
     * @param  string|array $arguments Optional arguments for injected class.
     * @return mixed
     * @throws Exception
     */
    public function getShared(string $name, $arguments = null) {

        return $this->get($name, $arguments, true);
    }

    /**
     * Is the service instantiated?
     *
     * @param  string $name
     * @return boolean
     */
    public function has(string $name): bool {

        return isset($this->objects[$name]);
    }
}
