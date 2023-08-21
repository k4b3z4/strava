<?php

include("config.php");
include("func.php");


function Mostrar(){
	
	global $_GET;
	global $mysqli_link;
	
	$athlete   = validar($_GET["a"]);
    $share     = validar($_GET["share"]);
	$desde     = validar($_GET["d"]);
	$hasta     = validar($_GET["h"]);
	
	$type      = validar($_GET["type"]);
	$wtype     = validar($_GET["wtype"]);
	$location  = validar($_GET["location"]);
	$gear_id   = validar($_GET["gear_id"]);
	$hr        = validar($_GET["hr"]);
	
	
		
	include("pChart/class/pData.class.php");
	include("pChart/class/pDraw.class.php"); 
	include("pChart/class/pImage.class.php"); 

	$mismo_mes = false;
	$mismo_anio = false;
	
	$periodo = Array();
	
	$desde_y = substr($desde,0,4);
	$desde_m = substr($desde,5,2);
	$desde_d = substr($desde,8,2);

	$hasta_y = substr($hasta,0,4);
	$hasta_m = substr($hasta,5,2);
	$hasta_d = substr($hasta,8,2);
	
	
	if( !checkdate($desde_m,$desde_d,$desde_y) or
		 !checkdate($hasta_m,$hasta_d,$hasta_y) or
		 (!date_diff( date_create($hasta), date_create($desde) )->invert and $desde != $hasta ) ) {
		exit;
	}
	
	
	/// El Mismo MES
	
	if( $desde_y == $hasta_y and $desde_m == $hasta_m ){    
		$periodoP = "Y-m-d";
		$periodoM = "%Y-%m-%d";
		$links = false;
		$x = 0;
		while( date($periodoP, mktime(0,0,0,$desde_m,$desde_d+$x-1,$desde_y)) <
               date($periodoP, mktime(0,0,0,$hasta_m,$hasta_d , $hasta_y)) ){
			array_push($periodo,date($periodoP, mktime(0,0,0, $desde_m, $desde_d+$x, $desde_y)) );  // agrego el periodo
			$x=$x+7;
		}
		$mismo_mes = true;
		
	/// El Mismo Anio	
		
	}elseif( $desde_y == $hasta_y and $desde_m != $hasta_m ) {
		$periodoP = "Y-m";
		$periodoM = "%Y-%m";
		$links = true;
		$x = 0;
		while( date($periodoP, mktime(0,0,0,$desde_m+$x-1,$desde_d,$desde_y)) != $hasta_y."-".$hasta_m ){
			array_push($periodo,date($periodoP, mktime(0,0,0,$desde_m+$x,$desde_d,$desde_y)) );  // agrego el periodo
			$x++;
		}
	
	/// Varios Anios	
	
	}else{
		$periodoP = "Y";
		$periodoM = "%Y";
		$links = true;
		$x = 0;
		while( date($periodoP, mktime(0,0,0,$desde_m,$desde_d,$desde_y+$x-1)) != $hasta_y ){
			array_push($periodo,date($periodoP, mktime(0,0,0,$desde_m,$desde_d,$desde_y+$x)) );  // agrego el periodo
			$x++;
		}
		$mismo_anio = true;		
	}
	
	
	if($type)      $where = " and type='$type' ";
	if($wtype)     $where = " and workout_type='$wtype' ";
	if($location)  $where.= " and concat(location_state,', ',location_city)='$location' ";
	if($gear_id)   $where.= " and gear_id='$gear_id' ";
	if($hr){
					list($min,$max) = explode("-",$hr,2);
					$where.= " and average_heartrate >= $min and average_heartrate <= $max ";
	}
	
	
	$sql = "SELECT SUM(distance) as distancia,
				   SUM(total_elevation_gain) as elevacion,
				   SUM(moving_time) as tiempo,
	               DATE_FORMAT( start_date_local, '$periodoM') as periodo
	         from tracks
				where start_date_local >= '$desde 00:00:00' and
				      start_date_local <= '$hasta 23:59:59' and
					  athlete='$athlete' $where
				GROUP by periodo
				ORDER by periodo";				
	
	
	$Resp= mysqli_query($mysqli_link,$sql);
	if(!$Resp) { echo $sql; exit; }
	
	$cantidad = mysqli_num_rows($Resp);
	
	while($row = mysqli_fetch_array($Resp)){
		$dato["distancia"][$row["periodo"]] = $row["distancia"]/1000;
		$dato["elevacion"][$row["periodo"]] = $row["elevacion"];
		$dato["tiempo"][$row["periodo"]] = $row["tiempo"]/3600;

		$horas = 0;
		$minutos = floor( $row["tiempo"] / 60) ;
		
		while($minutos >= 60){
			$minutos -= 60;
			$horas++;
		}
		
		if($links){
			if($mismo_anio){
				$desde_ = $row["periodo"]."-01-01";
				$hasta_ = date("Y-m-d", mktime(0,0,0, 01 , 0 , $row["periodo"] + 1));
			}else{
				$desde_ = substr($row["periodo"],0,7) ."-01";
				$hasta_ = date("Y-m-d", mktime(0,0,0, substr($desde_,5,2) +1 , 0, substr($desde_,0,4)));
			}
			$url = "index.php?type=$type&wtype=$wtype&location=$location&gear_id=$gear_id&hr=$hr&d=$desde_&h=$hasta_&share=$share";
		}
		
		//$dato["tiempo_value"][$row["periodo"]] = $horas."h ".$minutos."m;$url";
		$dato["tiempo_value"][$row["periodo"]] = $horas."h ".$minutos."m;$url";
		
		$dato["distancia_value"][$row["periodo"]] = intval($row["distancia"]/1000) ."km;$url";
		$dato["elevacion_value"][$row["periodo"]] = intval($row["elevacion"]) . "m;$url";
		
		array_push($periodo,$row["periodo"]);
	}
	
	
	
	
// elimino los periodos repetidos y ordeno 
 
	$periodo = array_unique($periodo);
	asort($periodo);


// completo con 0 los faltantes
	
	foreach($periodo as $per){
		foreach($dato as $id => $array){
			if( $dato[$id][$per] == '' ) $dato[$id][$per] = 0;
		}
	}

// ordeno los datos por periodos (los que agregue en 0 quedaron al final)
	
	foreach($dato as $key => $array){
		ksort($dato[$key]);
	}
	
	
// Transformo las etiquetas

	foreach($periodo as $key => $per ){		
		if(!$mismo_anio){
			if($mismo_mes){
				list($anio,$mes,$dia) = explode ( "-" , $periodo[$key] );
				$periodo[$key] = $dia;
			}else{
				list($anio,$mes) = explode( "-" , $periodo[$key] );
				$periodo[$key] = $mes."-".$anio;
			}
		}
	}

	
	/*
	print_r( $dato["tiempo"]);
	print_r( $dato["distancia"]);
	print_r( $dato["elevacion"]);
	
	exit;
	*/
	
// ************************* GRAFICO !!!!! **************************************

	$MyData = new pData();  
	

	$MyData->addPoints($dato["distancia"],"distancia"); 
	$MyData->setSerieOnAxis("distancia",0);

	$MyData->addPoints($dato["tiempo"],"tiempo");	 
	$MyData->setSerieOnAxis("tiempo",1);
	
	$MyData->addPoints($dato["elevacion"],"elevacion");
	$MyData->setSerieOnAxis("elevacion",2);
	
	$MyData->addPoints($periodo,"Labels"); 
	$MyData->setSerieDescription("Labels","Months"); 
	$MyData->setAbscissa("Labels"); 
	
	$MyData->setAxisPosition(0,AXIS_POSITION_LEFT);
	$MyData->setAxisName(0,"kilometros");
	$MyData->setAxisUnit(0,"");
	
	$MyData->setAxisPosition(1,AXIS_POSITION_LEFT);
	$MyData->setAxisName(1,"horas");
	$MyData->setAxisUnit(1,"");	

	$MyData->setAxisPosition(2,AXIS_POSITION_RIGHT);
	$MyData->setAxisName(2,"metros");
	$MyData->setAxisUnit(2,"");
	
	
	
	/* Create the pChart object */ 
	$myPicture = new pImage(1100,360,$MyData);
	
	/* Image map*/
	if (isset($_GET["ImageMap"]) || isset($_POST["ImageMap"]))
		$myPicture->dumpImageMap("pChartGraf",IMAGE_MAP_STORAGE_SESSION);
		
		
	$myPicture->initialiseImageMap("pChartGraf",IMAGE_MAP_STORAGE_SESSION);
	

	/* Draw the background */ 
	$Settings = array("R"=>44, "G"=>44, "B"=>44, "Dash"=>1, "DashR"=>55, "DashG"=>55, "DashB"=>55); 
	$myPicture->drawFilledRectangle(0,0,1100,360,$Settings); 

	/* Overlay with a gradient */ 
	$Settings = array("StartR"=>44, "StartG"=>44, "StartB"=>44, "EndR"=>11, "EndG"=>11, "EndB"=>11, "Alpha"=>50); 
	$myPicture->drawGradientArea(0,0,1100,360,DIRECTION_VERTICAL,$Settings); 
	//$myPicture->drawGradientArea(0,0,850,20,DIRECTION_VERTICAL,array("StartR"=>0,"StartG"=>0,"StartB"=>0,"EndR"=>50,"EndG"=>50,"EndB"=>50,"Alpha"=>80)); 

	/* Add a border to the picture */ 
	$myPicture->drawRectangle(0,0,1099,359,array("R"=>0,"G"=>0,"B"=>0)); 
  
	/* Write the picture title */  
	//$myPicture->setFontProperties(array("FontName"=>"pChart/fonts/Silkscreen.ttf","FontSize"=>6)); 
	//$myPicture->drawText(10,13,"" ,array("R"=>255,"G"=>255,"B"=>255));  


	/* Draw the scale and the 1st chart */ 
	$myPicture->setGraphArea(90,40,1050,340); 
	$myPicture->drawFilledRectangle(90,40,1050,340,array("R"=>255,"G"=>255,"B"=>255,"Surrounding"=>-200,"Alpha"=>10)); 

	
	if( count($periodo)>10 ){
		$skip = intval(count($periodo)/10) - 1;
	}else{
		$skip = 0;
	}
	
	$myPicture->setFontProperties(array("FontName"=>"pChart/fonts/pf_arma_five.ttf","FontSize"=>12,"R"=>200,"G"=>200,"B"=>200));
	
	
	// TITLE
	if( $mismo_anio){
			$skip = 0;
			$myPicture->drawText(500,20, $desde_y. "-" . $hasta_y  ,array("R"=>200,"G"=>200,"B"=>200));
	}else{
		if( $mismo_mes ){
			$skip = 0; 
			$myPicture->drawText(500,20, $mes."-".$anio  ,array("R"=>200,"G"=>200,"B"=>200));  	
		}else{
			$myPicture->drawText(450,20, $desde_d."-".$desde_m."-".$desde_y . " - " .
	  							         $hasta_d."-".$hasta_m."-".$hasta_y ,array("R"=>200,"G"=>200,"B"=>200));  			
		}
	}
	
	$myPicture->setFontProperties(array("FontName"=>"pChart/fonts/pf_arma_five.ttf","FontSize"=>7));
	
	$myPicture->drawScale(array("DrawSubTicks"=>FALSE,
										 "LabelingMethod"=>LABELING_ALL,
										 "LabelRotation"=>0,
										 "Mode"=>SCALE_MODE_START0,
										 "LabelSkip"=> $skip )); 

										 
										 
	$myPicture->setShadow(TRUE,array("X"=>1,"Y"=>1,"R"=>0,"G"=>0,"B"=>0,"Alpha"=>10)); 
	$myPicture->setFontProperties(array("FontName"=>"pChart/fonts/pf_arma_five.ttf","FontSize"=>6)); 

   
	$myPicture->drawBarChart(array( "DisplayValues"=>FALSE,
								    "DisplayColor"=>DISPLAY_AUTO,
								    "Rounded"=>TRUE,
 									 "Surrounding"=>2,
									 "AroundZero"=>1,
									 "RecordImageMap"=>TRUE)); 
 
 
	$LabelSettings = array("DrawVerticalLine"=>FALSE,"NoTitle"=>TRUE,"BoxWidth"=>20);

	$myPicture->setShadow(TRUE); 
	$myPicture->drawLegend(20,10,array("Style"=>LEGEND_BORDER,"Mode"=>LEGEND_HORIZONTAL)); 

	/* Change Values ImageMap */
	
	$tiempo_values=Array();
	$distancia_values=Array();
	$elevacion_values=Array();
	foreach($dato["tiempo_value"] as $val){
		array_push($tiempo_values,$val);
	}
	foreach($dato["distancia_value"]  as $val){
		array_push($distancia_values,$val);
	}
	foreach($dato["elevacion_value"]  as $val){
		array_push($elevacion_values,$val);
	}
		
	$myPicture->replaceImageMapValues("tiempo", $tiempo_values);
	$myPicture->replaceImageMapValues("distancia", $distancia_values);
	$myPicture->replaceImageMapValues("elevacion", $elevacion_values);
	
	
	$myPicture->replaceImageMapTitle("tiempo", $periodo);
	$myPicture->replaceImageMapTitle("distancia", $periodo);
	$myPicture->replaceImageMapTitle("elevacion", $periodo);
	
	
	
	/* Render the picture (choose the best way) */	
	$myPicture->autoOutput("pictures/example.drawLineChart.png"); 

	return true;
}







/* *********************** */
/* ******  M a i n  ****** */
/* *********************** */


Mostrar();

?>
