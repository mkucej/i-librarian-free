<?php

namespace Librarian;

use Exception;

final class Loader {

    /**
     * Register autoloader.
     *
     * @return void
     */
    public function register(): void {

        spl_autoload_register([$this, "autoLoad"]);
    }

    /**
     * Autoloader.
     *
     * @param string $class
     * @return void
     * @throws Exception
     */
    private function autoLoad(string $class): void {

        if (strpos($class, 'Librarian\\') === 0) {

            /*
             * Framework classes are in IL_CLASS_PATH.
             */

            $className = str_replace('Librarian', '', $class);
            $className = str_replace('\\', DIRECTORY_SEPARATOR, strtolower($className));

            require IL_CLASS_PATH . "{$className}.php";

        } elseif (strpos($class, 'LibrarianApp\\') === 0) {

            /*
             * App classes are in IL_APP_PATH.
             */

            $class2 = str_replace('LibrarianApp', '', $class);
            $class2 = str_replace('\\', DIRECTORY_SEPARATOR, strtolower($class2));
            $className = basename($class2);

            if (substr($class, -10) === 'Controller') {

                $classPath = 'controllers' . DIRECTORY_SEPARATOR . substr($className, 0, -10);

            } elseif (substr($class, -4) === 'View') {

                $classPath = 'views' . DIRECTORY_SEPARATOR . substr($className, 0, -4);

            } elseif (substr($class, -5) === 'Model') {

                $classPath = 'models' . DIRECTORY_SEPARATOR . substr($className, 0, -5);

            } else {

                throw new Exception("invalid class name");
            }

            $class_file = IL_APP_PATH . DIRECTORY_SEPARATOR . "{$classPath}.php";

            if (!is_readable($class_file)) {

                $basename = basename($class_file, '.php');

                // In case of URL route, it has been sanitized, so we can safely output it in error message.
                throw new Exception("page <kbd>$basename</kbd> not found", 404);
            }

            require $class_file;
        }
    }
}
