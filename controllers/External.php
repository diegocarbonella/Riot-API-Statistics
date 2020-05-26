<?php

class External {

    /*
    
    Todo lo relacionado a jsons externos, consumo de Riot API, guardado de datos en bbdd.
    
    */

    //hace multiples funciones, retorna un array con los resultados
    public static function multicurl($array)
    {
        // array of curl handles
        $multiCurl = array();
        
        // guarda el resultado
        $result = array();
        
        // multi handle
        $mh = curl_multi_init();
        
        foreach ($array as $i => $match_id) {
            $url = "https://la2.api.riotgames.com/lol/match/v4/matches/" . $match_id . "?api_key=" . Config::$api_key;
            $multiCurl[$match_id] = curl_init();
            curl_setopt($multiCurl[$match_id], CURLOPT_URL, $url);
            curl_setopt($multiCurl[$match_id], CURLOPT_HEADER, 0); //<--no incluye el header en el resultado
            curl_setopt($multiCurl[$match_id], CURLOPT_RETURNTRANSFER, 1); //<--no imprime el resultado
            curl_multi_add_handle($mh, $multiCurl[$match_id]);
        }
        
        $index=null;
        
        do {
            curl_multi_exec($mh, $index);
        } while($index > 0);
        
        // get content and remove handles
        foreach($multiCurl as $k => $ch) {
            // echo $k;
            $result[$k] = curl_multi_getcontent($ch);
            curl_multi_remove_handle($mh, $ch);
        }
        
        // close
        curl_multi_close($mh);
        
        return $result;
    }



    // Recibe un array de $games (jsons) y empaqueta en [ success_matchs[] , error_matchs[] , jsons[] ]
    public static function processMatchs($matchs)
    {
        // matchs que hubo error (429, 404, 500, etc)
        $error_matchs = array();
        // matchs que fueron guardados exitosamente
        $success_matchs = array();
        // data json exitosa
        $jsons = array();

        // itero todos los results (10)
        foreach ($matchs as $re => $id) {

            $json = json_decode($id, true);

            if (isset($json["status"]["status_code"])){                                                         // hay un error?
                echo $re . " error " . $json["status"]["status_code"] . "\n";
                array_push($error_matchs, array("id" => $re, "status_code" => $json["status"]["status_code"])); // guardo el game_id en $error_matchs
            } else {                                                                                            // no hay error...
                echo $re . " no hubo error\n";
                array_push($success_matchs, $re);                                                               // guardo el game_id en $success_matchs
                array_push($jsons, $json);
            }

        }

        return array(
            "jsons" => $jsons,
            "error_matchs" => $error_matchs,
            "success_matchs" => $success_matchs
        );

    }



    // consume Riot API guarda hasta 100 partidas. 
    public static function saveJsonGamesFromAPI()
    {
        require_once __DIR__ . "/../models/Matchs.php";

        $time_start = microtime(true);

        $mysqli = Matchs::connectToDB();

        // obtengo 100 game_id de bbdd
        $array = Matchs::selectDuplicatesOrNot($mysqli, false);

        $execution_time = 0;

        $i = 0;

        $store = array( // acá se van a guardar todos los datos obtenidos
            "jsons" => array(),
            "error_matchs" => array(),
            "success_matchs" => array()
        );

        $matchs_not_found = array(); //<--- acá almaceno las partidas que tienen error 404

        // hace llamadas a Riot API durante 30 segundos,
        // en ese lapso puede hacer hasta 200 llamadas aproximadamente lo que es suficiente para
        // usar al máximo el limite de las 100 llamadas cada dos minutos. 
        while ($execution_time <= 30) {

            sleep(1);

            // 1) saco 10 game_id de $array para hacer multicurl
            // (son solamente 10 por un rate limit de la RiotAPI)
            $sliced_array = array_slice($array, 100 - ($i + 1) * 15); 
            
            // 2) hago multicurl y almaceno en $result
            $result = External::multicurl($sliced_array);
            
            // 3) proceso $result para separar en tres arrays [success matchs id, error matchs id, jsons (data)]
            $result = External::processMatchs($result);
            
            // 4) empezó siendo array de 100, ahora le resto 10 porque ya analicé esos 10
            $array = array_diff($array, $sliced_array);

            // 5) por cada partida con error tengo que analizar si fue un error 404, 
            // de esa manera puedo borrar de bbdd las partidas que no existan
            foreach ($result["error_matchs"] as $error_match) {
                if ($error_match["status_code"] == 404) {
                    array_push($matchs_not_found, $error_match["id"]);
                    unset($array[$error_match["id"]]);
                } else {
                    array_push($array, $error_match["id"]);
                }
            }

            // 6) guardo data obtenida
            $store["jsons"] = array_merge($store["jsons"], $result["jsons"]);
            $store["success_matchs"] = array_merge($store["success_matchs"], $result["success_matchs"]);

            $time_end = microtime(true);
            $execution_time = intval($time_end - $time_start);
            // echo "gathered = ". count($store["success_matchs"]) . " remaining = " . count($array) . " exec time = $execution_time \n";

            if (count($array) < 1) {
                break;
            }

            $i++;
            
        }

        echo "Obtenidas = " . count($store["success_matchs"]) . "\n";
        echo "Error = " . count($array) . "\n";

        $number_of_writes = 0;

        foreach ($store["jsons"] as $json)  {

            $fp = fopen(__DIR__ . '/../games/' . $json["gameId"] . '.json', 'w');
            $json = json_encode($json);
            fwrite($fp, $json);
            fclose($fp);
            $number_of_writes++;

        }

        $mysqli = Matchs::connectToDB();

        if (count($store["success_matchs"]) > 0) {
            $matchs = Matchs::deleteMatchFutureInDB($mysqli, $store["success_matchs"]);
            echo " success matches deleted " . $matchs . " \n";
        }

        if (count($matchs_not_found) > 0) {
            $matchs = Matchs::deleteMatchFutureInDB($mysqli, $matchs_not_found);
            echo " matches not found deleted " . $matchs . " \n";
        }

        echo "Archivos creados = $number_of_writes\n";

        $time_final_end = microtime(true);

        $total_execution_time = intval($time_final_end - $time_start);

        echo "\n";

        echo "Total execution time = $total_execution_time\n";

        echo "Riot API execution time = $execution_time\n";

        $sleep = 120 - $total_execution_time - $execution_time;

        if ($sleep >= 0) {
            echo "Duerme $sleep \n";
            sleep($sleep);
        }

        echo "Fin...\n";

    }



