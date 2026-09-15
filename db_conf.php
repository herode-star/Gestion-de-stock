<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$DB_host = getenv('DB_HOST') ?: 'localhost';
$DB_user = getenv('DB_USER') ?: 'root';
$DB_pass = getenv('DB_PASSWORD') ?: '';
$DB_name = getenv('DB_NAME') ?: 'Otechnologie';


try {

$DB_con = new PDO(
    "mysql:host={$DB_host};dbname={$DB_name};charset=utf8mb4",
    $DB_user,
    $DB_pass,
    array(
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    )
);
} catch(PDOException $e) {
    http_response_code(500);
    exit('Connexion a la base de donnees impossible.');
}
if (isset($_SESSION['user_session'])) {

	$umail=$_SESSION['user_session'];
}
 else{
 	$umail="";
 }

$user = null;
$userpers = null;
if ($umail !== '') {
    $reponsee = $DB_con->prepare('SELECT user_id, personnalite FROM users WHERE user_email = :email LIMIT 1');
    $reponsee->execute(array(':email' => $umail));
    $donnes = $reponsee->fetch();
    if ($donnes) {
        $user = $donnes['user_id'];
        $userpers = $donnes['personnalite'];
    }
}





/********* nbre de commande par produit*******/
$reponse = $DB_con->query("SELECT pro_id,count(numcom) as star FROM commande group by(pro_id)");
	while ($donnees = $reponse->fetch()){
		 $nbstar=$donnees['star'];
		 $nbpro=$donnees['pro_id'];
		 $DB_con->exec("UPDATE produit SET star='$nbstar' where id='$nbpro'");


	}

/******* devise *************/
$res=$DB_con->query("SELECT * FROM formweb");
$rows = $res->fetch() ?: array();

                 $devise="TND";
				  if(isset($_GET['d'])){
                  $devise=$_GET['d'];
                  $devise = strtoupper($devise);
                  }
                  if($devise=="TND"){
					  $dev="DT";
					  $pridev=1;
                	}elseif($devise=="USD"){
                      $dev='<i class="fa fa-usd"></i>';
					  $pridev=2.43847;
					}elseif($devise=="EUR"){
					  $dev='<i class="fa fa-eur "></i>';
					  $pridev=2.573;
				    }











				    /******************** fin date produit *****************/
/*$reponse = $DB_con->query("SELECT * FROM produit");
$datenew=date("Y-m-d")." ".date("H:i:s");
	while ($donnees = $reponse->fetch())
	  {
	   $npro=$donnees['id'];
	   if($donnees['datefinsolde']<$datenew and $donnees['datefinsolde']!=""  and $donnees['prixsolde']!=0){
         $prix=$donnees['prixsolde'];
	   	 $DB_con->exec("UPDATE produit SET prix='$prix',prixsolde='0',datefinsolde='',pourcentage='0' where id=$npro");
	   }

	}*/

?>
