<?php

session_start();


$mysqli_link=mysqli_connect(DB_HOST,DB_USER,DB_PASSWORD);
if(!$mysqli_link){
	echo  "Error: error de conexion con mysql";
	exit;
}
if(!mysqli_select_db($mysqli_link,DB_DATABASE)){
	echo "Error: error accediendo a la base de datos";
	exit;
}


// Funciones
function validar($string){
	if ( $string && !preg_match("/^[a-zA-Z0-9\-\,\ ]+$/",$string) ){
			echo "Error de validacion: >".$string."<";
			exit;
	}
	return $string;
}



function traer_gear(){
	
	global $access_token, $athlete, $mysqli_link;

	$sql = "Select distinct gear_id 
	               from tracks 
	         where gear_id != '' and
			       athlete = '$athlete' and
			       gear_id not in (select id from gears where athlete='$athlete') ";

	$Resp= mysqli_query($mysqli_link,$sql);
	
	while($row=mysqli_fetch_array($Resp)){
		
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, "https://www.strava.com/api/v3/gear/".$row["gear_id"]);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization: Bearer '.$access_token));
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE );
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE );
		curl_setopt($ch, CURLOPT_HEADER, false);
		$output = curl_exec($ch);
		$info = curl_getinfo($ch);
		curl_close($ch);	
			
		$gear = json_decode($output,true);
		
		if($gear["name"]){
			
			$name = scape($gear["name"]);
			$id   = $gear["id"];
			
			$slq2 = "insert into gears (id,athlete,name)
			                     values('$id','$athlete','$name') ";
								 
			$Resp2 = mysqli_query($mysqli_link,$slq2);
			
		}
		
	}

	return;
}



