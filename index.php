<!DOCTYPE html>

<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AtoS Blacklist Manager</title>
    <link rel="stylesheet" href="css/estilo.css">

</head>
<body>
    <header>
    </header>
    <section>
        <div id="box">
                <h3>Gerenciamento de Blacklist Dinâmica</h3>
                <form name='web_form' id='web_form' method='post' action='/blacklist/index.php'>
                        <input type='text' name='ip' class="form-control" id="ip" placeholder="Input IP Address here">
                        <button type="submit" name='s1' id='s1' value="submit" class="btn btn-primary btn2">Submit</button>
                </form>
        </div>
        <br></br>
<div id="res">
<?php
	function ip_is_private ($ip) {
    	$pri_addrs = array (
                      	'10.0.0.0|10.255.255.255', // single class A network
                      	'172.16.0.0|172.31.255.255', // 16 contiguous class B network
                      	'192.168.0.0|192.168.255.255', // 256 contiguous class C network
                      	'169.254.0.0|169.254.255.255', // Link-local address also refered to as Automatic Private IP Addressing
                      	'127.0.0.0|127.255.255.255' // localhost
                     	);

    	$long_ip = ip2long ($ip);
   	if ($long_ip != -1) {
        	foreach ($pri_addrs AS $pri_addr) {
			list ($start, $end) = explode('|', $pri_addr);
             		// IF IS PRIVATE
             		if ($long_ip >= ip2long ($start) && $long_ip <= ip2long ($end)) {
                		return "private";
             		}
        	}
    	}
    	return "public";
	}

	function lookupIp ($ip)	{
	$ch = curl_init('http://ipwhois.app/json/'.$ip);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_PROXY, "172.16.162.222");
	curl_setopt($ch, CURLOPT_PROXYPORT, "3128");
	$json = curl_exec($ch);
	curl_close($ch);
	$ipwhois_result = json_decode($json, true);
	return $ipwhois_result['country'];
	}




  session_start();
        if( $_SERVER['REQUEST_METHOD']=='POST' )
        {
                $request = md5( implode( $_POST ) );
                if( isset( $_SESSION['last_request'] ) && $_SESSION['last_request']== $request )
                {
                       	echo('<div id="not_valid">');	
			echo("DENY: Uso da  mesma sessão (Botão Refresh) ou entrada de dados duplicada (Mesmo endereço IP anterior)");
			echo("</div>");
                }
                else
                {
                        $_SESSION['last_request']  = $request;
	if (isset($_POST['s1'])) {
        	$ip = $_POST['ip'];
		$ip_reg = preg_replace("/\./", "\.", $ip);
        	date_default_timezone_set('America/Sao_Paulo');
        	$data = date('Y-m-j');
        	$hora = date('H:i:s');
		$data_python = date('Ymj');
		$hora_python = date('His');
		if (!file_exists("controle_horas.txt")) {
			fopen('controle_horas.txt','w+');
		}
		if (!file_exists("blacklist.txt")) {
			fopen('blacklist.txt','w+');
		}
        	if (filter_var($ip, FILTER_VALIDATE_IP)) {
			$is_private = ip_is_private("$ip");
			if ( $is_private != "private" ) {
				$blacklist=file_get_contents("blacklist.txt");
				$blacklist_dinamica=file_get_contents("/var/www/html/blacklist_dinamica.txt");
				$blacklist_estatica=file_get_contents("/var/www/html/blacklist_estatica.txt");
				$controle_horas=file_get_contents("controle_horas.txt");
				$count_controle = preg_match_all("/.*$ip_reg/i", $controle_horas, $matches);
				$count_estatica = preg_match_all("/$ip_reg/i", $blacklist_estatica, $matches);
				if( $count_estatica == 0 ) {
					if( $count_controle == 0 ) {
						$count_black = preg_match_all("/$ip_reg/i", $blacklist_dinamica, $matches);
						if ( $count_black == 0 ) {
							$ip_lookup = lookupIp($ip);
							file_put_contents('controle_horas.txt', $data." ".$hora." ".$ip."\n", FILE_APPEND);
							file_put_contents('blacklist.txt', $data_python.$hora_python." ".$ip." ".$ip_lookup."\n", FILE_APPEND);
							file_put_contents('blacklist_report.txt', $data." ".$hora." ".$ip."\n", FILE_APPEND);
							echo('<div id="success">');
							echo("IP $ip foi adicionado a blacklist dinâmica com sucesso");
							echo("</div>");
						} else {
							echo('<div id="not_valid">');
							echo("IP $ip não adicionado. Dentro das 48 horas para revogação");
							echo("</div>");
						}
					} else {
						if ( $count_controle >= 3) {
							preg_match_all("/.*$ip_reg/i", $controle_horas, $resultado);
							$arr_controle_horas = array();
							foreach($resultado[0] as $texto){
								$arr = explode(' ', $texto);
								array_push($arr_controle_horas, "$arr[0] $arr[1]" );
							}	
							$arr_end = end($arr_controle_horas);
							$last_data = $arr_end;
							$datatime1 = new DateTime("$last_data");
							$datatime2 = new DateTime("$data $hora");
							$diff = $datatime1->diff($datatime2);
							$diff_horas = $diff->h + ($diff->days * 24);
							if ( $diff_horas <= 48 ) {
								echo('<div id="not_valid">');
								echo("IP $ip não adicionado. Dentro das 48 horas para revogação");
								echo("</div>");
							} else {	
								$count_estatica = preg_match_all("/$ip_reg/i", $blacklist_estatica, $matches);
								if( $count_estatica >= 1 ) {
									echo('<div id="not_valid">');
									echo("O IP $ip já está cadastrado na blacklist estática");
									echo("</div>");
								} else {
									$controle_horas = preg_replace("/.*$ip_reg/i", '', $controle_horas);
									$controle_horas = preg_replace('/^[ \t]*[\r\n]+/m', '', $controle_horas);
									file_put_contents('controle_horas.txt', $controle_horas);
									file_put_contents('blacklist_estatica_report.txt',  $data_python.$hora_python." ".$ip."\n", FILE_APPEND);
									echo('<div id="not_valid">');
									echo("O IP $ip foi adicionado a blacklist estática");
									echo("</div>");
								}
							}
						} else {
							preg_match_all("/.*$ip_reg/i", $controle_horas, $resultado);
							$arr_controle_horas = array();
							foreach($resultado[0] as $texto){
								$arr = explode(' ', $texto);
								array_push($arr_controle_horas, "$arr[0] $arr[1]" );
							}
							$arr_end = end($arr_controle_horas);
							$last_data = $arr_end;
							$datatime1 = new DateTime("$last_data");
							$datatime2 = new DateTime("$data $hora");
							$diff = $datatime1->diff($datatime2);
							$diff_horas = $diff->h + ($diff->days * 24);
							if ( $diff_horas <= 48 ) {
								echo('<div id="not_valid">');
								echo("IP $ip não adicionado. Dentro das 48 horas para revogação");
								echo("</div>");
							} else {
								$ip_lookup = lookupIp($ip);
								file_put_contents('controle_horas.txt', $data." ".$hora." ".$ip."\n", FILE_APPEND);
                                        			file_put_contents('blacklist.txt', $data_python.$hora_python." ".$ip." ".$ip_lookup."\n", FILE_APPEND);
								file_put_contents('blacklist_report.txt', $data." ".$hora." ".$ip."\n", FILE_APPEND);
								echo('<div id="success">');
								echo("IP $ip foi adicionado a blacklist dinâmica com sucesso");
								echo("</div>");
							}
						}
					}
				} else {
					echo('<div id="not_valid">');
					echo("O IP $ip já está cadastrado na blacklist estática");
					echo("</div>");	
				}
			} else {
				echo('<div id="not_valid">');
				echo("$ip é um endereço privativo e não pode ser bloqueado");
				echo("</div>");
			}
		} else {
       			echo('<div id="not_valid">');
       			echo("$ip não é um endereço IP válido");
       			echo("</div>");
		}
	}
}
}
?>
</div>
    </section>
</body>
</html>
