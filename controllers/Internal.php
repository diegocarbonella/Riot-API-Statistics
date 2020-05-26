<?php

class Internal {

    /*
    
    Todo lo relacionado a jsons internos, llamadas a mysql internas, escritura y lectura de archivos.
    Usado principalmente con la shell.
    
    */

    // Agarra 100 matchs_future que estén duplicadas, 
    // luego ese array lo borra de matchs_future.
    public static function deleteDuplicateMatchs()
    {
        require_once __DIR__ . "/../models/Matchs.php";

        $mysqli = Matchs::connectToDB();
        $duplicate_matchs = Matchs::selectDuplicatesOrNot($mysqli, true);
        echo Matchs::deleteMatchFutureInDB($mysqli, $duplicate_matchs);
    }



    // Lee un directorio local y retorna los NOMBRES de los archivos jsons.
    private static function returnArrayLocalJsonNames($limit, $offset = 0, $dir)
    {
        $json_array = array_diff(scandir($dir), array('..', '.')); // resta los directorios ".." y "."
        $json_array = array_slice($json_array, $offset, $limit);

        foreach ($json_array as $key => $json) {

            if (strpos('.json', $json) !== FALSE) {

                print_r($key . " " . $json .  "\n");
                print_r("hola");
                unset($json_array[$key]);

            }

        }

        return $json_array;
    }



    // Lee un json local y lo retorna convertido en array.
    private static function readLocalJson($game_id)
    {
        $dir = __DIR__ . "/../games/done/";
        $json = file_get_contents($dir . $game_id . ".json");

        if ($json == false) {

            return false;

        } 

        return json_decode($json, true);
    }



    // Lee múltiples jsons, tiene $limit como parámetro.
    // combinación de las dos funciones anteriores.
    public static function readMultipleJsons($limit, $offset = 0, $dir)
    {
        // lee los nombres de mútiples jsons.
        $json_array = self::returnArrayLocalJsonNames($limit, $offset, $dir);

        // borra la extension ".json"
        foreach ($json_array as $key => $file_name) {

            // si no es un .json lo borra del array.
            if (strpos($json_array[$key], '.json') === FALSE) {

                unset($json_array[$key]);
                continue;

            }

            $json_array[$key] = str_replace(".json", '', $file_name);

        }

        // carga la data de cada partida
        foreach ($json_array as $key => $json) {

            $json_array[$key] = self::readLocalJson($json);

        }

        return $json_array;
    }



    // Mueve jsons desde un directorio a otro, 
    // retorna cuántos movió.
    public static function moveJsons($json_array, $source, $destination)
    {
        $moved = 0;

        foreach ($json_array as $game_id) {

            $old_dir = $source      . $game_id . ".json";
            $new_dir = $destination . $game_id . ".json";

            if (rename($old_dir, $new_dir) == true) {

                $moved += 1;

            }

        }

        return $moved;
    }



    // Borra archivos jsons locales.  
    public static function deleteJsons($json_array, $dir)
    {
        foreach ($json_array as $json) {

            $file = $dir . $json . ".json";
            unlink($file);

        }
    }

    

    // Inserta jsons en bbdd,
    public static function insertMultipleMatchsInDB($json_array)
    {
        require_once __DIR__ . "/../models/Matchs.php";
        require_once __DIR__ . "/../models/Bans.php";
        require_once __DIR__ . "/../models/Participants.php";
        require_once __DIR__ . "/../models/Users.php";
        $mysqli = Matchs::connectToDB();

        // Procesa matchs.
        $matchs = array();

        foreach ($json_array as $json) {

            array_push($matchs, Matchs::processMatchData($json));

        }

        // Procesa bans.
        $bans = array();

        foreach ($json_array as $json) {

            $bans = array_merge($bans, array_values(Matchs::processBansData($json)));

        }

        // Proceso participants.
        $participants = array();

        foreach ($json_array as $json) { 

            $participants = array_merge($participants, Matchs::processParticipantsData($json));

        }

        // Proceso users.
        $users = array();

        foreach ($json_array as $json) {

            $users = array_merge($users, array_values(Matchs::processUsersData($json)));

        }

        // Retorna cuenta de los casos.
        return array(
            "matchs"       => Matchs::saveMultipleMatchs($matchs, $mysqli),
            "users"        => Users::saveMultipleUsers($users, $mysqli),
            "bans"         => Bans::saveMultipleBans($bans, $mysqli),
            "participants" => Participants::saveMultipleParticipants($participants, $mysqli)
        );
    }



    // Procesa múltiples jsons locales y los inserta en bbdd.
    public static function processLocalJsonsAndInsertInDB($files)
    {
        $json_array = self::readMultipleJsons($files, 0, __DIR__ . "/../games/done/");

        $number_of_files = count($json_array);

        $result = self::insertMultipleMatchsInDB($json_array);

        if ($result["users"] >= $number_of_files*10 && $result["bans"] >= $number_of_files*10 && $result["participants"] >= $number_of_files*10) {

            // mejorar esto
            $game_id_array = array();

            foreach ($json_array as $key => $json) {

                array_push($game_id_array, $json["gameId"]);

            }

            $moved_jsons = self::moveJsons($game_id_array, __DIR__ . "/../games/done/", __DIR__ . "/../games/done/saved/");
            echo "success moved : " . $moved_jsons . " matchs added : " . $result["matchs"] . "\n";

        } else {

            // // convierto mi $json_array en $game_id_array
            // $game_id_array = array();
            // foreach ($json_array as $key => $json) {
            //     array_push($game_id_array, $json["gameId"]);
            // }

            // // Muevo el archivo desde done hasta saved
            // echo "success moved : " . self::moveJsons($game_id_array) . " matchs added : " . $result["matchs"] . "\n";

            echo "????????????????????????????????????????????????????????????????????????????????????????";
            print_r($result);

        }
    }



    // Checkea si un json es ranked,
    private static function isRankedMatch($json)
    {
        if ($json["queueId"] == 420 && $json["gameMode"] == "CLASSIC" && $json["gameType"] == "MATCHED_GAME") {
            return true;
        }
        return false;
    }



    // Carga un array de jsons files, analiza cuáles no son ranked y los elimina.  
    public static function deleteJsonsNonRanked($limit, $offset)
    {
        $json_array = self::readMultipleJsons($limit, $offset, __DIR__ . "/../games/done/");
    
        $non_ranked_array = array();

        foreach ($json_array as $json) {
            if (!self::isRankedMatch($json)) {
                array_push($non_ranked_array, $json["gameId"]);
            }
        }

        return self::moveJsons($non_ranked_array, __DIR__ . "/../games/done/", __DIR__ . "/../games/error/");
    }

}