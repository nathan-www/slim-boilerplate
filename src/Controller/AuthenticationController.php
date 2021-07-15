<?php

namespace App\Controller;

use Slim\Psr7\Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Container\ContainerInterface;
use Slim\Exception\HttpSpecializedException;

class AuthenticationController extends Controller
{
    protected $request;

    public function authTest(Request $request)
    {
        $response = new Response;
        $db = $this->ci->get('UserDatabase');

        return $response;
    }


    /* Handle /auth/loginRequest */
    public function loginRequest(Request $request)
    {
        $this->request = $request;
        $db = $this->ci->get('UserDatabase');
        $json = json_decode($request->getBody(), true);


        if ($this->shouldRecaptcha($json['identity']) && (!isset($json['recaptcha_token']) || !$this->verifyRecaptchaToken($json['recaptcha_token']))) {
            //Needs a valid recaptcha token
            return $this->jsonResponse([
            "status"=>"fail",
            "require_recaptcha"=>true
          ]);
        }

        if (!\App\Class\User::userExists([ "Identity"=>$json['identity'] ])) {

          // Identity does not belong to account yet
            if (!isset($json['full_name'])) {

                //User must send their full name to create account
                $resp = [
                  "status"=>"fail",
                  "require_full_name"=>true
                ];

                if ($this->shouldRecaptcha($json['identity'])) {
                    $resp['require_recaptcha'] = true;
                }

                return $this->jsonResponse($resp);
            }


            //Create new account
            \App\Class\User::newUser([$json['identity']], explode(" ", $json['full_name'])[0], explode(" ", $json['full_name'])[1]);
        }

        //Create login request
        $user = new \App\Class\User([
            "Identity"=>$json['identity']
          ]);


        $requestID = substr(bin2hex(openssl_random_pseudo_bytes(255)), 0, 35);
        $token = substr(bin2hex(openssl_random_pseudo_bytes(255)), 0, 35);
        //Note - changing character lengths of above tokens will require changing parameter middleware
        $code = rand(10000, 99999);
        $ip_location = $this->getIPLocation($_SERVER['REMOTE_ADDR']);

        $db->insert("LoginRequests", [
            "RequestID"=>$requestID,
            "UserID"=>$user->me()['UserID'],
            "Identity"=>$json['identity'],
            "Status"=>"Requested",
            "Token"=>$token,
            "Code"=>$code,
            "Timestamp"=>time(),
            "RequestIP"=>$_SERVER['REMOTE_ADDR'],
            "RequestIPLocation"=>$ip_location,
            "RequestUserAgent"=>$this->decodeUserAgent(),
            "LastAttemptTimestamp"=>time()
          ]);


        //Send login code to user's email
        \App\Email\Email::sendEmail([
          "to"=>$json['identity'],
          "subject"=>"We've got your magic link!",
          "template"=>"loginRequest",
          "variables"=>[
              "request_token"=>$token,
              "ip"=>$_SERVER['REMOTE_ADDR'],
              "ip_location"=>$ip_location,
              "device"=>$this->decodeUserAgent(),
              "first_name"=>$user->me()['FirstName']
          ]
        ]);

        return $this->jsonResponse([
          "status"=>"success",
          "request_id"=>$requestID
        ]);
    }

    /* Handle /auth/loginStatus */
    public function loginStatus(Request $request)
    {
        $this->request = $request;
        $db = $this->ci->get('UserDatabase');
        $json = json_decode($request->getBody(), true);

        if (\App\Class\LoginRequest::requestExists($json['request_id'])) {
            $request = new \App\Class\LoginRequest($json['request_id']);

            return $this->jsonResponse([
              "status"=>"success",
              "login_status"=>$request->me()['Status']
            ]);
        } else {
            return $this->jsonResponse([
            "status"=>"fail"
          ]);
        }

        return new Response();
    }

    /* Handle /auth/tokenToCode */
    public function tokenToCode(Request $request)
    {
        $this->request = $request;
        $db = $this->ci->get('UserDatabase');
        $json = json_decode($request->getBody(), true);

        $code = \App\Class\LoginRequest::tokenToCode($json['token']);

        if ($code == false) {
            return $this->jsonResponse([
              "status"=>"fail"
            ]);
        } else {
            return $this->jsonResponse([
              "status"=>"success",
              "code"=>$code
            ]);
        }
    }

