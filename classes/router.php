<?php

namespace Librarian;

use Exception;
use Librarian\Container\DependencyInjector;
use Librarian\Security\Validation;

/**
 * Class Router.
 *
 * Parse URI into a route and dispatch the controller and action.
 */
final class Router {

    /**
     * @var DependencyInjector We need the DI for controller instantiation.
     */
    private DependencyInjector $di;

    /**
     * @var array Array of route components.
     */
    public array $route = [];

    /**
     * @var Validation
     */
    private Validation $validation;

    /**
     * Constructor.
     *
     * @param DependencyInjector $di
     * @param Validation $validation
     */
    function __construct(DependencyInjector $di, Validation $validation) {

        $this->di         = $di;
        $this->validation = $validation;
    }

    /**
     * Parse URL path into a controller route.
     *
     * IL_PATH_URL valid forms:
     * controller(?query)
     * controller/action(?query)
     *
     * @return void
     * @throws Exception
     */
    public function parse(): void {

        // Split PATH_INFO it by / into components.
        $path_components = explode('/', IL_PATH_URL);

        switch (count($path_components)) {

            // Empty path.
            case 0:
                $this->route['controller'] = 'main';
                $this->route['action']     = 'main';
                break;

            // Only the controller was specified.
            case 1:
                $this->route['controller'] = empty($path_components[0]) ? 'main' : $path_components[0];
                $this->route['action']     = 'main';
                break;

            // Both the controller and action were specified.
            case 2:
                $this->route['controller'] = empty($path_components[0]) ? 'main' : $path_components[0];
                $this->route['action']     = empty($path_components[1]) ? 'main' : $path_components[1];
                break;

            // Everything else is invalid.
            default:
                throw new Exception('invalid URL request', 400);
        }

        // Validate. Path components must be alphanumerical.
        foreach ($this->route as $route_part) {

            $this->validation->alphanum($route_part);
        }
    }

    /**
     * Dispatch the controller and the action.
     *
     * @return string
     * @throws Exception
     */
    public function dispatch(): string {

        // Assemble the controller class name.
        $controller_name = '\\LibrarianApp\\' . ucfirst($this->route['controller']) . "Controller";

        // Instantiate the controller.
        $controller = new $controller_name($this->di);

        // Does action exist?
        $method_name = $this->route['action'] . 'Action';

        if (method_exists($controller, $method_name) === false) {

            // URL route has been validated @ row 86, so we can safely output it in an error message.
            throw new Exception("page action <kbd>{$this->route['action']}</kbd> is undefined", 404);
        }

        // Call the controller action.
        return $controller->{$method_name}();
    }
}
