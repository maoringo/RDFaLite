<?php
include_once("RDFaLite.php");
function parse_item(&$item){
//    print_r($item);//." aa \n";
    $type = $item->type[0];
//    $type = substr(strrchr($type,"/"),1);
   foreach($item->properties as $property => $values){
        if($property == "taxon" or $property == "seeAlso"){//taxonまたはseeAlsoの場合にはNestに配慮
            $nestproperty = $property;
           foreach($values as $value){
               if(is_object($value)){//Nest or not
                   parse_item2($value,$nestproperty);
                 }else{
                    print "@".$type."_".$property."=".$value."\n";
                 }
           }
        }
       else{
        foreach($values as $value){
               if(is_object($value)){//Nest or not
                   parse_item($value);
                 }else{
                    print "@".$type."_".$property."=".$value."\n";
        }
   } 
}
}
}

//In case of Nest
function parse_item2(&$item,&$nestproperty){
    $type = $item->type[0];
   // $type = substr(strrchr($type,"/"),1);
   foreach($item->properties as $property => $values){
       foreach($values as $value){
               if(is_object($value)){//Nest or not
                   parse_item2($value,$nestproperty);
                 }else{
                    print "@BiologicalDatabaseEntry_".$nestproperty."_".$type."_".$property."=".$value."\n";
        }
}
}
}

$url = $argv[1];
    echo $url."\n";
$md = new RDFaLite($url); //note the class name is the same as original
$data = $md->obj(); //$md->obj() if need PHP object
foreach($data as $item){
    parse_item($item);
}


?>
