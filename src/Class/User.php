<?php

namespace App\Class;

class User
{
    protected $userRow;

    public function __construct($user)
    {
        if (array_keys($user)[0] == "UserID") {
            $this->userRow = $this->getUser([
          "UserID" => $user['UserID']
        ])[0];
        } else {
            $this->userRow = $this->getUser([
          "Identity" => $user['Identity']
        ])[0];
        }

        $this->userRow['Identities'] = json_decode($this->userRow['Identities'], true);
    }

    public function changeName($first,$last)
    {
        $this->getDB()->update('Users', [
          "UserID"=>$this->userRow['UserID']
        ], [
          "FirstName"=>$first,
          "LastName"=>$last
        ]);
    }

    public function newSession()
    {
        $token = substr(bin2hex(openssl_random_pseudo_bytes(255)), 0, 35);

        $sessions = json_decode($this->userRow['Sessions'], true);
        $sessions[] = [
          "session_id"=>rand(100000, 999999),
          "device"=>\App\Controller\AuthenticationController::decodeUserAgent(),
          "ip"=>$_SERVER['REMOTE_ADDR'],
          "ip_location"=>\App\Controller\AuthenticationController::getIPLocation($_SERVER['REMOTE_ADDR']),
          "created"=>time(),
          "token"=>$token
      ];

        $this->getDB()->update('Users', [
        "UserID"=>$this->userRow['UserID']
      ], [
        "Sessions"=>json_encode($sessions)
      ]);

        return $token;
    }

    public function me()
    {
        return $this->userRow;
    }


    protected static function getDB()
    {
        return new \App\Database\Database("UserDatabase");
    }

    public static function newUser($identities, $firstname, $lastname)
    {
        self::getDB()->insert("Users", [
        "UserID"=>substr(md5(time().uniqid()), 0, 12),
        "Identities"=>json_encode($identities),
        "FirstName"=>$firstname,
        "LastName"=>$lastname,
        "Sessions"=>"[]",
        "RegistrationTimestamp"=>time()
      ]);
    }

    public static function verifySession($userID, $sessionToken)
    {
        if (!preg_match("/^[0-9a-f]*$/", $userID) || !preg_match("/^[0-9a-f]*$/", $sessionToken)) {
            return false;
        }

        $condition = "UserID = '".$userID."' AND Sessions LIKE '%\"" . $sessionToken . "\"%'";
        $resp = self::getDB()->where("Users", $condition);

        return(count($resp)>0);
    }

    public function deleteSession($session_id="current")
    {
        $sessions = json_decode($this->userRow['Sessions'], true);
        $newSession = [];

        if ($session_id == "current") {
            foreach ($sessions as $s) {
                if ($s['token'] !== $_COOKIE[getenv('COOKIE_SESS_NAME')]) {
                    $newSession[] = $s;
                }
            }
        } elseif ($session_id == "all") {
        } else {
            foreach ($sessions as $s) {
                if (trim($s['session_id']) !== trim($session_id)) {
                    $newSession[] = $s;
                }
            }
        }

        $this->getDB()->update('Users', [
          "UserID"=>$this->userRow['UserID'],
        ], [
          "Sessions"=>json_encode($newSession)
        ]);
    }

    public function getSessions()
    {
        $sessions = json_decode($this->userRow['Sessions'], true);
        $displaySessions = [];

        foreach ($sessions as $s) {
            if ($s['token'] == $_COOKIE[getenv('COOKIE_SESS_NAME')]) {
                $s['current_session'] = true;
            } else {
                $s['current_session'] = false;
            }

            unset($s['token']);
            $displaySessions[] = $s;
        }

        return $displaySessions;
    }

    public static function userExists($condition)
    {
        if (array_keys($condition)[0] == "UserID") {
            $condition = "UserID = '" . $condition['UserID'] . "'";
        } else {
            $condition = "Identities LIKE '%\"" . $condition['Identity'] . "\"%'";
        }

        $resp = self::getDB()->where("Users", $condition);

        if (count($resp) > 0) {
            return true;
        } else {
            return false;
        }
    }

    public static function getUser($condition)
    {
        if (array_keys($condition)[0] == "UserID") {
            $condition = "UserID = '" . $condition['UserID'] . "'";
        } else {
            $condition = "Identities LIKE '%\"" . $condition['Identity'] . "\"%'";
        }

        $resp = self::getDB()->where("Users", $condition);

        return $resp;
    }
}
