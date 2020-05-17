<?php

Class Participants {


    
    // Guarda data en la tabla participant de bbdd
    public static function saveParticipantInDB($participant, $mysqli)
    {
        $stmt1 = $mysqli -> prepare('
            REPLACE INTO participants 
            (game_id, participant_id, champion_id, team_id, spell1_id, spell2_id, summoner_id, role, lane, item0, item1, item2, item3, item4, item5, item6) 
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
        ');

        if (
            $stmt1 &&
            $stmt1 -> bind_param('iiiiiisssiiiiiii', 
                $participant["gameId"], 
                $participant['participantId'], 
                $participant["championId"], 
                $participant["teamId"], 
                $participant["spell1Id"], 
                $participant["spell2Id"], 
                $participant["summonerId"], 
                $participant["role"], 
                $participant["lane"],
                $participant["item0"], $participant["item1"], $participant["item2"], $participant["item3"], $participant["item4"], $participant["item5"], $participant["item6"]) &&
            $stmt1 -> execute()
        ) {
            return true;
        } else {
            echo $mysqli->error;
            return $mysqli->error;
        }
    }





    // Crea/actualiza multiples registros en la tabla participants de bbdd
    // Recibe bans array y retorna success
    public static function saveMultipleParticipants($participants, $mysqli)
    {
        // valores que dependen de cuántos bans haya
        $data_types = ""; 
        $values = "";
        $participants_array_values = array();
        $i = 0;

        // itero para armar values de la forma (?,?),(?,?),(?,?)
        foreach ($participants as $participant) {
            $values .= "(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?), ";
            $data_types .= "iiiiiisssiiiiiii";
            // desempaqueta el array y pone todos los indices de los games
            // en valores numéricos
            $participants_array_values = array_merge($participants_array_values, array_values($participants[$i]));
            $i++;
        }

        // print_r($participants_array_values);

        $values = mb_substr($values, 0, -2); // borro la última coma

        $query = '
            REPLACE INTO participants
            (game_id, participant_id, champion_id, team_id, spell1_id, spell2_id, summoner_id, role, lane, item0, item1, item2, item3, item4, item5, item6) 
            VALUES ' . $values;

        $stmt = $mysqli -> prepare($query);

        // return true;

        if (
            $stmt &&
            $stmt -> bind_param($data_types, ...$participants_array_values) &&
            $stmt -> execute()
        ) {
            return $stmt->affected_rows;
        } else {
            return $mysqli->error;
        }
    }








}


