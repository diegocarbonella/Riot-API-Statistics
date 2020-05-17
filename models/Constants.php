<?php

class Constants {


    private static function readJsonFile()
    {
        $str = file_get_contents(__DIR__ . '/../info/champion.json');
        $json = json_decode($str, true);
        return $json["data"];
    }



    private static function processJson($champions)
    {
        $data = array();
        foreach ($champions as $champion){
            $key = $champion["key"];
            $id = $champion["id"];
            array_push($data, array("key" => $key, $id => $key));
        }
        return $data;
    }



    public static function dale()
    {
        $data = self::readJsonFile();
        $data = self::processJson($data);
        return $data;
    }


}