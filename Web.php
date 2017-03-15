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
					case 'login':
						$email = $actionn['email'];
						$pwd = $actionn['password'];
						$data = array('email' => $email,'password' => $pwd);
						if($this->Api_model->getuser($email) == true){
							$veryfi = $this->Api_model->email_verify($email);
							$arr = $this->Api_model->login($data);
							if($arr != 0){
								if($veryfi['is_email_verified'] == 0){
									$code = 301;
									$error = "Your email not verified!";
								}else{
									$responseArr['userinfo'] = $arr;
									$response = ($responseArr);
									$code = 200;
									$message = 'Login successful';
								}
							}else{
								$code = 300;
								$error = "Username and password doesn't match!";
							}
						}else{
							$error = 'User not found!';
							$code = 102;
						}
						break;	
					case 'signup':
						$random = substr(md5(uniqid(mt_rand(), true)), 0, 32);
						$data['random'] = $random;
						$data = array( 
							'email' => $actionn['email'], 
							'password' => md5($actionn['password']),
							'registration_date' => date('y-m-d h:i:s')
						);
						if($this->Api_model->getuser($actionn['email'])==true){
							$error = 'User Already Exists';
							$code = 300;
						}else{
							$uid = $this->Api_model->register($data);
							$this->Api_model->updatetokan($random, $uid);
							$data['user_name'] = $this->Api_model->getusername($uid);
							$data['password'] = $actionn['password'];
							$data['link'] = base_url() . "web/confirm?hash=".$random."&is_verify=f&u=".$uid;
							$to = $actionn['email'];
							$subject = 'Registration Successfully Done.';
							$headers = "From: carwashmi@carwashmi.com\r\n"; 
							$headers .= "Reply-To: noreply@carwahsmi.com\r\n";
							$headers .= "MIME-Version: 1.0\r\n";
							$headers .= "Content-Type: text/html; charset=ISO-8859-1\r\n";
							ob_start();
								$this->load->view('success_email_template', $data);
							$message = ob_get_clean();
							$m = mail($to, $subject, $message, $headers);
							if($m){
								$response = ($uid);		
								$code = 200;
								$message = 'User registred successfully';
							}else{
								$error = 'We are unable to send Email!';		
								$code = 301;
							}							
						}
						break;
					case 'forgotpass':
						$email = $actionn['email'];
						if($this->Api_model->getuser($email)==true){
							$hash = substr(md5(uniqid(mt_rand(), true)), 0, 8); 
							$arr = $this->Api_model->forgotpassStatus(1,$email);
							$this->Api_model->update_forgot_firstlogin_status($email);
							$data['hash'] = $hash;
							$this->Api_model->updateTempPass($data['hash'],$email);
							$to = $email;
							$subject = 'Set up a new password for Carwashmi';

							$headers = "From: carwashmi@carwashme.com\r\n"; 
							$headers .= "Reply-To: noreply@carwashmi.com\r\n";
							$headers .= "MIME-Version: 1.0\r\n";
							$headers .= "Content-Type: text/html; charset=ISO-8859-1\r\n";
							ob_start();
								$this->load->view('reset_email_template', $data);
							$message = ob_get_clean();
							$m = mail($to, $subject, $message, $headers);
							if($m){
								$response = ($arr);
								$code = 200;
								$message = 'Email has been sent with temporary password. You can change it after login.';
							}else{
								$code = 101;
								$error = 'Please try again';
							}
						}else{
								$error = 'Email address not found';
								$code = 102;
						}
						break;
					case 'changepass':
						$new_password = md5($actionn['new_password']);
						$email = $actionn['email'];
						$oldpassword = $this->Api_model->checkOldpassword($email);
						if($new_password == $oldpassword){
							$code = 300;
							$error = 'New password must be diffrent from old password!';
						}else{
							
							if($this->Api_model->update_password($new_password,$email)){
								$this->Api_model->forgotpassStatus(0,$email);
								$this->Api_model->update_firstlogin_status($email);
								$code = 200;
								$message = 'Password changed successfully';
							}else{
								$code = 300;
								$error = 'Please try again'; 
							}
						}
						break;
					case 'getprofile':
						$udata = array();
						$arr = $this->Api_model->getprofile($actionn['user_id']);
						if(count($arr)>0){
							if($arr->user_type == 1){
								$udata['user_id'] = intval($arr->user_id);
								$udata['first_name'] = $arr->first_name;
								$udata['last_name'] = $arr->last_name;
								$udata['email'] = $arr->email;
								$udata['mobile_number'] = $arr->mobile_number;
								$udata['location'] = $arr->location;
								$udata['user_type'] = $arr->user_type;
								$udata['latitude'] = $arr->latitude;
								$udata['longitude'] = $arr->longitude;
								$udata['car_make'] = $arr->car_make;
								$udata['car_model'] = $arr->car_model;
								$udata['car_year'] = $arr->car_year;
								$udata['rider_type']=$arr->rider_type;
								$udata['liscence_plate'] = $arr->liscence_plate;
								$udata['washer_type'] = (isset($arr->washer_type)) ? $arr->washer_type : 0;
								$udata['is_elite_service_car'] = (isset($arr->is_elite_service_car)) ? $arr->is_elite_service_car : false;
								$udata['is_standard_service_car'] = (isset($arr->is_standard_service_car)) ? $arr->is_standard_service_car : false;
								$udata['is_premium_service_car'] = (isset($arr->is_premium_service_car)) ? $arr->is_premium_service_car : false;
								$udata['is_elite_service_bike'] = (isset($arr->is_elite_service_bike)) ? $arr->is_elite_service_bike : false;
								$udata['is_standard_service_bike'] = (isset($arr->is_standard_service_bike)) ? $arr->is_standard_service_bike : false;
								$udata['is_premium_service_bike'] = (isset($arr->is_premium_service_bike)) ? $arr->is_premium_service_bike : false;
								
								$response = ($udata);		
								$code = 200;
								$message = '';
							}else{
								$udata['user_id'] = $arr->user_id;
								$udata['first_name'] = $arr->first_name;
								$udata['last_name'] = $arr->last_name;
								$udata['email'] = $arr->email;
								$udata['mobile_number'] = $arr->mobile_number;
								$udata['location'] = $arr->location;
								$udata['user_type'] = $arr->user_type;
								$udata['latitude'] = $arr->latitude;
								$udata['longitude'] = $arr->longitude;
								$udata['car_make'] = $arr->car_make;
								$udata['car_model'] = $arr->car_model;
								$udata['car_year'] = $arr->car_year;
								$udata['liscence_plate'] = $arr->liscence_plate;
								$response = ($udata);		
								$code = 200;
								$message = '';
							}
						}else{
							$code = 300;
							$error = 'Record Not found!'; 
						}
						break;
					case 'updateprofile':
						$udata = array();
						$washer = array();
						$udata['first_name'] = $actionn['first_name'];
						$udata['last_name'] = $actionn['last_name'];
						$udata['mobile_number'] = $actionn['mobile_number'];
						$udata['location'] = $actionn['location'];
						$udata['latitude'] = $actionn['latitude'];
						$udata['longitude'] = $actionn['longitude'];
						$udata['rider_type'] = $actionn['rider_type'];
						$udata['car_make'] = $actionn['car_make'];
						$udata['car_model'] = $actionn['car_model'];
						$udata['car_year'] = $actionn['car_year'];
						$udata['liscence_plate'] = $actionn['liscence_plate'];
						if($this->Api_model->getuserbyid($actionn['user_id'])){
							$arr1 = $this->Api_model->updateuser($udata,$actionn['user_id']);
							if($arr1){
								$washer['washer_type'] = $actionn['washer_type'];
								$washer['is_elite_service_car'] = $actionn['is_elite_service_car'];
								$washer['is_standard_service_car'] = $actionn['is_standard_service_car'];
								$washer['is_premium_service_car'] = $actionn['is_premium_service_car'];
								$washer['is_elite_service_bike'] = $actionn['is_elite_service_bike'];
								$washer['is_standard_service_bike'] = $actionn['is_standard_service_bike'];
								$washer['is_premium_service_bike'] = $actionn['is_premium_service_bike'];
								$arr2 = $this->Api_model->updatewasher($washer,$actionn['user_id']);
							}
							$arr = array_merge(array('user' => $arr1),array('washer' => $arr2));
							$response = ($arr);	
							$code = 200;
							$message = 'Profile Updated successfully';								
						}else{
							$error = 'Error in update. Email not found!';		
							$code = 300;
						}
						break;
					case 'profileimage':
						$postData = array();
						$path = FCPATH ."assets/images/users/profile/".$actionn['user_id']."_profile.png";
						$data = $actionn['img'];
						$postData['user_id'] = $actionn['user_id'];
						$postData['img_path'] = base_url()."assets/images/users/profile/".$actionn['user_id']."_profile.png";
						$img = str_replace('data:image/png;base64,', '', $postData['img_path']);
						$img = str_replace(' ', '+', $img);
						$data = base64_decode($img);
						$result = file_put_contents($path, $data);
						if($result == true){
							$arr = $this->Api_model->update_profile_img($postData);
							if($arr == 1){
								$response = (array('insert'=>$path));	
								$code = 200;
								$message = 'Profile Image added successfully';								
							}else{
								$response = (array('update'=>$path));	
								$code = 200;
								$message = 'Profile Image Updated successfully';
							}
						}else{
							$error = 'Profile image not updated!';		
							$code = 300;
						}
						break;
					case 'getprofileimg':
						$arr = $this->Api_model->checkprofileimage($actionn['user_id']);
						$img = ($arr != false)?$arr:false;
						$response = ($img);	
						$code = 200;
						$message = 'success';								
						break;
					case 'savelatlong': 
						$data = array( 
							'email' => $actionn['email'], 
							'latitude' => $actionn['latitude'], 
							'longitude' => $actionn['longitude'],
						);
						
						if($this->Api_model->getuser($actionn['email'])==true){
							$arr = $this->Api_model->update_user_location($data,$actionn['email']);
							$response = ($arr);		
							$code = 200;
							$message = 'Location Saved Successfully';							
						}else{
							$error = 'Email Not Exists!';		
							$code = 300;
						}
						break;
					case 'getwashers':
						$washers = [];
						$lat = $actionn['latitude'];
						$long = $actionn['longitude'];
						$arr = $this->Api_model->getAllwashers();
						if($arr > 0){
							for($count=0;$count<count($arr);$count++){
								if($arr[$count]->rider_type == 0){
									$bikers_distense = $this->Api_model->distance($lat, $long, $arr[$count]->latitude, $arr[$count]->longitude);
									if($bikers_distense <= 5){
										$washers['biker'] = $arr[$count];
									}
								}else{
									$d = $this->Api_model->distance($lat, $long, $arr[$count]->latitude, $arr[$count]->longitude);
									if($d <= 10){
										$washers['driver'] = $arr[$count];
									}
								}
							}
							if($washers > 0){
								$response = ($washers);		
								$code = 200;
								$message = 'success';
							}else{
								$code = 301;
								$error = 'No service provider found!';
							}
						}else{
							$code = 300;
							$error = 'No record found!';
						}
						break;
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
					case "request":
						$washers = [];
						$lat = $actionn['latitude'];
						$long = $actionn['longitude'];
						$requestData = array(
							'user_id' => $actionn['user_id'], 
							'vehicle_type' => $actionn['vehicle_type'],
							'washer_type' => $actionn['washer_type'],
							'service_type' => $actionn['service_type']
						);
						
						$requestId = $this->Api_model->request($requestData);
						if($requestId){
							$wids = array();
							$arr = $this->Api_model->getAllwashers();
							if($arr > 0){
								for($count=0;$count<count($arr);$count++){
									$washers['count'] = $count;
									$wids[] = $arr[$count]->user_id;
									if($arr[$count]->washer_type == 0){
										$bikers_distense = $this->Api_model->distance($lat, $long, $arr[$count]->latitude, $arr[$count]->longitude);
										if($bikers_distense <= 5){
											$washers['biker'] = $arr[$count];
										}
									}else{
										$d = $this->Api_model->distance($lat, $long, $arr[$count]->latitude, $arr[$count]->longitude);
										if($d <= 10){
											$washers['driver'] = $arr[$count];
										}
									}
								}
								if(count($wids) > 0){
									for($w=0;$w<count($wids);$w++){
										$responseData = array(
											'client_request_id' => $requestId, 
											'user_id' => $wids[$w]
										);
										$responseId = $this->Api_model->add_response($responseData);
									}
								}
								if($washers > 0){
									$response = ($washers);		
									$code = 200;
									$message = 'success';
								}else{
									$code = 301;
									$error = 'No service provider found!';
								}
							}
						}
						break;
					case "response":
						$arr = $this->Api_model->getresponse();
						$requestCount = count($arr);
						if($arr > 0){
							$response = array('count' => $requestCount);		
							$code = 200;
							$message = 'success';
						}else{
							$code = 300;
							$error = 'No response found!';
						}
						break;
					case "totalwash":
						$ar = array();
						$requests = $this->Api_model->getwashcount($actionn['user_id']);
						if($requests != false){
							foreach($requests as $r){
								if($r->washer_type == 0){
									$ar['bikes'] = count($requests->washer_type);
								}else{
									$ar['cars'] = count($requests->washer_type);
								}
							}
							
							$response = array('total wash' => $ar);		
							$code = 200;
							$message = 'success';
						}else{
							$code = 300;
							$error = 'No data found!';
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
	
	function confirm(){
		$data['getparam'] = $_REQUEST;
		$this->load->view('confirm',$data);
	}
}	