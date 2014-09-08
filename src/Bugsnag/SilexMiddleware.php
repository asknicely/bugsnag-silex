<?php

class Bugsnag_SilexMiddleware
{
    private static $request;

    /* Captures request information */
    public static function beforeMiddleware()
    {
        $middlewareFunc = function (Symfony\Component\HttpFoundation\Request $request) {
            self::$request = $request;
        };
        return $middlewareFunc;
    }

    /* Filters stack frames and appends new tabs */
    public static function errorMiddleware($client)
    {
        return function (Exception $error, $code) use($client) {
            $client->setBeforeNotifyFunction(function (Bugsnag_Error $e) {
                $frames = array_filter($e->stacktrace->frames, function ($frame) {
                    $file = $frame['file'];

                    if (preg_match('/^\[internal\]/', $file)) {
                        return FALSE;
                    }

                    if (preg_match('/symfony\/http-kernel/', $file)) {
                        return FALSE;
                    }

                    if (preg_match('/silex\/silex\//', $file)) {
                        return FALSE;
                    }

                    return TRUE;
                });

                $e->stacktrace->frames = array();
                foreach ($frames as $frame) {
                    $e->stacktrace->frames[] = $frame;
                }

                $e->setMetaData(array(
                    "user" => array(
                        "clientIp" => self::$request->getClientIp()
                    )
                ));
            });

            $session = self::$request->getSession();
            if ($session) {
                $session = $session->all();
            }

            $qs = array();
            parse_str(self::$request->getQueryString(), $qs);

            $client->notifyException($error, array(
                "request" => array(
                    "clientIp" => self::$request->getClientIp(),
                    "params" => $qs,
                    "requestFormat" => self::$request->getRequestFormat(),
                ),
                "session" => $session,
                "cookies" => self::$request->cookies->all(),
                "host" => array(
                    "hostname" => self::$request->getHttpHost()
                )
            ));
        };
    }
}