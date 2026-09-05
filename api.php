<?php
header('Content-Type: application/json; charset=utf-8');
function out($data,$code=200){http_response_code($code);echo json_encode($data,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);exit;}
function db(){static $p=null;if($p)return $p;$p=new PDO('mysql:host='.(getenv('DB_HOST')?:'127.0.0.1').';port='.(getenv('DB_PORT')?:'3306').';dbname='.(getenv('DB_NAME')?:'datasell').';charset=utf8mb4',getenv('DB_USER')?:'root',getenv('DB_PASS')?:'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);return $p;}
function bearer(){$h=$_SERVER['HTTP_AUTHORIZATION']??'';if(preg_match('/Bearer\s+(.+)/i',$h,$m))return trim($m[1]);return '';}
function live_mode(){return getenv('PROVIDER_LIVE')==='1';}
function ref($p='API'){return $p.'-'.date('YmdHis').'-'.strtoupper(bin2hex(random_bytes(3)));}
try{
$key=bearer();if(!$key)out(['success'=>false,'message'=>'Missing Bearer API key.'],401);
$prefix=substr($key,0,16);$s=db()->prepare("SELECT c.*,u.id user_id,u.fullname,u.email,u.wallet_balance,u.account_type FROM api_credentials c JOIN users u ON u.id=c.user_id WHERE c.key_prefix=? AND c.status='active' LIMIT 1");$s->execute([$prefix]);$cred=$s->fetch();if(!$cred||!password_verify($key,$cred['key_hash']))out(['success'=>false,'message'=>'Invalid API key.'],401);if(!in_array($cred['account_type'],['api','reseller'],true))out(['success'=>false,'message'=>'This account is not enabled for API access.'],403);db()->prepare('UPDATE api_credentials SET last_used_at=NOW() WHERE id=?')->execute([$cred['id']]);
$action=$_GET['action']??'';
if($action==='balance')out(['success'=>true,'data'=>['balance'=>(float)$cred['wallet_balance'],'currency'=>'NGN','pricing_tier'=>'api','mode'=>live_mode()?'live':'staging']]);
if($action==='plans'){$category=trim($_GET['category']??'data');$provider=trim($_GET['provider']??'');$sql='SELECT id,category,provider,name,code,api_price AS price,face_value,validity,plan_type FROM service_plans WHERE active=1 AND category=?';$args=[$category];if($provider!==''){$sql.=' AND provider=?';$args[]=$provider;}$sql.=' ORDER BY sort_order,id';$q=db()->prepare($sql);$q->execute($args);out(['success'=>true,'pricing_tier'=>'api','data'=>$q->fetchAll()]);}
if($action==='purchase'){
 if($_SERVER['REQUEST_METHOD']!=='POST')out(['success'=>false,'message'=>'Use POST for purchases.'],405);
 $payload=json_decode(file_get_contents('php://input'),true)?:$_POST;$planId=(int)($payload['plan_id']??0);$recipient=trim($payload['recipient']??'');$custom=(float)($payload['amount']??0);
 $q=db()->prepare('SELECT * FROM service_plans WHERE id=? AND active=1');$q->execute([$planId]);$plan=$q->fetch();if(!$plan)out(['success'=>false,'message'=>'Plan not found.'],404);
 $amount=$plan['category']==='electricity'?$custom:(float)$plan['api_price'];if($amount<=0||$recipient==='')out(['success'=>false,'message'=>'Valid recipient and amount are required.'],422);
 $reference=ref();$details=json_encode(['source'=>'api','provider'=>$plan['provider'],'plan'=>$plan['name'],'plan_code'=>$plan['code'],'recipient'=>$recipient,'pricing_tier'=>'api','cost_price'=>(float)$plan['cost_price'],'selling_price'=>$amount,'mode'=>live_mode()?'live':'staging']);
 if(!live_mode()){db()->prepare('INSERT INTO transactions(user_id,type,amount,status,reference,details) VALUES(?,?,?,?,?,?)')->execute([$cred['user_id'],$plan['category'],$amount,'pending',$reference,$details]);out(['success'=>true,'message'=>'Staging order created. Wallet not debited.','data'=>['reference'=>$reference,'status'=>'pending','amount'=>$amount,'pricing_tier'=>'api','mode'=>'staging']],202);}
 $p=db();$p->beginTransaction();$u=$p->prepare('SELECT wallet_balance FROM users WHERE id=? FOR UPDATE');$u->execute([$cred['user_id']]);$balance=(float)$u->fetchColumn();if($amount>$balance){$p->rollBack();out(['success'=>false,'message'=>'Insufficient wallet balance.'],402);}$p->prepare('UPDATE users SET wallet_balance=wallet_balance-? WHERE id=?')->execute([$amount,$cred['user_id']]);$p->prepare('INSERT INTO transactions(user_id,type,amount,status,reference,details) VALUES(?,?,?,?,?,?)')->execute([$cred['user_id'],$plan['category'],$amount,'pending',$reference,$details]);$p->commit();out(['success'=>true,'message'=>'Order accepted for provider processing.','data'=>['reference'=>$reference,'status'=>'pending','amount'=>$amount,'pricing_tier'=>'api','mode'=>'live']],202);
}
out(['success'=>false,'message'=>'Unknown action. Use balance, plans or purchase.'],404);
}catch(Throwable $e){out(['success'=>false,'message'=>'Server error.'],500);}