function traer_tracks(){
	
	global $access_token, $athlete, $mysqli_link;
	
	$sql = "select y,m from busq where athlete='$athlete'";
	$Resp=mysqli_query($mysqli_link,$sql);
	
	if( mysqli_num_rows($Resp)==0 )	{
		
		$sql2 = "select year(start_date_local) as y,
	                month(start_date_local) as m
	                from tracks where athlete='$athlete' order by start_date_local desc limit 1";
			  
			  
		$Resp2= mysqli_query($mysqli_link,$sql2);
	
		if(!mysqli_num_rows($Resp2)){
			
			$after = mktime(0,0,0,1,1,1970);
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, "https://www.strava.com/api/v3/athlete/activities?after=".$after."&per_page=1");
			curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization: Bearer '.$access_token));
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE );
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE );
			curl_setopt($ch, CURLOPT_HEADER, false);
			$output = curl_exec($ch);
			$info = curl_getinfo($ch);
			curl_close($ch);
		
			if($info["http_code"] != "200"){
				echo $info["http_code"];
				return;
			}
		
			$tracks = json_decode($output,true);
		
			$y = substr($tracks[0]["start_date_local"],0,4);
			$m = substr($tracks[0]["start_date_local"],5,2);
		
		}else{
		
			$row2 = mysqli_fetch_array($Resp2);
			$y = $row2["y"];
			$m = $row2["m"];
			
		}
	
		$sql3 = "insert into busq (athlete,y,m) values('$athlete',$y,$m); ";
		$Resp3 = mysqli_query($mysqli_link,$sql3);	

        if(!$Resp3) {
            logger("Error sql: ". $sql3);
        }

	}else{
		
		$row = mysqli_fetch_array($Resp);
		$y = $row["y"];
		$m = $row["m"];
		
	}

	
	$after = mktime(0,0,0,$m,1,$y);
	$before= mktime(0,0,0,$m+1,1,$y);

	
	if($after > mktime()){
		echo "--";
		exit;
	}else{
		echo date("m-Y",$after);
	}
	
	
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, "https://www.strava.com/api/v3/athlete/activities?after=".$after."&before=".$before);
	curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization: Bearer '.$access_token));
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE );
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE );
	curl_setopt($ch, CURLOPT_HEADER, false);
	$output = curl_exec($ch);
	$info = curl_getinfo($ch);
	curl_close($ch);

    logger("response: ". $info["http_code"]);

	if($info["http_code"] != "200"){
		echo $info["http_code"];
		return;
	}	
	
	$tracks = json_decode($output,true);

    logger("tracks: ". print_r($tracks,true));
	
	foreach($tracks as $track){
		
		$id = $track["id"];
		$external_id = $track["external_id"];
		$name = scape($track["name"]);
		$distance = $track["distance"];
		$moving_time = $track["moving_time"];
		$total_elevation_gain = $track["total_elevation_gain"];
		$type = $track["type"];
		$start_date_local =  date('Y-m-d h:i:s', strtotime($track["start_date_local"])) ;
		$average_speed = $track["average_speed"];
		$gear_id = $track["gear_id"];
		$location_city = scape($track["location_city"]);
		$location_state = scape($track["location_state"]);
		$location_country = scape($track["location_country"]);
		
		$achievement_count = $track["achievement_count"];
		$kudos_count = intval($track["kudos_count"]);
		$average_heartrate = intval($track["average_heartrate"]);
		$max_heartrate = intval($track["max_heartrate"]);
		
		$elev_high = floatval($track["elev_high"]);
		$elev_low = floatval($track["elev_low"]);
		$start_latlng = $track["start_latlng"][0].", ".$track["start_latlng"][1];
		$end_latlng =   $track["end_latlng"][0].", ".$track["end_latlng"][1];
		
		$workout_type = intval($track["workout_type"]);
		$average_cadence = floatval($track["average_cadence"]);
		$average_temp = floatval($track["average_temp"]);
		$average_watts = floatval($track["average_watts"]);
		$suffer_score = intval($track["suffer_score"]);

		$calories = floatval($track["calories"]);
		$device_name = $track["device_name"];
		
		$highlighted_kudosers = json_encode($track["highlighted_kudosers"]);
		$gear = json_encode($track["gear"]);
		$segment_efforts = json_encode($track["segment_efforts"]);
		
		$track_ = json_encode($track);


        $sql = "INSERT INTO tracks
                        (athlete,id,external_id,name,distance,
                        moving_time,total_elevation_gain,type,start_date_local,average_speed,
                        gear_id,location_city,location_state,location_country,achievement_count,
                        kudos_count,average_heartrate,max_heartrate,elev_high,elev_low,
                        start_latlng,end_latlng,workout_type,average_cadence,average_temp,
                        average_watts,suffer_score,calories,device_name,gear,
                        segment_efforts,highlighted_kudosers,track)
                VALUES ('$athlete','$id','$external_id','$name','$distance',
                        '$moving_time','$total_elevation_gain','$type','$start_date_local','$average_speed',
                        '$gear_id','$location_city','$location_state','$location_country',$achievement_count,
                        $kudos_count,$average_heartrate,$max_heartrate,$elev_high,$elev_low,
                        '$start_latlng','$end_latlng',$workout_type,$average_cadence,$average_temp,
                        $average_watts,$suffer_score,$calories,'$device_name','$gear',
                        '$segment_efforts','$highlighted_kudosers','$track_' )";

        $Resp= mysqli_query($mysqli_link,$sql);

        if(!$Resp){
            logger("Error sql: " . $sql);
        }
			
	}
	
	$m++;
	
	$sql = "update busq set m='$m' where athlete='$athlete'";
	$Resp= mysqli_query($mysqli_link,$sql);
	
    if(!$Resp){
        logger("Error sql: " . $sql);
    }
		
}


function scape($str){
	
	return str_replace(array('\\', "\0", "\n", "\r", "'", '"', "\x1a"),
					   array('\\\\', '\\0', '\\n', '\\r', "\\'", '\\"', '\\Z'),
					   $str);
	
}

Function logger($logMSG,$nivel=0) {
    if ($nivel <= logLEVEL){
        $logFP=fopen("logs/cliente-".date("Ymd").".log","a");
        fputs($logFP, date('Y-m-d H:i:s') ."|" . $nivel . "| -> $logMSG\n");
        fclose($logFP);
    }
}


?>
