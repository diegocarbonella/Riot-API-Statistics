<?php

require 'Database.php';

class Champions extends Database {



    // Retorna cuántas filas hay en la tabla matchs
    public static function getNumberOfMatchs() 
    {
        $mysqli = self::connectToDB();
        $esto = self::howManyMatchs($mysqli);
        return $esto;
    }



    // Retorna cuántas partidas hay
    private static function howManyMatchs($mysqli)
    {
        $stmt = $mysqli -> prepare('SELECT count(*) FROM matchs;');

        if (
            $stmt &&
            $stmt -> execute() &&
            $stmt -> store_result() 
        ) { 

            return $stmt->num_rows;

        } else {
            return 'Prepared Statement Error';
        }
    }



    // Retorna las veces que un campeón gano
    private static function selectWins($mysqli, $champion_id)
    {
        $stmt = $mysqli -> prepare('
            SELECT
            p.game_id
            FROM participants p
            INNER JOIN matchs m ON (m.game_id = p.game_id)
            WHERE p.champion_id = ? AND p.team_id = m.winning_team
        ');

        if (
            $stmt &&
            $stmt -> bind_param('i', $champion_id) &&
            $stmt -> execute() &&
            $stmt -> store_result() 
        ) { 

            $times = 0;

            while ($stmt -> fetch()) {

                $times += 1;
            }

            return $times;

        } else {
            return 'Prepared Statement Error';
        }
    }



    private static function selectAll($mysqli, $champion_id)
    {
        $stmt = $mysqli -> prepare('
            SELECT
            p.team_id,
            m.winning_team
            FROM participants p
            INNER JOIN matchs m ON (m.game_id = p.game_id)
            WHERE p.champion_id = ?
        ');

        if (
            $stmt &&
            $stmt -> bind_param('i', $champion_id) &&
            $stmt -> bind_result($team_id, $winning_team) &&
            $stmt -> execute() &&
            $stmt -> store_result() 
        ) { 

            $games = 0;
            $wins = 0;

            while ($stmt->fetch()) {
                $games += 1;
                if ($team_id == $winning_team) {
                    $wins++;
                }
                echo $team_id . " " .$winning_team . "<br>";
            }
            
            return array("games" => $games, "wins" => $wins);

        } else {
            return 'Prepared Statement Error';
        }
    }



    // Selecciona todas las partidas jugadas y wins por campeon, agrupa por campeon    
    private static function selectPickWin($mysqli)
    {
        $stmt = $mysqli -> prepare('
        SELECT 
        champion_id as champ,
        count(champion_id),
        (
            SELECT count(matchs.winning_team) 
            FROM participants
            INNER JOIN matchs ON (matchs.game_id = participants.game_id) 
            WHERE participants.champion_id = champ 
            AND matchs.winning_team = participants.team_id
        ) as wins
        FROM participants 
        GROUP BY champion_id ORDER BY champion_id
        ');

        if (
            $stmt &&
            $stmt -> bind_result($champion_id, $games, $wins) &&
            $stmt -> execute() &&
            $stmt -> store_result() 
        ) { 

            $array = array();

            while ($stmt->fetch()) {
                $array[$champion_id] = array("games" => $games, "wins" => $wins); 
            }
            
            return $array;

        } else {
            return 'Prepared Statement Error';
        }
    }



    public static function getTimesChampionWasPlayed($champion_id)
    {
        $mysqli = self::connectToDB();
        $times = self::selectAll($mysqli, $champion_id);
        return $times;
    } 



    // retorna array de campeones y su pickrate
    public static function getPickRate()
    {
        $mysqli = self::connectToDB();
        return self::selectPickWin($mysqli);
    } 




}