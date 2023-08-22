<?php

include("config.php");
include("func.php");

// ******************************************************************************************************************************

$state = validar($_GET["state"]);
$code  = validar($_GET["code"]);

$type     = validar($_GET["type"]);
$wtype    = validar($_GET["wtype"]);
$location = validar($_GET["location"]);
$gear_id  = validar($_GET["gear_id"]);
$hr       = validar($_GET["hr"]);

$desde = validar($_GET["d"]);
$hasta = validar($_GET["h"]);

$buscar = validar($_GET["buscar"]);
$logout = validar($_GET["logout"]);

$userid = validar($_GET["userid"]);
$share  = validar($_GET["share"]);

if($logout){
	session_destroy();
}

if($state == "auth"){
	
	$cred  = array(
		'client_id' => STRAVA_CLIENT_ID,
		'client_secret' => STRAVA_CLIENT_SECRET,
		'code' => $code,
		'grant_type' => "authorization_code"
	);
	
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, "https://www.strava.com/oauth/token" );
	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $cred);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE );
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE );
	$output = curl_exec($ch);	 
	curl_close($ch);

	$array = json_decode($output,true);
	
	$_SESSION["access_token"] = $array["access_token"];
	$_SESSION["athlete"] = $array["athlete"]["id"];
	
	echo "<html>";
	echo "<link rel='stylesheet' href='style3.css'><body onLoad='Iniciar(); '>";
	echo "<script type='text/javascript' src='func.js'></script> \n";
	echo "Procesando nuevos tracks...<br>";
	echo "<div class='procesador' id='procesador'></div>";
	echo "</body></html>";
	
	$sql = "delete from busq where athlete='".$array["athlete"]["id"]."' ;";
	$Resp= mysqli_query($mysqli_link,$sql);
	
	exit;
	
		
}


if( $share ){

    $athlete = $share;
    $access_token = "1";

}else{

    $access_token = $_SESSION["access_token"];
    $athlete = $_SESSION["athlete"];

    if($userid){
        $athlete = $userid;
        $_SESSION["athlete"] = $athlete;
        $access_token = "1";
        $_SESSION["access_token"] = $access_token;
    }

}


if($buscar == "2"){	
	traer_tracks();
	traer_gear();
	exit;
}