    /* Handle /auth/login */
    public function login(Request $request)
    {
        $this->request = $request;
        $db = $this->ci->get('UserDatabase');
        $json = json_decode($request->getBody(), true);

        $res = \App\Class\LoginRequest::verifyLogin($json['request_id'], $json['code']);

        if ($res['status'] == "success") {
            //Yay! Successful login
            $user = new \App\Class\User([
          "UserID"=>$res['UserID']
          ]);
            $token = $user->newSession();

            setcookie(getenv('COOKIE_USERID_NAME'), $res['UserID']);
            setcookie(getenv('COOKIE_SESS_NAME'), $token);

            return $this->jsonResponse([
          "status"=>"success"
        ]);
        } else {
            if ($res['reason'] == "429") {
                throw new HttpTooManyRequestsException($this->request, "Too many requests.");
            } else {
                return $this->jsonResponse([
            "status"=>"fail",
            "reason"=>$res['reason']
          ]);
            }
        }
    }


    /* Handle /auth/amILoggedIn */
    public function accountInfo(Request $request)
    {
        $user = new \App\Class\User([
          "UserID"=>$request->getAttribute('loggedInUserID')
        ]);

        return $this->jsonResponse([
          "status"=>"success",
          "identities"=>$user->me()['Identities'],
          "firstName"=>$user->me()['FirstName'],
          "lastName"=>$user->me()['LastName']
        ]);
    }

    /* Handle /auth/logout */
    public function logout(Request $request)
    {
        $user = new \App\Class\User([
          "UserID"=>$request->getAttribute('loggedInUserID')
        ]);

        $user->deleteSession();

        return $this->jsonResponse([
          "status"=>"success"
        ]);
    }


    /* Handle /auth/listSessions */
    public function listSessions(Request $request)
    {
        $user = new \App\Class\User([
          "UserID"=>$request->getAttribute('loggedInUserID')
        ]);

        return $this->jsonResponse([
          "status"=>"success",
          "sessions"=>$user->getSessions()
        ]);
    }

    /* Handle /auth/deleteSession */
    public function deleteSession(Request $request)
    {
        $json = json_decode($request->getBody(), true);

        $user = new \App\Class\User([
          "UserID"=>$request->getAttribute('loggedInUserID')
        ]);

        $user->deleteSession($json['session_id']);

        return $this->jsonResponse([
          "status"=>"success"
        ]);
    }



    /* Handle /auth/changeName */
    public function changeName(Request $request)
    {
        $json = json_decode($request->getBody(), true);

        $user = new \App\Class\User([
          "UserID"=>$request->getAttribute('loggedInUserID')
        ]);

        $user->changeName(explode(" ", $json['full_name'])[0],explode(" ", $json['full_name'])[1]);

        return $this->jsonResponse([
          "status"=>"success"
        ]);
    }


    /* Whether ReCaptcha should be required for login request, based on IP and identity */
    protected function shouldRecaptcha($identity)
    {
        $db = $this->ci->get('UserDatabase');

        //First delete all login requests older than 15 minutes
        $db->delete("LoginRequests", "Timestamp < " . (time() - 900));

        $ip = $_SERVER['REMOTE_ADDR'];

        $test = ($db->where("LoginRequests", "(Identity = '".$identity."' OR RequestIP='" . $ip . "') AND Timestamp > " . (time()-600)));

        if ($test !== false && count($test) > 50) {
            //Woah! 50 requests in 10 minutes, block!
            throw new HttpTooManyRequestsException($this->request, "Too many requests.");
        } elseif ($test !== false && count($test) > 6) {
            //More than 6 login requests in the last 10 mins against this identity or from this IP
            //Require ReCaptcha
            return true;
        } else {
            return false;
        }
    }

    /* Verify submitted recaptcha token with Google */
    protected function verifyRecaptchaToken($token)
    {
        $context  = stream_context_create(['http' =>
          [
            'method'  => 'POST',
            'header'  => 'Content-Type: application/x-www-form-urlencoded',
            'content' => http_build_query([
                    'secret' => getenv('GOOGLE_RECAPTCHA_SECRET'),
                    'response' => $token
            ])
          ]
        ]);

        return json_decode(file_get_contents('https://www.google.com/recaptcha/api/siteverify', false, $context), true)['success'];
    }


    /* Get the geographical location from user's IP address */
    public static function getIPLocation($ip)
    {
        $req = json_decode(file_get_contents('http://ip-api.com/json/'.$ip), true);
        if (!is_null($req) && $req['status'] == "success") {
            return $req['city'] . ", " . $req['country'];
        } else {
            return "Unknown location";
        }
    }

    /* Converts user agent header to readable string */
    public static function decodeUserAgent()
    {
        $parser = new \donatj\UserAgent\UserAgentParser();
        $ua = $parser->parse();
        return $ua->browser() . " on " . $ua->platform();
    }
}

class HttpTooManyRequestsException extends HttpSpecializedException
{
    protected $code = 429;
    protected $message = 'Too Many Requests.';
    protected $title = '429 Too Many Requests';
    protected $description = 'Blocked due to receiving too many requests.';
}
