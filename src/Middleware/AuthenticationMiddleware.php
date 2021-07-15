<?php

namespace App\Middleware;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response;
use Slim\Exception\HttpForbiddenException;


class AuthenticationMiddleware{

  public static function loggedIn(Request $request, RequestHandler $handler){

    if(!isset($_COOKIE[getenv('COOKIE_USERID_NAME')]) || !isset($_COOKIE[getenv('COOKIE_SESS_NAME')])){
      throw new HttpForbiddenException($request, "Not logged in");
    }

    if(!\App\Class\User::verifySession($_COOKIE[getenv('COOKIE_USERID_NAME')],$_COOKIE[getenv('COOKIE_SESS_NAME')])){
          throw new HttpForbiddenException($request, "Not logged in");
    }

    return $handler->handle($request->withAttribute('loggedInUserID',$_COOKIE[getenv('COOKIE_USERID_NAME')]));

  }

}
