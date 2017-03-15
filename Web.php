<?php 
if ( ! defined('BASEPATH')) exit('No direct script access allowed');
class Web extends CI_Controller
{
    function __construct() 
	{
		parent::__construct();
		$this->load->model('Common_model');
		$this->load->model('Api_model');
		$this->load->library("braintree_lib");
	}
	
	public function index()
	{   
		header( 'Access-Control-Allow-Headers: Authorization, Content-Type' );
		header('content-type: application/json; charset=utf-8');
		header("Accept: application/json");
		header("access-control-allow-origin: *");
		error_reporting(E_ALL);
		$responseArr = array();
		$response = null;
		$message = null;
		$code = null;

		$myFile = FCPATH ."pwd.txt";
		$lines = file($myFile);//file in to an array
		$auth_variables = explode("|", $lines[0]);
		$actionn = $_POST;
		
		$data = array("username" => $auth_variables[0], "password" => $auth_variables[1]);
		if(@$actionn['username'] != "" && @$actionn['pwd'] != "") {
			$error = '';
			$username = $actionn['username'];
			$password = $actionn['pwd'];
			// echo "<pre>";
			// print_r($actionn);
			if($username == $data['username'] && $password == $data['password']){
				file_put_contents(FCPATH . 'array.txt', var_export($actionn, TRUE));
				switch($actionn['action']){					
					
					case 'payment':
						$price = $actionn['amount'];
						$card_number=str_replace("+","",$actionn['card_number']);  
						$card_name=$actionn['card_name'];
						$expiry_month=$actionn['expiry_month'];
						$expiry_year=$actionn['expiry_year'];
						$cvv=$actionn['cvv'];
						$expirationDate=$expiry_month.'/'.$expiry_year;

						// Braintree_Configuration::environment('production');
						Braintree_Configuration::environment('sandbox');
						Braintree_Configuration::merchantId('wd349dtjw63mbzm5');
						Braintree_Configuration::publicKey('fqt336fb4fcjprkh');
						Braintree_Configuration::privateKey('2442c7b60f4642dcdb0bd3c14ed4fd49'); 

						$result = Braintree_Transaction::sale(array(
							'amount' => $price,
							'creditCard' => array(
								'number' => $card_number,
								'cardholderName' => $card_name,
								'expirationDate' => $expirationDate,
								'cvv' => $cvv
							)
						));
						$response = ($result);		
						$code = 200;
						$message = 'success';
						if ($result->success) 
						{
							//print_r("success!: " . $result->transaction->id);
							if($result->transaction->id)
							{
								$braintreeCode=$result->transaction->id;
								// updateUserOrder($braintreeCode,$session_user_id);
								$response = ($result);		
								$code = 200;
								$message = 'success'; 
							}
						}
						else if ($result->transaction) 
						{
							$response = ($washers);		
							$code = 200;
							$message = 'success';
						} 
						else 
						{
							$code = 301;
							$error = 'Payment not done!';
						}
						break;
					
										
					case 'addpaymentmethod':
						if(isset($actionn['uid'])){
							$cardnumber = isset($actionn['cardnumber']) ? $actionn['cardnumber'] : "";
							$type = $this->Api_model->validatecard($cardnumber);
							switch($type){
								case "visa" :
									$paymenttype = "visa";
									break;
								case "mastercard" :
									$paymenttype = "mastercard";
									break;
								case "amex" :
									$paymenttype = "amex";
									break;
								case "discover" :
									$paymenttype = "discover";
									break;
								case "unknown" :
									$paymenttype = "unknown";
									break;
							}
							
							if($paymenttype == "unknown"){
								$code = 300;
								$error = 'Cardnumber not valid!';
							}else{
								
								$month = isset($actionn['xpirymonth']) ? $actionn['xpirymonth'] : "";
								$year = isset($actionn['year']) ? $actionn['year'] : "";
								$respo = $this->Api_model->is_valid_expiration ( $month, $year );
								if($respo == -2){
									$code = 301;
									$error = 'Card expiry not valid!';
									exit;
								}
								
								$cvv = isset($actionn['cvv']) ? $actionn['cvv'] : "";
								$xpiry = $month.'/'.$year;
								$country = isset($actionn['country']) ? $actionn['country'] : "";
							
								$sql1 = "INSERT INTO payment_settigns(uid,payment_type,card_number,cvv,expiry,country) VALUES('".$actionn['uid']."','".$paymenttype."','".$cardnumber."','".$cvv."','".$xpiry."','".$country."')";
								$rw2 = $conn->query($sql1);
								if($rw2){
									$response = array('paymentmethod' => true);		
									$code = 200;
									$message = 'success';
								}else{
									$code = 300;
									$error = 'Card details not valid!';
								}
							}
						}
						$response = ($arr);		
						$code = 200;
						$message = 'success';
						break;
				}
				$arr = array('success'=> $message, 'code'=> $code, 'response'=> $response, 'error'=> $error);
				$response = json_encode($arr);
				echo $response;				
				die;
			}else{
				$message = "Access denied To Use Api!";
				$arr = array('error'=> array('errorcode' => 404, 'reason' => $message) );
				$response = json_encode($arr);
				echo $response;
			}
		}else{
			$message = "Access denied please check credentials used";
			$arr = array('error'=> array('errorcode' => 401, 'reason' => $message) );
			$response = json_encode($arr);
			echo $response;
		}
	}

}	
