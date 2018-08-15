<?php
use Illuminate\Database\Capsule\Manager as Capsule;
use Respect\Validation\Validator as v;

// DIC configuration

$container = $app->getContainer();

// view renderer
$container['renderer'] = function ($c) {
    $settings = $c->get('settings')['renderer'];
    return new Slim\Views\PhpRenderer($settings['template_path']);
};

// monolog
$container['logger'] = function ($c) {
    $settings = $c->get('settings')['logger'];
    $logger = new Monolog\Logger($settings['name']);
    $logger->pushProcessor(new Monolog\Processor\UidProcessor());
    $logger->pushHandler(new Monolog\Handler\StreamHandler($settings['path'], $settings['level']));
    return $logger;
};

// Bootstrap Eloquent
$capsule = new Capsule;
$capsule->addConnection($container['settings']['db']);
$capsule->setAsGlobal();
$capsule->bootEloquent();

// Validations
$container ['memberValidation'] = function () {
  $nameValidator = v::stringType()->notBlank()->max(500);
  $idNumberValidator = v::numeric()->notBlank();

  $validators = [
    'name' => $nameValidator,
    'id_no'=> $idNumberValidator
  ];

  return new \DavidePastore\Slim\Validation\Validation($validators);
};
