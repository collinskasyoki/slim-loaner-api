<?php

use Slim\Http\Request;
use Slim\Http\Response;

// Routes

$app->get('/', function (Request $request, Response $response, array $args) {
    // Sample log message
    $this->logger->info("Slim-Skeleton '/' route");

    // Render index view
    return $this->renderer->render($response, 'index.phtml', $args);
});

$app->group('/api/v1', function () {
  $this->get('/test', '\TestController::index');

  // members routes
  $this->get('/members', '\MembersController::index');
  $this->get('/members/{id}', 'MembersController:getMember');
  $this->post('/members', 'MembersController:store')->add(
          $this->getContainer()['memberValidation']
      );
  $this->patch('/members/{id}', 'MembersController:update')->add(
          $this->getContainer()['memberValidation']
      );

  $this->get('/shares', 'SharesController:index');
  $this->get('/shares/{id}', 'SharesController:getMemberShares');
  $this->post('/shares', 'SharesController:store')->add(
          $this->getContainer()['sharesValidation']
      );
  $this->patch('/shares/{id}', 'SharesController:update')->add(
          $this->getContainer()['sharesValidation']
  );
});