    // Agrega games_id a tabla matchs_future
    public static function insertMatchsFromUser()
    {
        require_once __DIR__ . "/../models/Users.php";
        require_once __DIR__ . "/../models/Matchs.php";

        $mysqli = Matchs::connectToDB();

        $account_id = Users::selectLastAccountId($mysqli);

        $gathered_matchs = self::getAllMatchesFromUser($account_id, $mysqli);

        echo "Matchs obtenidas : " . count($gathered_matchs) . "\n";

        if (count($gathered_matchs) == 0) {

            return false;

        }

        $existent_matchs = Matchs::checkIfMatchsExistsInMatchs($gathered_matchs, $mysqli);

        $matchs_to_save = array_diff($gathered_matchs, $existent_matchs); // Resta las matchs que obtuvo de la API externa las que ya tengo en mi bbdd. 
        
        echo "Matchs a guardar : " . count($matchs_to_save)  . "\n";

        // tengo que agregar para que me diga si se agregaron nuevas filas o no..... porque ahora mismo te muestra el numero se hayan agregado o no...
        if (count($matchs_to_save) > 0) {

            echo Matchs::setMatchFutureInDB($matchs_to_save)     . "\n";

        }

    }



    // Retorna game_id_array de todas las rankeds de un jugador dado su $account_id
    public static function getAllMatchesFromUser($account_id, $mysqli)
    {
        $game_id_array = array(); // TODAS las partidas a retornar

        $queue       = 420;
        $begin_index = 0;
        $end_index   = 100;
        $season      = 13;

        $obtained_matchs = array(1); // número de partidas en una consulta (máximo 100).

        $i = 0;

        // pregunta si el valor obtenido por la api de matchs > 0
        while (count($obtained_matchs) > 0) {

            $begin_index = ($i)     * 100;  // arranca en 0
            $end_index   = ($i + 1) * 100;  // arranca en 100

            $url_params = array(
                "queue"      => $queue,
                "endIndex"   => $end_index,
                "beginIndex" => $begin_index,
                "season"     => $season
            );

            $end_point = "https://la2.api.riotgames.com/lol/match/v4/matchlists/by-account/" . $account_id;
            $url       = Matchs::createUrl($end_point, $url_params);
            $data      = Matchs::consumeRiotAPI($url);

            if (!isset($data[0]["matches"])) { // hay error

                echo "\nError:" . $data[1] . "\n";

                if ($data[1] == 404) {

                    return $game_id_array;

                }

                continue;

            }

            $obtained_matchs = $data[0]["matches"];

            // por cada match recibida (máx 100) va a guardarla en $wrapper
            foreach ($obtained_matchs as $match) {

                array_push($game_id_array, $match["gameId"]);

            }

            $i += 1;
        }

        return $game_id_array;
    }

}




?>