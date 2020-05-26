<?php

require_once __DIR__ . '/../config/Config.php';

class Database {

    // Se conecta a la base de datos, retorna connexión
    public static function connectToDB()    
    {
        $credentials = Config::$credentials;
        $mysqli = new mysqli($credentials['host'], $credentials['username'], $credentials['password'], $credentials['database']);
        return $mysqli;
    }



    // Crea una url para consumir Riot API.
    public static function createUrl($end_point, $url_params)
    {
        $url = $end_point . "?";

        foreach ($url_params as $param => $value) {

            $url = $url . $param . "=" . $value . "&";

        }

        $url = $url . "api_key=" . Config::$api_key;

        return $url;
    }



    // Obtiene data desde Riot API
    public static function consumeRiotAPI($url)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_URL, $url);
        $result = curl_exec($ch);
        $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $result = json_decode($result, true);

        $array = array($result, $http_status);

        return $array;
    }



}

?>