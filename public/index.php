<?php

use Slim\Psr7\Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Routing\RouteCollectorProxy;
use Slim\Factory\AppFactory;
use DI\Container;

require __DIR__.'/../vendor/autoload.php';
require __DIR__ . '/../env.php';

//New dependency injection container
$container = new Container();


//Setup database
$UserDatabase = new \App\Database\Database("UserDatabase", function ($db) {
    $db->query(<<<EOT
    CREATE TABLE Users (
      UserID TINYTEXT PRIMARY KEY,
      Identities LONGTEXT,
      FirstName TINYTEXT,
      LastName TINYTEXT,
      Sessions LONGTEXT,
      RegistrationTimestamp TINYTEXT
    );
  EOT);

    $db->query(<<<EOT
    CREATE TABLE LoginRequests (
      RequestID TINYTEXT PRIMARY KEY,
      UserID TINYTEXT,
      Identity TINYTEXT,
      Status TINYTEXT,
      Token TINYTEXT,
      Code TINYTEXT,
      Timestamp TINYTEXT,
      RequestIP TINYTEXT,
      RequestIPLocation TINYTEXT,
      RequestUserAgent TINYTEXT,
      LastAttemptTimestamp TINYTEXT
    );
  EOT);
});


//Setup SQLite databases with installation statements
$container->set('UserDatabase', function () {
    global $UserDatabase;
    return $UserDatabase;
});



AppFactory::setContainer($container);

$app = AppFactory::create();


$app->get('/test', '\App\Controller\TestController:default');

$app->get('/authTest', '\App\Controller\AuthenticationController:authTest');

/* Authentication routes */
$app->group('/auth', function (RouteCollectorProxy $group) {
    $group->post('/loginRequest', '\App\Controller\AuthenticationController:loginRequest')->add(fn ($request, $handler) => App\Middleware\ParameterCheckerMiddleware::run($request->withAttribute('params', [
    "identity" => fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL), //Email
    "full_name" => [
      "optional" => true,
      "checker" => "/^[A-Za-z-]{1,20}( )[A-Za-z-]{1,20}$/"
    ]
  ]), $handler));

    $group->post('/loginStatus', '\App\Controller\AuthenticationController:loginStatus')->add(fn ($request, $handler) => App\Middleware\ParameterCheckerMiddleware::run($request->withAttribute('params', [
      "request_id" => "/^[a-f0-9]{35}$/", //35-digit hex
    ]), $handler));

    $group->post('/tokenToCode', '\App\Controller\AuthenticationController:tokenToCode')->add(fn ($request, $handler) => App\Middleware\ParameterCheckerMiddleware::run($request->withAttribute('params', [
      "token" => "/^[a-f0-9]{35}$/", //35-digit hex
    ]), $handler));

    $group->post('/login', '\App\Controller\AuthenticationController:login')->add(fn ($request, $handler) => App\Middleware\ParameterCheckerMiddleware::run($request->withAttribute('params', [
      "request_id" => "/^[a-f0-9]{35}$/", //35-digit hex
      "code" => "/^[0-9]{5,6}$/" //5-6 digit number
    ]), $handler));

    $group->get('/accountInfo', '\App\Controller\AuthenticationController:accountInfo')->add('\App\Middleware\AuthenticationMiddleware::loggedIn');

    $group->get('/logout', '\App\Controller\AuthenticationController:logout')->add('\App\Middleware\AuthenticationMiddleware::loggedIn');

    $group->get('/listSessions', '\App\Controller\AuthenticationController:listSessions')->add('\App\Middleware\AuthenticationMiddleware::loggedIn');

    $group->post('/deleteSession', '\App\Controller\AuthenticationController:deleteSession')->add(fn ($request, $handler) => App\Middleware\ParameterCheckerMiddleware::run($request->withAttribute('params', [
      "session_id" => "/^(([0-9]{5,6})|(all))$/", //5-6 digit number, or the string 'all'
    ]), $handler))->add('\App\Middleware\AuthenticationMiddleware::loggedIn');

    $group->post('/changeName', '\App\Controller\AuthenticationController:changeName')->add(fn ($request, $handler) => App\Middleware\ParameterCheckerMiddleware::run($request->withAttribute('params', [
      "full_name" => "/^[A-Za-z-]{1,20}( )[A-Za-z-]{1,20}$/"
    ]), $handler))->add('\App\Middleware\AuthenticationMiddleware::loggedIn');

});

/* Error handler */
$errorMiddleware = $app->addErrorMiddleware(true, true, true);
$errorMiddleware->setDefaultErrorHandler('App\Controller\ErrorController:errorHandler');

$app->run();
