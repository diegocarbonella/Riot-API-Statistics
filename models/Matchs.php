<?php

require_once 'Database.php';

class Matchs extends Database {



    // Obtiene data desde Riot API
    private static function getDataFromRiotAPI($match_id)
    {
        $url = "https://la2.api.riotgames.com/lol/match/v4/matches/" . $match_id . "?api_key=" . Config::$api_key;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_URL,$url);
        $result=curl_exec($ch);
        $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $result = json_decode($result, true);

        $array = array($result, $http_status);

        return $array;
    }



    // Se fija en el status_code, si es 200, retorna la data
    private static function existsMatch($result)
    {
        if ($result[1] == 200) {
            return true;
        } else {
            return $result[1]; // error en la consulta a la api
        }
    }



    // Crea un array ordenado para poder luego ser almacenado en la BBDD.
    // Obtiene como parámetro un json con toda la información de un match.
    private static function processRawData($json)
    {
        // Participants data
        $participants = self::processParticipantsData($json);

        // Match data
        $match = self::processMatchData($json);

        // Bans data
        $bans = self::processBansData($json);

        return array($participants, $match, $bans);
    }



    // Recibe un json y retorna la data para un match 
    public static function processMatchData($json)
    {
        $teams = $json['teams'];
        if ($teams[0]['win'] == 'Win') {
            $winning_team = 100;
        } else {
            $winning_team = 200;
        }
        $match = array(
            "game_id"       => $json['gameId'],
            "winning_team"  => $winning_team,
            "game_version"  => $json['gameVersion'],
            "game_creation" => $json['gameCreation'],
            "season_id"     => $json['seasonId'],
            "game_duration" => $json['gameDuration'],
        );
        return $match;
    }



    // Recibe un json y retorna la data para 8 bans 
    public static function processBansData($json)
    {
        $bans = array();
        foreach ($json['teams'] as $team) {
            foreach ($team['bans'] as $ban) {
                $banned = array(
                    "gameId" => $json['gameId'],
                    "championId" => $ban["championId"],
                    "pickTurn" => $ban["pickTurn"],
                    "teamId" => $team["teamId"]
                );
                array_push($bans, $banned);
            }
        }
        return $bans;
    }



    // Recibe un json con datos de partida y retorna la data de 10 participants 
    public static function processParticipantsData($json)
    {
        // Data en array de los participantes (campeones, win/lose, spells)
        $participants = array();//almacena los 10 jugadores

        // tengo que crear un iterador porque la data de participant esta divididad,
        // por un lado esta en ['participantIdentities'][$i] y por otro en participant
        $i = 0;

        foreach ($json['participants'] as $participant) {
            $new = array();
            $new['gameId'] = $json['gameId'];
            $new['participantId'] = $participant['participantId'];
            $new['championId'] = $participant['championId'];
            $new['teamId'] = $participant['teamId'];
            $new['spell1Id'] = $participant['spell1Id'];
            $new['spell2Id'] = $participant['spell2Id'];

            if (!isset($json['participantIdentities'][$i]['player']['summonerId'])) {
                echo $new['gameId'];
            }

            $new['summonerId'] = $json['participantIdentities'][$i]['player']['summonerId'];
            $new['role'] = $participant['timeline']['role'];
            $new['lane'] = $participant['timeline']['lane'];
            $new['item0'] = $participant['stats']['item0'];
            $new['item1'] = $participant['stats']['item1'];
            $new['item2'] = $participant['stats']['item2'];
            $new['item3'] = $participant['stats']['item3'];
            $new['item4'] = $participant['stats']['item4'];
            $new['item5'] = $participant['stats']['item5'];
            $new['item6'] = $participant['stats']['item6'];

            array_push($participants, $new);
            $i++;
        }
        return $participants;
    }



    // Recibe un json con datos de partida y retorna la data de 10 users 
    public static function processUsersData($json)
    {
        // echo "\n" . $json["gameId"] . "\n";

        $users = array();
        foreach ($json['participantIdentities'] as $participant) {

            $new = array();
            $new["summoner_id"]         = $participant["player"]["summonerId"];
            $new["account_id"]          = $participant["player"]["accountId"];
            // $new["summoner_name"]       = iconv("UTF-8", "UTF-8", $participant["player"]["summonerName"]);
            $new["summoner_name"]       = $participant["player"]["summonerName"];
            $new["current_platform_id"] = $participant["player"]["currentPlatformId"];
            $new["modified"]            = "0000-00-00 00:00:00";
            array_push($users, $new);
        }
        return $users;
    }



    // Guarda data en la tabla match de bbdd
    private static function saveMatchInDB($game_data, $mysqli)
    {
        $stmt = $mysqli -> prepare('
            INSERT INTO matchs
            (game_id, winning_team, game_version, game_creation, season_id, game_duration)
            VALUES (?,?,?,?,?,?)
            ON DUPLICATE KEY UPDATE
            winning_team=VALUES(winning_team), game_version=VALUES(game_version), game_creation=VALUES(game_creation), season_id=VALUES(season_id), game_duration=VALUES(game_duration)'
        );

        if (
            $stmt &&
            $stmt -> bind_param('iisiii', $game_data['game_id'], $game_data["winning_team"], $game_data["game_version"], $game_data["game_creation"], $game_data["season_id"], $game_data["game_duration"]) &&
            $stmt -> execute()
        ) {
            return true;
        } else {
            echo $mysqli->error;
            return $mysqli->error;
        }
    }



    // Guarda game_id en la tabla matchs_future de la bbdd
    private static function saveMatchFutureInDB($games, $mysqli)
    {
        $query = 'REPLACE INTO matchs_future (game_id) VALUES';
        $data_types = "";

        foreach ($games as $game_id) {

            $query .= "(?), ";
            $data_types .= "i";
        }

        $query = mb_substr($query, 0, -2); // borro la ultima coma

        $stmt = $mysqli -> prepare($query);

        if (
            $stmt &&
            $stmt -> bind_param($data_types, ...$games) &&
            $stmt -> execute()
        ) {
            return "success";
        } else {
            return $mysqli->error;
        }
    }





    // Guarda muchas matchs en la bbdd.
    public static function saveMultipleMatchs($games, $mysqli)
    {
        // valores que dependen de cuántos games haya
        $data_types = ""; 
        $values = "";
        $games_array_values = array(); // acá convierto en un array numérico [0 => 12 , 1 => "asd" , 2 => 45]
        $i = 0;

        // Tengo un array del tipo   : ("a" => 5, "b" => 5, "c" => "st")
        // Lo tengo que transformar a: ( 0  => 5,  1  => 5,  2  => "st")
        // Luego por cada game va a crecer el array pero va a ser con números siempre
        // (0 => 5, 1 => 5, 2  => "st", 3  => 5, 5 => 5, 6 => "st", 6 => 5, 7 => 5, 8 => "st")

        // itero para armar values de la forma (?,?),(?,?),(?,?)
        foreach ($games as $game_id) {
            $values .= "(?,?,?,?,?,?), ";
            $data_types .= "iisiii";
            // desempaqueta el array y pone todos los indices de los games
            // en valores numéricos
            $games_array_values = array_merge($games_array_values, array_values($games[$i]));
            $i++;
        }

        $values = mb_substr($values, 0, -2); // borro la ultima coma

        $query = '
            INSERT INTO matchs
            (game_id, winning_team, game_version, game_creation, season_id, game_duration)
            VALUES ' . $values . '
            ON DUPLICATE KEY UPDATE
            winning_team=VALUES(winning_team), game_version=VALUES(game_version), game_creation=VALUES(game_creation), season_id=VALUES(season_id), game_duration=VALUES(game_duration)';

        $stmt = $mysqli -> prepare($query);

        if (
            $stmt &&
            $stmt -> bind_param($data_types, ...$games_array_values) &&
            $stmt -> execute()
        ) {
            return $stmt->affected_rows;
        } else {
            return $mysqli->error;
        }
    }






    // Borra el array $games de la tabla matchs_future
    public static function deleteMatchFutureInDB($mysqli, $games)
    {
        $query = 'DELETE FROM matchs_future WHERE game_id IN(';
        $data_types = "";

        if (count($games) <= 0) {
            return "No has ingresado un array con suficientes games.";
        }

        foreach ($games as $game_id) {
            $query .= "?, ";
            $data_types .= "i";
        }

        $query = mb_substr($query, 0, -2); // borro la ultima coma
        $query .= ")";

        $stmt = $mysqli -> prepare($query);

        if (
            $stmt &&
            $stmt -> bind_param($data_types, ...$games) &&
            $stmt -> execute()
        ) {
            return "Éxito! Se han borrado, " . $stmt->affected_rows . " rows";
        } else {
            return $mysqli->error;
        }
    }



    // Checkea si el array pasado existe en la tabla matchs... 
    // recibe un array de game_id y retorna un array de game_id existentes
    public static function checkIfMatchsExistsInMatchs($games_array, $mysqli)
    {
        $query = 'SELECT game_id from matchs WHERE game_id IN(';
        $data_types = "";

        foreach ($games_array as $game_id) {

            $query .= "?, ";
            $data_types .= "i";
        }

        $query = mb_substr($query, 0, -2); // borro la ultima coma
        $query .= ")";

        $stmt = $mysqli -> prepare($query);

        $games_array_return = array();

        if (
            $stmt &&
            $stmt -> bind_param($data_types, ...$games_array) &&
            $stmt -> execute() &&
            $stmt -> bind_result($game_id)
        ) {

            while($stmt -> fetch()) {
                array_push($games_array_return, $game_id);
            }

            return $games_array_return;

        } else {
            return $mysqli->error;
        }
    }



    public static function setMatchFutureInDB($games)
    {
        // echo $game_id;
        $mysqli = self::connectToDB();
        return self::saveMatchFutureInDB($games, $mysqli);
    }



    // ESTO DEBERIA ESTAR EN UN CONTROLADOR
    // Guarda primero en tabla matchs y luego en tabla participants y users
    private static function storeDataInDB($data, $mysqli)
    {
        require_once __DIR__ . "/../models/Bans.php";
        require_once __DIR__ . "/../models/Participants.php";
        require_once __DIR__ . "/../models/Users.php";
        //$data[0] = participants;
        //$data[1] = match_data;
        //$data[2] = bans;

        $result = array(
            "matchs" => 0,
            "participants" => 0,
            "users" => 0,
            "bans" => 0
        );

        if (self::saveMatchInDB($data[1], $mysqli)) {
            $result["matchs"] = 1;
        }

        $participants = $data[0];

        foreach ($participants as $participant){

            if (Participants::saveParticipantInDB($participant, $mysqli)) {
                $result["participants"] += 1;
            }

            if (Users::saveUserInDB($participant, $mysqli)) {
                $result["users"] += 1;
            }
        }

        $bans = $data[2];

        foreach ($bans as $ban) {

            if (Bans::saveBanInDB($ban, $mysqli)) {
                $result["bans"] += 1;
            }
        }

        return $result;
    }



    //hace de todo¿¿¿???
    private static function storeData($game_data)
    {
        $processed_data = self::processMatchData($game_data);
        $connection = self::connectToDB();

        $result = self::storeDataInDB($processed_data, $connection);

        if ($result["matchs"] == 1 && $result["participants"] == 10 && $result["users"] == 10 && $result["bans"] == 10) {
            return true;
        } else {
            return false;
        }
    }



    // Recibe match_id, pide info sobre el match, si es ranked retorna true, sino false
    private static function isRankedMatch($game_data)
    {
        if ($game_data[0]['queueId'] != 420) {
            return $game_data[0]['queueId'];
        } else {
            return true;
        }
    }



    // ESTO DEBERIA IR EN UN CONTROLADOR
    // Recibe un array de match_id y va preguntando si son o no son rankeds, si son guarda en bbdd
    public static function multipleMatchsAdding($array)
    {
        // tiempo en el que empieza la función, se usa para medir cuánto tomó
        $time_start = microtime(true); 

        // contador de partidas que fueron almacenadas...
        $number_of_saves = 0; 

        // guarda todas las matchs que tuvieron problema en la api error (429)
        $matchs_with_api_error = array();

        foreach ($array as $match_id) {

            $game_data = self::getDataFromRiotAPI($match_id);
            $response_code = self::existsMatch($game_data);

            if ($response_code === true) { 

                $queue = self::isRankedMatch($game_data);

                if ($queue === true) {

                    self::storeData($game_data[0]);
                    echo "Guardado " . $match_id . "<br>";
                    $number_of_saves += 1;

                } else {

                    echo "No es ranked " . $match_id . " es " . $queue .  "<br>";

                }

            // $game_data es false por lo tanto no existe el match (404)
            } else { 

                echo "Error " . $response_code . " en " . $match_id . "<br>";

                if ($response_code != 404) {
                    array_push($matchs_with_api_error, $match_id);
                }

            }

        }

        $time_end = microtime(true);

        $execution_time = intval($time_end - $time_start);

        $result = array(
            "matchs_with_api_error" => $matchs_with_api_error,
            "number_of_saves" => $number_of_saves,
            "execution_time" => $execution_time
        );

        return $result;

    }



    // Selecciona 100 rows de matchs o matchs_future,
    // recibe $from (tabla)
    // retorna array de 100 matchs 
    public static function select100Matchs($mysqli, $from)
    {
        $stmt = $mysqli -> prepare('SELECT game_id FROM ' . $from . ' LIMIT 100 OFFSET 0');

        if (
            $stmt &&
            $stmt -> execute() &&
            $stmt -> store_result() &&
            $stmt -> bind_result($game_id)
        ) { 
            $array = array();
            while ($stmt -> fetch()) {
                array_push($array, $game_id);
            }

            return $array;

        } else {
            echo 'Prepared Statement Error';
        }
    }


    private static function selectLastMatch($mysqli)
    {
        $stmt = $mysqli -> prepare('SELECT game_id FROM matchs ORDER BY game_id DESC LIMIT 1;');

        if (
            $stmt &&
            $stmt -> execute() &&
            $stmt -> store_result() &&
            $stmt -> bind_result($game_id) &&
            $stmt -> fetch()
        ) { 

            return $game_id;

        } else {
            echo 'Prepared Statement Error';
        }
    }



    // Devuelve la última partida
    public static function getLastMatch()
    {
        $mysqli = self::connectToDB();
        $game_id = self::selectLastMatch($mysqli);
        return $game_id;
    }



    // asd
    public static function getAllMatchs()
    {
        $mysqli = self::connectToDB();
        $result = self::selectAllMatchs($mysqli);

        return $result;

    }



    // guarda una partida en error_429 asi luego puedo 
    public static function save429ErrorInDB($game_id)
    {

        $mysqli = self::connectToDB();

        $stmt1 = $mysqli -> prepare('
            INSERT INTO error_429 
            (game_id) 
            VALUES (?)
        ');

        if (
            $stmt1 &&
            $stmt1 -> bind_param('i', $game_id) &&
            $stmt1 -> execute()
        ) {
            return true;
        } else {
            return $mysqli->error;
        }

    }



    // Selecciona de bbdd las partidas que sean o no sean duplicadas 
    // depende de $duplicates (bool)
    // retorna array de 100 partidas
    public static function selectDuplicatesOrNot($mysqli, $duplicates)
    {
        if ($duplicates == true) { 
            $duplicates = '';
        } else {
            $duplicates = 'where m.game_id is null';
        }

        $query =    'select f.game_id from matchs_future f
                    left join matchs m on f.game_id = m.game_id ' .
                    $duplicates
                    . ' limit 100';

        $stmt = $mysqli -> prepare($query);

        if (
            $stmt &&
            $stmt -> execute() &&
            $stmt -> bind_result($game_id)
        ) {
            $games_array = array();
            while($stmt -> fetch()) {
                array_push($games_array, $game_id);
            }
            return $games_array;
        } else {
            return $mysqli->error;
        }
    }



    // Recibe array de jsons con data de partidas, también el match_id.
    // Retorna game_id array con las partidas que fueron guardadas exitosamente.
    public static function saveMatchsFromExternalJSON($array_json)
    {
        $success_records = array();
        foreach ($array_json as $id => $json) {
            if (self::storeData($json)) {
                array_push($success_records, $json["gameId"]);
            }
            echo $json["gameId"] . " ";
        }
        return $success_records;
    }

}