// ******************************************************************************************************************************
?><html>

	<header>
		<script src="pChart/imagemap.js" type="text/javascript"></script>
		
		<link rel="stylesheet" type="text/css" href="css/bootstrap.min.css">
		<link rel="stylesheet" type="text/css" href="datatables/jquery.dataTables.min.css" />
		<link rel='stylesheet' type="text/css" href='style3.css'>
	
		<script type="text/javascript" src="datatables/jquery-1.12.3.js"></script>
		<script type="text/javascript" src="datatables/jquery.dataTables.min.js"></script>
		<script type="text/javascript" src="js/bootstrap.min.js"></script>

		<script type="text/javascript">

			function openNav() {
			    $("#mysidenav").css("width","450px");
                $("#mysidenav").css("overflow-y","scroll");
			}

			function closeNav() {
			    $("#mysidenav").css("width","30");
                $("#mysidenav").css("overflow-y","hidden");
			}
		
			function toggleNav() {
				if($('#mysidenav').css('width') == '450px'){
					closeNav();
				}else{
					openNav();
				}
			}
			
			$(document).ready(function() {

				$('#tracks').DataTable({
					"pageLength": 50,
					"order": [[ 0, "desc" ]]
				});
	
				$('#tabletype').DataTable({
					"pageLength": 50,
					"searching": false,
					"bPaginate": false,
				});
	
				$('#tablewtype').DataTable({
					"pageLength": 50,
					"searching": false,
					"bPaginate": false,
				});
	
				$('#tablelocation').DataTable({
					"pageLength": 50,
					"searching": false,
					"bPaginate": false,
				});
	
				$('#tablegear').DataTable({
					"pageLength": 50,
					"searching": false,
					"bPaginate": false,
				});
	
				$('#tablehr').DataTable({
					"pageLength": 50,
					"searching": false,
					"bPaginate": false,
				});
				
				$(".paginate_button").css("color","#999");
	
			});
		</script>
	</header>

	<body>
	
	<?php if(!$access_token) { ?>

		<div class="container">
			<div class="text-center"><h1> </h1>
				<form action="https://www.strava.com/oauth/authorize" method="get" >
					<input type="hidden" name="client_id" value="7268">
					<input type="hidden" name="response_type" value="code">
					<input type="hidden" name="redirect_uri" value="https://fedped.com/strava/index.php">
					<input type="hidden" name="scope" value="activity:read">
					<input type="hidden" name="state" value="auth">
					<input type="hidden" name="approval_prompt" value="force">
					<input type="image" src="LogInWithStrava.png">
				</form>
			</div>
		</div>

	<?php } else {
		
		
	$sql = "Select min(DATE_FORMAT( start_date_local ,'%Y-%m-%d')) as desde,
	    		   max(DATE_FORMAT( start_date_local ,'%Y-%m-%d')) as hasta
	         from tracks where athlete='$athlete' ";

	
	$Resp= mysqli_query($mysqli_link,$sql);
	
	
	$row = mysqli_fetch_Array($Resp);
	if(!$desde) {
		$desde = $row["desde"];
	}else{
		$desdefiltro = true;
	}
	if(!$hasta) {
		$hasta = $row["hasta"];
	}else{
		$hastafiltro = true;
	}

	if($desde and $hasta){
		$fechas = " and start_date_local >= '$desde 00:00:00' ";
		$fechas.= " and start_date_local <= '$hasta 23:59:59' ";
	}else{
		$fechas = "";
	}
	
	$where = '';
	if($type)		$where.= " and type='$type' ";
	if($wtype)      $where.= " and workout_type='$wtype'";  
	if($location)	$where.= " and concat(location_state,', ',location_city)='$location' ";
	if($gear_id)	$where.= " and gear_id='$gear_id' ";
	if($hr){
					list($min,$max) = explode("-",$hr,2);
					$where.= " and average_heartrate >= $min and average_heartrate <= $max ";
	}
	
   switch($wtype){
						case "0": $wtypename = "default run"; break;
						case "1": $wtypename = "race"; break;
						case "2": $wtypename = "long run"; break;
						case "3": $wtypename = "run workout"; break;
						case "10": $wtypename = "default ride"; break;
						case "11": $wtypename = "bike race"; break;
						case "12": $wtypename = "bike workout"; break;
					}
		
	$filteredby = "Filtered by:  ";
					if( $desdefiltro or $hastafiltro)  $filteredby.= " [<a href=\"?d=$desde&h=$hasta\">$desde - $hasta</a>] ";
					if( $type )          $filteredby.= " [<a href=\"?type=$type&$fechas_\">$type</a>] ";
					if( $wtype )         $filteredby.= " [<a href=\"?wtype=$wtype&$fechas_\">$wtypename</a>] ";
					if( $location  )     $filteredby.= " [<a href=\"?location=$location&$fechas_\">$location</a>] ";	
					if( $gear_id  )      $filteredby.= " [<a href=\"?gear_id=$gear_id&$fechas_\">".$gear[$gear_id]."</a>] ";
					if( $hr  )           $filteredby.= " [<a href=\"?hr=$hr&$fechas_\">".$hr."</a>] ";
					if( $type or $wtype or $location or $gear_id or $desdefiltro or $hastafiltro) $filteredby.= " [<a href='?$fechas_'>Clean Filter</a>]";
                    $filteredby.= " [<a href='".$_SERVER['REQUEST_URI']."&share=".$athlete."'>Share to a friend</a> ] ";
					$filteredby.= "<br/>";	
		
	?>
	
	
	
    <nav id="mysidenav" class="sidenav">
    <span class='glyphicon glyphicon-menu-hamburger' style='font-size:30px;cursor:pointer;height: 30px;' onclick='toggleNav();'></span>
    <div class="filters">	
		
      
      
<?php			
			
   echo $filteredby."<br>";

	// ##############################  TYPE
	
	$sql = "Select type as tit,
	               sum(distance) as dist,
				   count(*) as cant,
				   sum(total_elevation_gain) as acum
	        from tracks
	        where athlete='$athlete' $where $fechas
	              GROUP BY type
	              ";
				  		  
	$Resp= mysqli_query($mysqli_link,$sql);
	
	echo "\n<div class='columna'><table id='tabletype' class='compact'>\n";
	echo "<thead><tr><th class='col1'>Type</th>";
	echo "<th class='col2'>Count</th>";
	echo "<th class='col3'>Kms</th>";
	echo "<th class='col4'>Mts</th></tr></thead><tdata>\n";
	while ( $row = mysqli_fetch_array($Resp)) {
		echo "<tr><td><a href='?type=".$row["tit"]."&wtype=".$wtype."&location=".$location."&gear_id=".$gear_id.
		                      "&hr=".$rh.
		                      "&d=$desde&h=$hasta'>";
		echo ucwords($row["tit"])."</a></td>";
		echo "<td>".$row["cant"]."</td><td>".intval($row["dist"]/1000)."</td>";
		echo "<td>".intval($row["acum"])."</td></tr>\n";
	}
	echo "</tdata></table></div>\n";	

		// ##############################  W TYPE
	
	$sql = "Select CASE workout_type
	                 When 0 Then 'default run'
					 When 1 Then 'race'
					 When 2 Then 'long run'
					 When 3 Then 'run workout'
					 When 10 Then 'default ride'
					 When 11 Then 'bike race'
					 When 12 Then 'bike workout'
	               END as tit,
				   workout_type as wtype,
	               sum(distance) as dist,
				   count(*) as cant,
				   sum(total_elevation_gain) as acum
	        from tracks
	        where athlete='$athlete' $where $fechas and workout_type > 0 
	              GROUP BY workout_type
	              ";
				  		  
	$Resp= mysqli_query($mysqli_link,$sql);
	
	echo "\n<div class='columna'><table id='tablewtype' class='compact'>\n";
	echo "<thead><tr><th class='col1'>Workout type</th>";
	echo "<th class='col2'>Count</th>";
	echo "<th class='col3'>Kms</th>";
	echo "<th class='col4'>Mts</th></tr></thead><tdata>\n";
	while ( $row = mysqli_fetch_array($Resp)) {
		echo "<tr><td><a href='?type=".$type."&wtype=".$row["wtype"]."&location=".$location."&gear_id=".$gear_id.
		                      "&hr=".$rh.
		                      "&d=$desde&h=$hasta'>";
		echo ucwords($row["tit"])."</a></td>";
		echo "<td>".$row["cant"]."</td><td>".intval($row["dist"]/1000)."</td>";
		echo "<td>".intval($row["acum"])."</td></tr>\n";
	}
	echo "</tdata></table></div>\n";	

	
	
	// ##############################  LOCATION
	
	
		$sql = "Select concat( location_state,', ',location_city ) as location,
	               sum(distance) as dist,
				   count(*) as cant,
				   sum(total_elevation_gain) as acum
	        from tracks
	        where athlete='$athlete' and
			      (location_state != '' or location_city != '')
				  $where $fechas
	              GROUP BY location
	              ";
				  		  
	$Resp= mysqli_query($mysqli_link,$sql);
	
	echo "\n<div class='columna'><table id='tablelocation' class='compact'>\n";
	echo "<thead><tr><th >Location</th>";
	echo "<th >Count</th>";
	echo "<th >Kms</th>";
	echo "<th >Mts</th></tr></thead><tdata>\n";
	while ( $row = mysqli_fetch_array($Resp)) {
		echo "<tr><td><a href='?location=".$row["location"]."&type=".$type."&wtype=".$wtype."&gear_id=".$gear_id.
		                      "&hr=".$hr.
		                      "&d=$desde&h=$hasta'>";
		echo ucwords($row["location"])."</a></td>";
		echo "<td>".$row["cant"]."</td><td>".intval($row["dist"]/1000)."</td>";
		echo "<td>".intval($row["acum"])."</td></tr>\n";
	}
	echo "</tdata></table></div>\n";	
	
// ##############################  GEAR
	
	
		$sql = "Select gear_id,
		           (select name from gears where id=gear_id ) as gear,
	               sum(distance) as dist,
				   count(*) as cant,
				   sum(total_elevation_gain) as acum
	        from tracks 
	        where athlete = '$athlete' and
			      gear_id != '' $where $fechas
	              GROUP BY gear
	              ";
				  		  
	$Resp= mysqli_query($mysqli_link,$sql);
	
	echo "\n<div class='columna'><table id='tablegear' class='compact'>\n";
	echo "<thead><tr><th >Gear</th>";
	echo "<th >Count</th>";
	echo "<th >Kms</th>";
	echo "<th >Mts</th></tr></thead><tdata>\n";
	while ( $row = mysqli_fetch_array($Resp)) {
		echo "<tr><td><a href='?gear_id=".$row["gear_id"]."&type=".$type."&wtype=".$wtype."&location=".$location.
		                      "&hr=".$hr.
		                      "&d=$desde&h=$hasta'>";
		echo ucwords($row["gear"])."</a></td>";
		echo "<td>".$row["cant"]."</td><td>".intval($row["dist"]/1000)."</td>";
		echo "<td>".intval($row["acum"])."</td></tr>\n";
		
		$gear[$row["gear_id"]]=$row["gear"];
	}
	echo "</tdata></table></div>\n";		
	
	
	// ##############################  HR
	
	
	$sql = "select
	
		case 
			when average_heartrate between 80 and 100  then '080-100'
			when average_heartrate between 100 and 140 then '101-140'
			when average_heartrate between 140 and 170 then '141-170'
			when average_heartrate between 170 and 190 then '171-190'
			when average_heartrate between 170 and 190 then '190-210'
		end as hr,
		           sum(distance) as dist,
				   count(*) as cant,
				   sum(total_elevation_gain) as acum
	        from tracks 
	        where athlete = '$athlete' and
			      average_heartrate > 0 $where $fechas
	              GROUP BY hr
				  order by hr
	
	";
	
				  		  
	$Resp= mysqli_query($mysqli_link,$sql);
	
	echo "\n<div class='columna'><table id='tablehr' class='compact'>\n";
	echo "<thead><tr><th >HR</th>";
	echo "<th >Count</th>";
	echo "<th >Kms</th>";
	echo "<th >Mts</th></tr></thead><tdata>\n";
	while ( $row = mysqli_fetch_array($Resp)) {
		echo "<tr><td><a href='?gear_id=".$gear_id."&type=".$type."&wtype=".$wtype."&location=".$location.
		                      "&hr=".$row["hr"]. 
		                      "&d=$desde&h=$hasta'>";
		echo $row["hr"] ."</a></td>";
		echo "<td>".$row["cant"]."</td><td>".intval($row["dist"]/1000)."</td>";
		echo "<td>".intval($row["acum"])."</td></tr>\n";
		
	}
	echo "</tdata></table></div>\n";		
	
	
	// ##############################			
	
	
	
?>		
		</div> <!-- filters -->
		</nav> <!-- mysidenav -->
		

		<div class="container tracks">
			<div class="row">
			
				<div class="col-xs-8">
			
				<?php
	
		
      
               echo $filteredby;
      
					
	
	
					$sql = "Select sum(distance) as suma,
			               sum(total_elevation_gain) as acu,
			               sum(moving_time) as segundos
			               from tracks
								where athlete='$athlete' $fechas $where";
					
					
						
					$Resp= mysqli_query($mysqli_link,$sql);
					$row = mysqli_fetch_Array($Resp);
					$kms = intval($row["suma"] / 1000);
					$hor = intval($row["segundos"] / 3600);
					$acu = intval($row["acu"]);
						
					$sql = "Select *, IF(kudos_count > 0 , kudos_count, '') as kudos,
			                  IF(average_heartrate > 0, average_heartrate , '') as hr
							from tracks
				            where athlete='$athlete' $where $fechas
							order by start_date_local desc";
						
						
					$Resp= mysqli_query($mysqli_link,$sql);
					if(!$Resp) echo "Error sql: ".$sql;
	
					$num = mysqli_num_rows($Resp);

	
					$fechas_ = "d=$desde&h=$hasta";
					echo "[$num Tracks: $kms Kms / $hor Horas / $acu metros]";
					
					?>
					
					        </div>
					        <div class='col-xs-4 text-right'>
								<img src="api_logo_cptblWith_strava_horiz_light.png" width="240">
								
							</div>
					
					    </div>
			
					<?php
					
					echo "<div class='trackslist'>";
					
					echo "<img src='graf.php?type=$type&wtype=$wtype&d=$desde&h=$hasta&a=$athlete&location=$location&gear_id=$gear_id&hr=$hr&share=$share' id='Grafico' >";
					echo "<script>addImage('Grafico','pictureMap','graf.php?type=$type&wtype=$wtype&d=$desde&h=$hasta&a=$athlete&location=$location&gear_id=$gear_id&hr=$hr&ImageMap=get&share=$share')</script>";
		
					echo "<br><br><table class='tracklist compact' id='tracks'><thead><tr><th >Date</th>". 
				         "<th>Name (click to view on strava)</th>".
						 "<th>kudos</th>".
						 "<th>HR</th>".
						 "<th>cad</th>".
						 "<th>Distance - Time - Gain</th></tr></thead><tbody>\n";
			
					// Tracks
                    $semana_ = false;			
					while ( $row = mysqli_fetch_array($Resp)) {

                        $semana_ = $semana;
                        $semana = date("W", strtotime($row["start_date_local"]));
                        

                        if($semana_ <> $semana){
                            echo "<tr>";
                            echo "<td>Week ". number_format($semana +1) ."</td>";
                            echo "<td colspan=5><b> ".number_format($total_distancia / 1000,2)." km - ".
                                                    number_format($total_time / 3600,2)." hs - ". 
                                                    number_format($total_elevation ,2)." m</b></td>";
                            echo "</tr>";

                            $total_distancia = 0;
                            $total_elevation = 0;
                            $total_time = 0;
                        }

						echo "<tr><td>".$row["start_date_local"]."</td>";
						echo "<td>";	
						echo "<a href='https://www.strava.com/activities/".$row["id"]."' target='_blank'>".$row["name"]."</a></td>";
						echo "<td>".$row["kudos"]."</td>\n";
						echo "<td>".$row["hr"]."</td>\n";
						echo "<td>". ceil( $row["average_cadence"] * 2 )."</td>\n";
				
						echo "<td>". number_format($row["distance"] / 1000,2) . " km - " . number_format($row["moving_time"]/3600,2) . " hs - " . $row["total_elevation_gain"] .  " m </td>";
						echo "</tr>\n";

                        $total_distancia += $row["distance"]; 
                        $total_time += $row["moving_time"];
                        $total_elevation += $row["total_elevation_gain"];


					}

                    
                    echo "<tr>";
                    echo "<td>Week ". number_format($semana) ."</td>";
                    echo "<td colspan=5><b>".number_format($total_distancia / 1000,2)." km - ".
                                            number_format($total_time / 3600,2)." hs - ". 
                                            number_format($total_elevation ,2)." m</b></td>";
                    echo "</tr>";


					echo "</tbody></table>";
				echo "</div>";
			?>
			
			
		</div>
	
	
	<?php } ?>
	
	</body>

</html>


