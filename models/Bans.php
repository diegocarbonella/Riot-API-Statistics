<?php 

require_once 'Database.php';

Class Bans extends Database {


    // Crea/actualiza en la tabla ban de bbdd
    public static function saveBanInDB($ban, $mysqli)
    {
        $stmt1 = $mysqli -> prepare('
            REPLACE INTO bans 
            (game_id , champion_id, pick_turn, team_id) 
            VALUES (?,?,?,?)
        ');

        if (
            $stmt1 &&
            $stmt1 -> bind_param('iiii', $ban["gameId"], $ban["championId"], $ban["pickTurn"], $ban["teamId"]) &&
            $stmt1 -> execute()
        ) {
            return true;
        } else {
            echo $mysqli->error;
            return $mysqli->error;
        }
    }



    // Crea/actualiza multiples registros en la tabla ban de bbdd
    // Recibe bans array y retorna success
    public static function saveMultipleBans($bans, $mysqli)
    {
        // valores que dependen de cuántos bans haya
        $data_types = ""; 
        $values = "";
        $bans_array_values = array();
        $i = 0;

        // itero para armar values de la forma (?,?),(?,?),(?,?)
        foreach ($bans as $ban) {
            $values .= "(?,?,?,?), ";
            $data_types .= "iiii";
            // desempaqueta el array y pone todos los indices de los games
            // en valores numéricos
            $bans_array_values = array_merge($bans_array_values, array_values($bans[$i]));
            $i++;
        }

        $values = mb_substr($values, 0, -2); // borro la última coma

        $query = '
            REPLACE INTO bans
            (game_id, champion_id, pick_turn, team_id)
            VALUES ' . $values;

        $stmt = $mysqli -> prepare($query);

        if (
            $stmt &&
            $stmt -> bind_param($data_types, ...$bans_array_values) &&
            $stmt -> execute()
        ) {
            return $stmt->affected_rows;
        } else {
            return $mysqli->error;
        }
    }


}

?>