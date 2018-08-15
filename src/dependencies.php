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

$container['membersController'] = function ($c) {
  return new MembersController($c->memberValidation);//$c->memberValidation);
};

// Member Module Validations
$container ['memberValidation'] = function () {
  $nameValidator = v::stringType()->notBlank()->length(null, 500)
    ->setTemplate('Please Enter a Valid Name');
  $idNumberValidator = v::numeric()->notBlank()
    ->setTemplate('Please Enter a Valid ID Number');
  $nextNameValidator = v::stringType()->notBlank()->length(null, 500)
    ->setTemplate('Please Enter a Valid Name');
  $phoneValidator = v::numeric()->notBlank()
    ->setTemplate('Please Enter a Valid Phone Number');
  $genderValidator = v::in(['male', 'female', 'other'])->notBlank()
    ->setTemplate('Please Choose a Valid Gender["male", "female", "other"]');
  $dateValidator = v::date()->notBlank()
    ->setTemplate('Please Enter a Valid Date');
  $registrationFeeValidator = v::numeric()->notBlank()
    ->setTemplate('Please Enter a Valid Amount');

  $validators = [
    'name' => $nameValidator,
    'id_no'=> $idNumberValidator,
    'next_kin_name' => $nextNameValidator,
    'next_kin_phone' => $phoneValidator,
    'next_kin_id' => $idNumberValidator,
    'gender' => $genderValidator,
    'phone' => $phoneValidator,
    'registered_date' => $dateValidator,
    'registration_fee' => $registrationFeeValidator,
  ];

  return new \DavidePastore\Slim\Validation\Validation($validators);
};

