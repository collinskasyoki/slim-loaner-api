<?php

class LoanerApi {
  /**
  * Stores an instance of the Slim app
  *
  * @var \Slim\App
  */
  private $app;

  public function __construct () {
    require __DIR__ . '/../vendor/autoload.php';

    // Instantiate the app
    $settings = require __DIR__ . '/../src/settings.php';
    $app = new \Slim\App($settings);

    // Set up dependencies
    require __DIR__ . '/../src/dependencies.php';

    // Register middleware
    require __DIR__ . '/../src/middleware.php';

    // Register routes
    require __DIR__ . '/../src/routes.php';

    $this->app = $app;
  }

  /**
  * Get an instance of the App
  *
  * @return \Slim\App
  */
  public function get () {
    return $this->app;
  }
}