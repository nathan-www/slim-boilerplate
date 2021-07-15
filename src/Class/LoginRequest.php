<?php

namespace App\Class;

class LoginRequest
{
    protected $LoginRequestRow;

    protected static function getDB()
    {
        return new \App\Database\Database("UserDatabase");
    }

    public function __construct($requestID)
    {
        $this->LoginRequestRow = self::getDB()->where("LoginRequests", [
          "RequestID"=>$requestID
        ])[0];
    }

    public function me()
    {
        return $this->LoginRequestRow;
    }

    public static function requestExists($requestID)
    {
        return(count(self::getDB()->where("LoginRequests", [
          "RequestID"=>$requestID
        ])) > 0);
    }

    /* Exchanges token for a code, will return false if token invalid */
    public static function tokenToCode($token)
    {
        self::getDB()->delete("LoginRequests", "Timestamp < " . (time() - 900));

        $res = self::getDB()->where("LoginRequests", [
        "Token"=>$token
      ]);

        if (count($res) > 0) {
            self::getDB()->update("LoginRequests", [ "Token"=>$token ], [ "Status"=>"Approved" ]);
            return $res[0]['Code'];
        } else {
            return false;
        }
    }

    /* Verifies requestID and code to login */
    public static function verifyLogin($requestID, $code)
    {
        self::getDB()->delete("LoginRequests", "Timestamp < " . (time() - 900));

        $res = self::getDB()->where("LoginRequests", [
        "RequestID"=>$requestID,
        "Status"=>"Approved"
      ]);

        self::getDB()->update("LoginRequests", [ "RequestID"=>$requestID ], [ "LastAttemptTimestamp"=>time() ]);

        if (count($res) > 0) {

            //Do not allow more than 1 attempt per 10 seconds
            if ($res[0]['LastAttemptTimestamp'] > time() - 10) {
                return [
                  "status"=>"fail",
                  "reason"=>"429"
                ];

            } elseif ($res[0]['Code'] !== $code) {

                return [
                "status"=>"fail",
                "reason"=>"incorrect"
              ];

            } else {
                self::getDB()->update("LoginRequests", [ "RequestID"=>$requestID ], [ "Status"=>"Completed" ]);

                return [
                  "status"=>"success",
                  "UserID"=>$res[0]['UserID']
                ];
            }
        } else {
            return [
              "status"=>"fail",
              "reason"=>"incorrect"
            ];
        }
    }
}
