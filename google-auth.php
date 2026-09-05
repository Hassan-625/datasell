<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

function ga_db(): PDO {
    static $p=null;
    if ($p instanceof PDO) return $p;
    $p=new PDO(
        'mysql:host='.(getenv('DB_HOST')?:'127.0.0.1').';port='.(getenv('DB_PORT')?:'3306').';dbname='.(getenv('DB_NAME')?:'datasell').';charset=utf8mb4',
        getenv('DB_USER')?:'root',
        getenv('DB_PASS')?:'',
        [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]
    );
    return $p;
}
function ga_e($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function ga_base_url(): string {
    $configured=trim((string)getenv('APP_URL'));
    if ($configured!=='') return rtrim($configured,'/');
    $scheme=((($_SERVER['HTTP_X_FORWARDED_PROTO']??'')==='https')||(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off'))?'https':'http';
    return $scheme.'://'.($_SERVER['HTTP_HOST']??'localhost');
}
function ga_request(string $url, array $options=[]): array {
    $method=$options['method']??'GET';
    $headers=$options['headers']??[];
    $content=$options['content']??null;
    $ctx=stream_context_create(['http'=>[
        'method'=>$method,
        'header'=>implode("\r\n",$headers),
        'content'=>$content,
        'ignore_errors'=>true,
        'timeout'=>20
    ]]);
    $body=@file_get_contents($url,false,$ctx);
    if($body===false) throw new Exception('Unable to contact Google authentication service.');
    $data=json_decode($body,true);
    if(!is_array($data)) throw new Exception('Google returned an invalid authentication response.');
    return $data;
}
function ga_error(string $message,int $status=400): never {
    http_response_code($status);
    echo '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Google Sign In — IHLink Datasub</title><link rel="stylesheet" href="/assets/css/app.css?v=7"></head><body><main style="max-width:560px;margin:70px auto;padding:24px"><div class="card" style="padding:28px"><h2>Google sign-in could not continue</h2><p>'.ga_e($message).'</p><a class="btn btn-primary" href="/?page=login">Back to sign in</a></div></main></body></html>';
    exit;
}

$clientId=trim((string)getenv('GOOGLE_CLIENT_ID'));
$clientSecret=trim((string)getenv('GOOGLE_CLIENT_SECRET'));
$redirectUri=ga_base_url().'/google-auth.php?action=callback';
$action=$_GET['action']??'start';

if($action==='status'){
    header('Content-Type: application/json');
    echo json_encode(['enabled'=>$clientId!==''&&$clientSecret!=='']);
    exit;
}
if($clientId===''||$clientSecret===''){
    ga_error('Google sign-in is installed but the Google OAuth Client ID and Client Secret have not yet been configured on the server.',503);
}

try{
    $p=ga_db();
    $cols=$p->query('SHOW COLUMNS FROM users')->fetchAll(PDO::FETCH_COLUMN);
    if(!in_array('google_sub',$cols,true))$p->exec("ALTER TABLE users ADD google_sub VARCHAR(255) NULL UNIQUE AFTER email");
    if(!in_array('auth_provider',$cols,true))$p->exec("ALTER TABLE users ADD auth_provider VARCHAR(30) NOT NULL DEFAULT 'password' AFTER google_sub");

    if($action==='start'){
        $state=bin2hex(random_bytes(24));
        $_SESSION['google_oauth_state']=$state;
        $_SESSION['google_oauth_started_at']=time();
        $params=[
            'client_id'=>$clientId,
            'redirect_uri'=>$redirectUri,
            'response_type'=>'code',
            'scope'=>'openid email profile',
            'state'=>$state,
            'access_type'=>'online',
            'include_granted_scopes'=>'true',
            'prompt'=>'select_account'
        ];
        header('Location: https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query($params));
        exit;
    }

    if($action==='callback'){
        if(isset($_GET['error'])) ga_error('Google authentication was cancelled or denied.');
        $state=(string)($_GET['state']??'');
        $saved=(string)($_SESSION['google_oauth_state']??'');
        $started=(int)($_SESSION['google_oauth_started_at']??0);
        unset($_SESSION['google_oauth_state'],$_SESSION['google_oauth_started_at']);
        if($state===''||$saved===''||!hash_equals($saved,$state)||$started<time()-600) ga_error('The Google sign-in session expired. Please try again.');
        $code=(string)($_GET['code']??'');
        if($code==='') ga_error('Google did not return an authorization code.');

        $token=ga_request('https://oauth2.googleapis.com/token',[
            'method'=>'POST',
            'headers'=>['Content-Type: application/x-www-form-urlencoded'],
            'content'=>http_build_query([
                'code'=>$code,
                'client_id'=>$clientId,
                'client_secret'=>$clientSecret,
                'redirect_uri'=>$redirectUri,
                'grant_type'=>'authorization_code'
            ])
        ]);
        if(empty($token['access_token'])) ga_error('Google could not complete the token exchange.');

        $profile=ga_request('https://openidconnect.googleapis.com/v1/userinfo',[
            'headers'=>['Authorization: Bearer '.$token['access_token']]
        ]);
        $sub=trim((string)($profile['sub']??''));
        $email=strtolower(trim((string)($profile['email']??'')));
        $name=trim((string)($profile['name']??''));
        $verified=(bool)($profile['email_verified']??false);
        if($sub===''||$email===''||!filter_var($email,FILTER_VALIDATE_EMAIL)||!$verified) ga_error('Google did not provide a verified email address.');
        if($name==='')$name=strstr($email,'@',true)?:'IHLink User';

        $q=$p->prepare('SELECT id,google_sub FROM users WHERE google_sub=? OR LOWER(email)=? LIMIT 1');
        $q->execute([$sub,$email]);
        $user=$q->fetch();
        if($user){
            if(!empty($user['google_sub'])&&!hash_equals((string)$user['google_sub'],$sub)) ga_error('This email is already linked to a different Google account.');
            $p->prepare("UPDATE users SET google_sub=?,auth_provider=CASE WHEN auth_provider='password' THEN 'password+google' ELSE 'google' END WHERE id=?")->execute([$sub,$user['id']]);
            $uid=(int)$user['id'];
        }else{
            $phone='G'.substr(hash('sha256',$sub),0,17);
            $randomPassword=password_hash(bin2hex(random_bytes(32)),PASSWORD_DEFAULT);
            $ins=$p->prepare("INSERT INTO users(fullname,email,google_sub,auth_provider,phone,password_hash) VALUES(?,?,?,'google',?,?)");
            $ins->execute([$name,$email,$sub,$phone,$randomPassword]);
            $uid=(int)$p->lastInsertId();
        }
        session_regenerate_id(true);
        $_SESSION['uid']=$uid;
        header('Location:/?page=dashboard');
        exit;
    }
    ga_error('Unknown Google authentication action.');
}catch(Throwable $e){
    ga_error('Google sign-in could not be completed. Please try again or use your email and password.',500);
}
