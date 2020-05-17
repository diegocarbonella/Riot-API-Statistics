<?php

class Internal {

    /*
    
    Todo lo relacionado a jsons internos, llamadas a mysql internas, escritura y lectura de archivos.
    
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



    // Lee los nombres de jsons locales y los retorna en un array
    private static function returnArrayLocalJsons($limit)
    {
        $offset = 0;
        $dir = __DIR__ . "/../games/done";
        $json_array = array_diff(scandir($dir), array('..', '.')); // resta los directorios ".." y "."
        $json_array = array_slice($json_array, $offset, $limit);
        return $json_array;
    }



    // Lee un json local y lo retorna convertido en array
    private static function readLocalJson($game_id)
    {
        $dir = __DIR__ . "/../games/done/";
        $json = file_get_contents($dir . $game_id . ".json");
        return $json = json_decode($json, true);
    }



    // Lee múltiples jsons, tiene $limit como parámetro.
    public static function readMultipleJsons($limit)
    {
        // lee mútiples jsons
        $json_array = self::returnArrayLocalJsons($limit);

        // tengo que borrar la extension ".json"
        foreach ($json_array as $key => $json) {
            $json_array[$key] = str_replace(".json", '', $json) ;
        }

        // carga la data de cada partida
        foreach ($json_array as $key => $json) {
            $json_array[$key] = self::readLocalJson($json);
        }
        return $json_array;
    }



    // Mueve jsons desde un directorio a otro, 
    // retorna cuantos movió
    public static function moveJsons($json_array)
    {
        $moved = 0;
        foreach ($json_array as $game_id) {
            $old_dir = __DIR__ . "/../games/done/"       . $game_id . ".json";
            $new_dir = __DIR__ . "/../games/done/saved/" . $game_id . ".json";
            if (rename($old_dir, $new_dir) == true) {
                $moved += 1;
            }
        }
        return $moved;
    }



    // // Lee jsons locales y los guarda en bbdd
    // // guarda en matchs, participants, bans, users
    // public static function insertLocalJsons()
    // {
    //     require_once __DIR__ . "/../models/Matchs.php";
    //     //1 leo 100 jsons y los guardo en un array.
    //     //2 compruebo si el array existe en la bbdd.
    //     //3 las que no existan los guardo en bbdd.


    //     echo "\n";

    //     $json_array = self::returnArrayLocalJsons();

    //     // tengo que borrar la extension ".json"
    //     foreach ($json_array as $key => $json) {
    //         $json_array[$key] = str_replace(".json", '', $json) ;
    //     }

    //     $mysqli = Matchs::connectToDB();
    //     $existent_json_array = Matchs::checkIfMatchsExistsInMatchs($json_array, $mysqli);

    //     foreach ($json_array as $key => $json) {
    //         $json_array[$key] = self::readLocalJson($json);
    //     }

    //     $success_records = Matchs::saveMatchsFromExternalJSON($json_array);

    //     // acá voy a tener que agregar una nueva función para que handlee en caso de que hay partidas que no puedan ser guardadas...

    //     print_r($success_records);

    //     //muevo el archivo desde done hasta saved
    //     foreach ($success_records as $game_id) {
    //         $old_dir = __DIR__ . "/../games/done/"       . $game_id . ".json";
    //         $new_dir = __DIR__ . "/../games/done/saved/" . $game_id . ".json";
    //         rename($old_dir, $new_dir);
    //     }
    // }









    // // Lee jsons locales y los guarda en bbdd
    // // guarda en matchs, participants, bans, users
    // public static function insertLocalJsons2()
    // {
    //     require_once __DIR__ . "/../models/Matchs.php";
    //     //1 leo 100 jsons y los guardo en un array.
    //     //2 compruebo si el array existe en la bbdd.
    //     //3 las que no existan los guardo en bbdd.


    //     echo "\n";

    //     $json_array = self::returnArrayLocalJsons();

    //     // tengo que borrar la extension ".json"
    //     foreach ($json_array as $key => $json) {
    //         $json_array[$key] = str_replace(".json", '', $json) ;
    //     }

    //     $mysqli = Matchs::connectToDB();
    //     $existent_json_array = Matchs::checkIfMatchsExistsInMatchs($json_array, $mysqli);

    //     foreach ($json_array as $key => $json) {
    //         $json_array[$key] = self::readLocalJson($json);
    //     }

    //     $success_records = Matchs::saveMatchsFromExternalJSON($json_array);

    //     // acá voy a tener que agregar una nueva función para que handlee en caso de que hay partidas que no puedan ser guardadas...

    //     print_r($success_records);

    //     //muevo el archivo desde done hasta saved
    //     foreach ($success_records as $game_id) {
    //         $old_dir = __DIR__ . "/../games/done/"       . $game_id . ".json";
    //         $new_dir = __DIR__ . "/../games/done/saved/" . $game_id . ".json";
    //         rename($old_dir, $new_dir);
    //     }
    // }



    // Lee jsons locales e inserta en bbdd,
    public static function insertMultipleMatchs($json_array)
    {
        require_once __DIR__ . "/../models/Matchs.php";
        require_once __DIR__ . "/../models/Bans.php";
        require_once __DIR__ . "/../models/Participants.php";
        require_once __DIR__ . "/../models/Users.php";
        $mysqli = Matchs::connectToDB();

        // Proceso matchs
        $matchs = array();
        foreach ($json_array as $json) {
            array_push($matchs, Matchs::processMatchData($json));
        }

        // Proceso bans
        $bans = array();
        foreach ($json_array as $json) {
            $bans = array_merge($bans, array_values(Matchs::processBansData($json)));
        }

        // Proceso participants
        $participants = array();
        foreach ($json_array as $json) {  
            $participants = array_merge($participants, Matchs::processParticipantsData($json));
        }

        // Proceso users
        $users = array();
        foreach ($json_array as $json) {
            $users = array_merge($users, array_values(Matchs::processUsersData($json)));
        }

        // Retorna la cantidad de satisfactorios
        return array(
            "matchs"       => Matchs::saveMultipleMatchs($matchs, $mysqli),
            "users"        => Users::saveMultipleUsers($users, $mysqli),
            "bans"         => Bans::saveMultipleBans($bans, $mysqli),
            "participants" => Participants::saveMultipleParticipants($participants, $mysqli)
        );
    }



    // Procesa multiples jsons locales y los inserta en bbdd.
    public static function processLocalJsonsAndInsertInDB()
    {
        $files = 250;
        $json_array = self::readMultipleJsons($files);

        $result = self::insertMultipleMatchs($json_array);

        // Salió todo bien?
        if ($result["users"] >= $files*10 && $result["bans"] >= $files*10 && $result["participants"] >= $files*10) {

            // convierto mi $json_array en $game_id_array
            $game_id_array = array();
            foreach ($json_array as $key => $json) {
                array_push($game_id_array, $json["gameId"]);
            }

            // Muevo el archivo desde done hasta saved
            echo "success moved : " . self::moveJsons($game_id_array) . " matchs added : " . $result["matchs"] . "\n";

            print_r($result);


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
    public static function isRankedMatch($json)
    {
        if ($json["queueId"] == 420 && $json["gameMode"] == "CLASSIC" && $json["gameType"] == "MATCHED_GAME") {
            return true;
        }
        return false;
    }



    public static function dale()
    {
        // $json = self::readLocalJson(839056942);
        $json = self::readLocalJson(836529761);

        echo self::isRankedMatch($json);
    }

}
