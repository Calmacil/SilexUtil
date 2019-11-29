<?php
namespace Calma\SilexUtils;

if (!defined('ROOT')) {
  define('ROOT', __DIR__ . '/../../../');
}

/**
 * This trait adds YML config reading capabilities to a class implementing the ArrayAcces interface. It should be used
 * in a class extending the \Silex\Application class.
 * Note that a ROOT constant should be declared in the bootstrap php file
 * 
 * @author calmacil
 */
trait AppConfigTrait {
  
  /**
   * Inits the whole application
   * 
   * @param string $path  The path of the settings file.
   * @return void
   */
  public function init(string $path = null): void
  {
    if (!isset($this->rootNamespace)) {
      $this->rootNamespace = "Calma";
    }
    
    $this->loadConfig($path);
    $this->loadRoutes();
    $this->loadServices();
  }
  
  /**
   * Loads the settings file
   * 
   * @param string $path The path of the settings file. Defaults to ROOT/config/settings{_env}.yml
   * @return void
   */
  private function loadConfig(string $path = null): void
  {
    $file = ROOT . '/config/settings' . $this->environment . '.yml';
    if ($path) {
      $file = ROOT . $path;
    }

    $yml = yaml_parse_file($file);

    array_walk($yml, 'self::compileSettings');

    foreach ($yml as $key => $val) {
      $this->offsetSet($key, $val);
    }
  }

  /**
   * Loads the routes
   * 
   * @param string $path The path of the routing file. Defaults to ROOT/config/routes.yml
   * @return void
   */
  private function loadRoutes(string $path = null): void
  {
    $file = ROOT . '/config/routes.yml';
    if ($path) {
      $file = ROOT . $path;
    }
    
    $yml = yaml_parse_file($file);

    foreach ($yml as $bind_name => $route) {

      $method = 'GET';
      if (array_key_exists('method', $route)) {
        $method = $route['method'];
      }

      $ctrl = $this->match($route['pattern'], $this->getClassName($route['controller']) . "::{$route['action']}Action");

      if (array_key_exists('params', $route)) {
        foreach ($route['params'] as $param => $assertion) {
          if (is_array($assertion) && array_key_exists('converter', $assertion) && $this->offsetExists(implode(':', $assertion['converter']))) {
            $ctrl->convert($param, $assertion['converter']['class'] . ':' . $assertion['converter']['method']);
          } else {
            $ctrl->assert($param, $assertion);
          }
        }
      }
      $ctrl->method($method);
      $ctrl->bind($bind_name);
    }
  }

  /**
   * Loads the services list
   * 
   * @param string $path The path of the services file. Defaults to ROOT/config/services.yml
   * @return void
   */
  private function loadServices(string $path = null): void
  {
    $file = ROOT . '/config/services.yml';
    if ($path) {
      $file = ROOT.$path;
    }
    
    $yml = yaml_parse_file($file);

    foreach ($yml as $service_name => $service) {
      $class = $this->getClassName($service);
      $this->offsetSet($service_name, function($app) use ($class) {
        return new $class($app);
      });
    }
  }

  // FIXME set up project root namespace instead of Nephilim
  private function getClassName(string $namespace): string
  {
    return "\\" . $this->rootNamespace . "\\" . str_replace('/', '\\', $namespace);
  }

  /**
   * Callback for settings loading
   */
  private function compileSettings(&$item, $key)
  {
    if (is_array($item)) {
      array_walk($item, 'self::compileSettings');
    } else {
      $item = str_replace('ROOT', ROOT, $item);
    }
  }
}
