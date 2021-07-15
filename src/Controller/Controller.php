<?php

namespace App\Controller;

use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Container\ContainerInterface;
use Slim\Exception\HttpBadRequestException;

abstract class Controller
{
    protected $ci;

    public function __construct(ContainerInterface $ci)
    {
        $this->ci = $ci;
    }

    public function jsonResponse($data){

      $response = new Response;
      $response->getBody()->write(json_encode($data));
      return $response->withHeader('Content-type', 'application/json');
    }

}
