<?php 

require_once 'Database.php';

Class Users extends Database {


    public static function selectLastAccountId($mysqli)
    {
        $stmt = $mysqli -> prepare('SELECT account_id FROM users WHERE modified = "0000-00-00 00:00:00" LIMIT 1;');
        $stmt2 = $mysqli -> prepare('UPDATE users SET modified = NOW() WHERE modified = "0000-00-00 00:00:00" LIMIT 1;');

        if (
            $stmt &&
            $stmt -> execute() &&
            $stmt -> store_result() &&
            $stmt -> bind_result($account_id) &&
            $stmt -> fetch()
        ) { 

            $stmt2 -> execute();

            return $account_id;

        } else {
            echo 'Prepared Statement Error';
        }
    }



    // Crea/actualiza en la tabla users de bbdd
    public static function saveUserInDB($user, $mysqli)
    {
        // print_r($user);
        // echo $user["summonerName"];
        // tengo que "traducirlo", con carácteres especiales tira error
        $summonerName = iconv("UTF-8", "ISO-8859-1//TRANSLIT", $user["summonerName"]);

        $modified = "0000-00-00 00:00:00";

        // echo $summonerName;

        $stmt1 = $mysqli -> prepare('
            REPLACE INTO users 
            (summoner_id, account_id, summoner_name, current_platform_id, modified) 
            VALUES (?,?,?,?,?)
        ');

        if (
            $stmt1 &&
            $stmt1 -> bind_param('sssss', $user["summonerId"], $user["accountId"], $summonerName, $user["currentPlatformId"], $modified) &&
            $stmt1 -> execute()
        ) {
            // echo "guardado $summonerName";
            return true;
        } else {
            echo $mysqli->error;
            return $mysqli->error;
        }
    }



    // Crea/actualiza multiples registros en la tabla ban de bbdd
    // Recibe bans array y retorna success
    public static function saveMultipleUsers($users, $mysqli)
    {
        $mysqli->set_charset("utf8mb4");
        $data_types = ""; 
        $values = "";
        $users_array_values = array();
        $i = 0;

        foreach ($users as $user) {
            $values .= "(?,?,?,?,?), ";
            $data_types .= "sssss";
            $users_array_values = array_merge($users_array_values, array_values($users[$i]));
            $i++;
        }

        $values = mb_substr($values, 0, -2); // borro la última coma

        $query = '
            REPLACE INTO users
            (summoner_id, account_id, summoner_name, current_platform_id, modified) 
            VALUES ' . $values;

        $stmt = $mysqli -> prepare($query);

        if (
            $stmt &&
            $stmt -> bind_param($data_types, ...$users_array_values) &&
            $stmt -> execute()
        ) {
            return $stmt->affected_rows;
        } else {
            return $mysqli->error;
        }
    }






}

?>