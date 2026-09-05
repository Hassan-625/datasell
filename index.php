<?php
session_start();

const APP_NAME = 'IHLink Datasub';

function db(){
  static $pdo=null;
  if($pdo) return $pdo;
  $h=getenv('DB_HOST')?:'127.0.0.1';
  $pt=getenv('DB_PORT')?:'3306';
  $n=getenv('DB_NAME')?:'datasell';
  $u=getenv('DB_USER')?:'root';
  $pw=getenv('DB_PASS')?:'';
  $pdo=new PDO("mysql:host=$h;port=$pt;dbname=$n;charset=utf8mb4",$u,$pw,[
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC
  ]);
  return $pdo;
}
function esc($v){ return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8'); }
function money($v){ return '₦'.number_format((float)$v,2); }
function ref($p='TX'){ return $p.'-'.date('YmdHis').'-'.strtoupper(bin2hex(random_bytes(3))); }
function live_mode(){ return getenv('PROVIDER_LIVE')==='1'; }
function csrf(){
  if(empty($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(24));
  return $_SESSION['csrf'];
}
function verify_csrf(){
  if(!hash_equals($_SESSION['csrf']??'',$_POST['csrf']??'')) throw new Exception('Your session expired. Refresh and try again.');
}
function me(){
  if(empty($_SESSION['uid'])) return null;
  $s=db()->prepare('SELECT id,fullname,email,phone,wallet_balance,is_admin,created_at FROM users WHERE id=?');
  $s->execute([$_SESSION['uid']]);
  return $s->fetch() ?: null;
}
function initdb(){
  $p=db();
  $p->exec("CREATE TABLE IF NOT EXISTS users(
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    fullname VARCHAR(120) NOT NULL,email VARCHAR(160) NOT NULL UNIQUE,phone VARCHAR(20) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,wallet_balance DECIMAL(14,2) NOT NULL DEFAULT 0,is_admin TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
  ) ENGINE=InnoDB;
  CREATE TABLE IF NOT EXISTS transactions(
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,user_id INT UNSIGNED NOT NULL,type VARCHAR(50) NOT NULL,
    amount DECIMAL(14,2) NOT NULL,status VARCHAR(20) NOT NULL DEFAULT 'pending',reference VARCHAR(80) NOT NULL UNIQUE,
    details TEXT NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,INDEX(user_id),INDEX(status)
  ) ENGINE=InnoDB;
  CREATE TABLE IF NOT EXISTS funding_requests(
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,user_id INT UNSIGNED NOT NULL,amount DECIMAL(14,2) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',reference VARCHAR(80) NOT NULL UNIQUE,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
  ) ENGINE=InnoDB;
  CREATE TABLE IF NOT EXISTS service_plans(
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,category VARCHAR(40) NOT NULL,provider VARCHAR(60) NOT NULL,
    name VARCHAR(120) NOT NULL,code VARCHAR(80) NOT NULL,price DECIMAL(14,2) NOT NULL,
    face_value DECIMAL(14,2) NULL,validity VARCHAR(80) NULL,plan_type VARCHAR(60) NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,sort_order INT NOT NULL DEFAULT 0,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_plan(category,provider,code),INDEX(category),INDEX(provider)
  ) ENGINE=InnoDB;");
  $count=(int)$p->query("SELECT COUNT(*) FROM service_plans")->fetchColumn();
  if($count===0){
    $seed=[
      ['data','MTN','1GB Promo','MTN-1GB-250',250,null,'30 days','Promo/SME'],
      ['data','MTN','500MB','MTN-500MB',180,null,'30 days','SME'],
      ['data','MTN','2GB','MTN-2GB',700,null,'30 days','SME'],
      ['data','MTN','3GB','MTN-3GB',1050,null,'30 days','SME'],
      ['data','MTN','5GB','MTN-5GB',1750,null,'30 days','SME'],
      ['data','Airtel','500MB','AIR-500MB',200,null,'30 days','Corporate'],
      ['data','Airtel','1GB','AIR-1GB',400,null,'30 days','Corporate'],
      ['data','Airtel','2GB','AIR-2GB',800,null,'30 days','Corporate'],
      ['data','Airtel','5GB','AIR-5GB',2000,null,'30 days','Corporate'],
      ['data','Glo','1GB','GLO-1GB',350,null,'30 days','Corporate'],
      ['data','Glo','2GB','GLO-2GB',700,null,'30 days','Corporate'],
      ['data','Glo','5GB','GLO-5GB',1750,null,'30 days','Corporate'],
      ['data','9mobile','1GB','9M-1GB',350,null,'30 days','Corporate'],
      ['data','9mobile','2GB','9M-2GB',700,null,'30 days','Corporate'],
      ['airtime','MTN','₦100 Airtime','MTN-A100',98,100,null,'VTU'],
      ['airtime','MTN','₦500 Airtime','MTN-A500',490,500,null,'VTU'],
      ['airtime','Airtel','₦100 Airtime','AIR-A100',98,100,null,'VTU'],
      ['airtime','Glo','₦100 Airtime','GLO-A100',98,100,null,'VTU'],
      ['airtime','9mobile','₦100 Airtime','9M-A100',98,100,null,'VTU'],
      ['cable','DStv','DStv Yanga','DSTV-YANGA',3500,null,'30 days','Bouquet'],
      ['cable','DStv','DStv Compact','DSTV-COMPACT',12500,null,'30 days','Bouquet'],
      ['cable','GOtv','GOtv Jolli','GOTV-JOLLI',3950,null,'30 days','Bouquet'],
      ['cable','StarTimes','Nova','START-NOVA',1700,null,'30 days','Bouquet'],
      ['exam','WAEC','WAEC Result Checker PIN','WAEC-PIN',4000,null,null,'PIN'],
      ['exam','NECO','NECO Result Checker Token','NECO-PIN',1500,null,null,'PIN'],
      ['exam','NABTEB','NABTEB Result Checker PIN','NABTEB-PIN',1500,null,null,'PIN'],
      ['exam','JAMB','JAMB ePIN / Service','JAMB-PIN',5500,null,null,'PIN'],
      ['electricity','AEDC','AEDC Electricity','AEDC',0,null,null,'Prepaid/Postpaid'],
      ['electricity','EKEDC','EKEDC Electricity','EKEDC',0,null,null,'Prepaid/Postpaid'],
      ['electricity','IBEDC','IBEDC Electricity','IBEDC',0,null,null,'Prepaid/Postpaid'],
      ['electricity','IKEDC','IKEDC Electricity','IKEDC',0,null,null,'Prepaid/Postpaid'],
      ['electricity','JED','Jos Electricity (JED)','JED',0,null,null,'Prepaid/Postpaid'],
      ['electricity','KAEDCO','Kaduna Electric','KAEDCO',0,null,null,'Prepaid/Postpaid'],
      ['electricity','KEDCO','Kano Electric','KEDCO',0,null,null,'Prepaid/Postpaid'],
      ['electricity','PHED','Port Harcourt Electric','PHED',0,null,null,'Prepaid/Postpaid']
    ];
    $s=$p->prepare("INSERT INTO service_plans(category,provider,name,code,price,face_value,validity,plan_type,sort_order) VALUES(?,?,?,?,?,?,?,?,?)");
    foreach($seed as $i=>$r){ $s->execute([$r[0],$r[1],$r[2],$r[3],$r[4],$r[5],$r[6],$r[7],$i]); }
  }
}
try{ initdb(); }catch(Throwable $e){}

$action=$_POST['action']??''; $msg=''; $err='';
try{
  if($action){
    verify_csrf();
    if($action==='register'){
      $name=trim($_POST['fullname']??'');$email=strtolower(trim($_POST['email']??''));$phone=preg_replace('/\D/','',$_POST['phone']??'');$pw=$_POST['password']??'';
      if(strlen($name)<3||!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($phone)<10||strlen($pw)<6) throw new Exception('Please enter valid registration details.');
      $s=db()->prepare('INSERT INTO users(fullname,email,phone,password_hash) VALUES(?,?,?,?)');
      $s->execute([$name,$email,$phone,password_hash($pw,PASSWORD_DEFAULT)]);
      $_SESSION['uid']=db()->lastInsertId(); header('Location:?page=dashboard');exit;
    }
    if($action==='login'){
      $s=db()->prepare('SELECT * FROM users WHERE email=?');$s->execute([strtolower(trim($_POST['email']??''))]);$r=$s->fetch();
      if(!$r||!password_verify($_POST['password']??'',$r['password_hash'])) throw new Exception('Invalid email or password.');
      $_SESSION['uid']=$r['id'];header('Location:?page=dashboard');exit;
    }
    if($action==='logout'){ session_destroy();header('Location:/');exit; }
    if($action==='fund'){
      $u=me();if(!$u)throw new Exception('Please sign in.');
      $a=(float)($_POST['amount']??0);if($a<100)throw new Exception('Minimum funding request is ₦100.');
      db()->prepare('INSERT INTO funding_requests(user_id,amount,reference) VALUES(?,?,?)')->execute([$u['id'],$a,ref('FUND')]);
      $msg='Funding request submitted. Paystack/virtual-account auto funding can be connected next.';
    }
    if($action==='purchase'){
      $u=me();if(!$u)throw new Exception('Please sign in.');
      $planId=(int)($_POST['plan_id']??0);$category=$_POST['category']??'';
      $recipient=trim($_POST['recipient']??'');$custom=(float)($_POST['custom_amount']??0);
      $s=db()->prepare('SELECT * FROM service_plans WHERE id=? AND active=1');$s->execute([$planId]);$plan=$s->fetch();
      if(!$plan||$plan['category']!==$category) throw new Exception('Selected product is unavailable.');
      $amount=(float)$plan['price'];
      if($category==='electricity'){
        $amount=$custom;
        if($amount<100) throw new Exception('Enter a valid electricity amount.');
      }
      if($amount<=0) throw new Exception('Invalid product amount.');
      $details=[
        'provider'=>$plan['provider'],'plan'=>$plan['name'],'plan_code'=>$plan['code'],
        'recipient'=>$recipient,'plan_type'=>$plan['plan_type'],'mode'=>live_mode()?'live':'staging'
      ];
      if(live_mode()){
        if($amount>$u['wallet_balance']) throw new Exception('Insufficient wallet balance.');
        $p=db();$p->beginTransaction();
        $p->prepare('UPDATE users SET wallet_balance=wallet_balance-? WHERE id=? AND wallet_balance>=?')->execute([$amount,$u['id'],$amount]);
        $p->prepare('INSERT INTO transactions(user_id,type,amount,status,reference,details) VALUES(?,?,?,?,?,?)')
          ->execute([$u['id'],$category,$amount,'pending',ref(),json_encode($details)]);
        $p->commit();
        $msg='Order queued for provider processing.';
      }else{
        db()->prepare('INSERT INTO transactions(user_id,type,amount,status,reference,details) VALUES(?,?,?,?,?,?)')
          ->execute([$u['id'],$category,$amount,'pending',ref('DEMO'),json_encode($details)]);
        $msg='Staging order created without debiting your wallet. Connect the provider API to enable real fulfilment.';
      }
    }
    if($action==='approve'&&me()&&me()['is_admin']){
      $id=(int)($_POST['id']??0);$p=db();$p->beginTransaction();
      $s=$p->prepare("SELECT * FROM funding_requests WHERE id=? AND status='pending' FOR UPDATE");$s->execute([$id]);$r=$s->fetch();
      if(!$r) throw new Exception('Funding request not found.');
      $p->prepare("UPDATE funding_requests SET status='approved' WHERE id=?")->execute([$id]);
      $p->prepare('UPDATE users SET wallet_balance=wallet_balance+? WHERE id=?')->execute([$r['amount'],$r['user_id']]);
      $p->prepare('INSERT INTO transactions(user_id,type,amount,status,reference,details) VALUES(?,?,?,?,?,?)')
        ->execute([$r['user_id'],'wallet_funding',$r['amount'],'successful',$r['reference'],json_encode(['source'=>'admin-approved funding'])]);
      $p->commit();$msg='Wallet funded successfully.';
    }
    if($action==='save_plan'&&me()&&me()['is_admin']){
      $id=(int)($_POST['id']??0);$vals=[
        trim($_POST['category']??''),trim($_POST['provider']??''),trim($_POST['name']??''),trim($_POST['code']??''),
        (float)($_POST['price']??0),($_POST['face_value']??'')===''?null:(float)$_POST['face_value'],
        trim($_POST['validity']??''),trim($_POST['plan_type']??''),isset($_POST['active'])?1:0
      ];
      if(!$vals[0]||!$vals[1]||!$vals[2]||!$vals[3]) throw new Exception('Plan fields are incomplete.');
      if($id){
        $q=db()->prepare('UPDATE service_plans SET category=?,provider=?,name=?,code=?,price=?,face_value=?,validity=?,plan_type=?,active=? WHERE id=?');
        $q->execute([...$vals,$id]);
      }else{
        $q=db()->prepare('INSERT INTO service_plans(category,provider,name,code,price,face_value,validity,plan_type,active) VALUES(?,?,?,?,?,?,?,?,?)');
        $q->execute($vals);
      }
      $msg='Service plan saved.';
    }
  }
}catch(Throwable $e){ $err=$e->getMessage(); }

$page=$_GET['page']??'home';$u=me();
$protected=['dashboard','data','airtime','electricity','cable','exam','wallet','transactions','profile','admin'];
if(in_array($page,$protected,true)&&!$u){header('Location:?page=login');exit;}
if($page==='transactions'&&isset($_GET['export'])&&$u){
  header('Content-Type:text/csv');header('Content-Disposition:attachment; filename="ihlink-datasub-statement.csv"');
  $out=fopen('php://output','w');fputcsv($out,['Reference','Type','Amount','Status','Date']);
  $s=db()->prepare('SELECT reference,type,amount,status,created_at FROM transactions WHERE user_id=? ORDER BY id DESC');$s->execute([$u['id']]);
  foreach($s as $r)fputcsv($out,$r);fclose($out);exit;
}
function plans($cat,$provider=null){
  $sql='SELECT * FROM service_plans WHERE category=? AND active=1';$args=[$cat];
  if($provider){$sql.=' AND provider=?';$args[]=$provider;}
  $sql.=' ORDER BY sort_order,id';
  $s=db()->prepare($sql);$s->execute($args);return $s->fetchAll();
}
function navitem($key,$label,$page){$active=$key===$page?' active':'';return '<a class="nav-item'.$active.'" href="?page='.$key.'"><span>'.esc($label).'</span></a>';}
$csrf=csrf();
?>
<!doctype html><html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#07111f">
<title><?=esc(APP_NAME)?> — <?=esc(ucwords(str_replace('-',' ',$page)))?></title>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Manrope:wght@600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/app.css?v=3">
<style>
.plan-toolbar,.provider-tabs,.bill-tabs{display:flex;gap:10px;flex-wrap:wrap;margin:18px 0}.chip{border:1px solid var(--line);background:#fff;border-radius:999px;padding:10px 14px;font-weight:700;cursor:pointer}.chip.active{background:var(--navy);color:#fff;border-color:var(--navy)}
.plan-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}.plan-card{border:1px solid var(--line);background:#fff;border-radius:16px;padding:18px;text-align:left;cursor:pointer;transition:.2s}.plan-card:hover,.plan-card.selected{border-color:var(--primary);box-shadow:0 8px 25px rgba(11,107,203,.12);transform:translateY(-1px)}.plan-card strong{display:block;font-size:18px;margin:7px 0}.plan-card small{color:var(--muted)}.price{font-size:24px;font-weight:800;color:var(--navy);margin-top:12px}.service-shell{display:grid;grid-template-columns:1.2fr .8fr;gap:22px}.checkout{position:sticky;top:24px}.catalog-note{padding:12px 14px;background:#fff7df;border:1px solid #f5df9a;border-radius:12px;color:#7c5d13;margin-bottom:16px}.admin-plan-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.mini-plan{border:1px solid var(--line);border-radius:12px;padding:12px}.kpi-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:14px}.status-pill{display:inline-flex;padding:5px 9px;border-radius:999px;font-size:12px;font-weight:800;background:#eef2f6}.status-pill.successful{background:#eaf8f0;color:#137a4a}.status-pill.pending{background:#fff7df;color:#8d681d}.empty{padding:36px;text-align:center;color:var(--muted)}
@media(max-width:900px){.plan-grid{grid-template-columns:repeat(2,1fr)}.service-shell{grid-template-columns:1fr}.checkout{position:static}.kpi-grid{grid-template-columns:repeat(2,1fr)}}@media(max-width:560px){.plan-grid,.admin-plan-grid{grid-template-columns:1fr}.kpi-grid{grid-template-columns:1fr 1fr}}
</style></head><body>
<?php if($page==='home'): ?>
<header class="site-header"><a class="brand" href="/"><span class="brand-mark">IH</span><span>IHLink Datasub</span></a><nav class="desktop-nav"><a href="#services">Services</a><a href="#features">Features</a><a href="?page=api">API</a></nav><div class="header-actions"><a class="link-btn" href="?page=login">Sign in</a><a class="btn btn-primary" href="?page=register">Get started →</a></div></header>
<main>
<section class="hero-pro"><div class="hero-copy"><span class="eyebrow">VTU • Bills • Wallet • Reseller API</span><h1>Digital services made <span>simple and fast.</span></h1><p>Buy affordable data and airtime, pay electricity and cable bills, get exam PINs, manage your wallet and track every transaction from one secure platform.</p><div class="hero-actions"><a class="btn btn-primary btn-lg" href="?page=register">Create free account →</a><a class="btn btn-ghost btn-lg" href="#services">See services</a></div><div class="trust-row"><span>✓ 4 mobile networks</span><span>✓ Major Nigerian DISCOs</span><span>✓ Reseller-ready</span></div></div>
<div class="hero-product"><div class="product-shell"><div class="product-top"><span class="mini-brand"><span class="brand-mark sm">IH</span> IHLink Datasub</span><span class="secure-pill">● API-ready</span></div><div class="wallet-card premium"><span>Popular offer</span><strong>1GB — ₦250</strong><div class="wallet-meta"><span>MTN Promo</span><span>Admin editable</span></div></div><div class="quick-grid"><?php foreach([['Data','1GB from ₦250'],['Airtime','All networks'],['Bills','Electricity & TV'],['PINs','WAEC • NECO']] as $q):?><div class="quick-item"><b><?=esc($q[0])?></b><small><?=esc($q[1])?></small></div><?php endforeach;?></div></div></div></section>
<section class="logo-strip"><span>MTN</span><span>AIRTEL</span><span>GLO</span><span>9MOBILE</span><span>DStv</span><span>GOtv</span><span>StarTimes</span><span>WAEC</span></section>
<section class="section-pro" id="services"><div class="section-heading"><span class="eyebrow">Everything in one wallet</span><h2>Built like a modern VTU platform.</h2><p>Flexible catalogues today, provider-powered fulfilment when you connect your API.</p></div><div class="service-grid-pro">
<?php foreach([['data','Mobile Data','SME, corporate, gifting and promotional bundles.'],['airtime','Airtime','VTU top-up and discounted fixed denominations.'],['electricity','Electricity','Prepaid and postpaid payments across major DISCOs.'],['cable','Cable TV','DStv, GOtv and StarTimes bouquets.'],['exam','Education PINs','WAEC, NECO, NABTEB and JAMB products.'],['api','Reseller API','API keys, webhooks and transaction status architecture.']] as $s):?>
<a class="service-card-pro" href="<?= $s[0]==='api'?'?page=api':'?page=register' ?>"><h3><?=esc($s[1])?></h3><p><?=esc($s[2])?></p><span class="card-link">Explore →</span></a><?php endforeach;?></div></section>
<section class="why-section" id="features"><div class="why-copy"><span class="eyebrow light">Core platform capabilities</span><h2>What users expect from current VTU apps.</h2><div class="feature-list">
<div><b>01</b><span><strong>Dynamic product catalogue</strong><small>Admin-managed prices, networks, validity and plan types.</small></span></div>
<div><b>02</b><span><strong>Wallet + funding</strong><small>Manual approval now; Paystack and virtual accounts can be connected.</small></span></div>
<div><b>03</b><span><strong>Receipts & statements</strong><small>Searchable transaction history with CSV statement export.</small></span></div>
<div><b>04</b><span><strong>API-ready fulfilment</strong><small>Orders stay in staging until your live VTU API is connected.</small></span></div>
</div></div><div class="why-panel"><div class="metric"><span>Networks</span><strong>4</strong></div><div class="metric"><span>Service groups</span><strong>6+</strong></div><div class="metric"><span>Product pricing</span><strong>Admin controlled</strong></div></div></section>
</main><footer class="footer-pro"><a class="brand" href="/"><span class="brand-mark">IH</span><span>IHLink Datasub</span></a><p>Powered by IHLink.</p><span>© <?=date('Y')?> IHLink Datasub</span></footer>

<?php elseif($page==='login'||$page==='register'): ?>
<div class="auth-layout"><aside class="auth-visual"><a class="brand brand-light" href="/"><span class="brand-mark">IH</span><span>IHLink Datasub</span></a><div><span class="eyebrow light">One account. Every digital service.</span><h1>Data, airtime, bills and more — in one place.</h1><p>A clean VTU experience designed for customers, agents and resellers.</p></div><small>Secure access • Wallet control • Transaction history</small></aside><main class="auth-panel"><div class="auth-card-clean"><span class="auth-kicker"><?=$page==='login'?'Welcome back':'Create account'?></span><h2><?=$page==='login'?'Sign in to IHLink Datasub':'Join IHLink Datasub'?></h2>
<?php if($err):?><div class="notice error"><span>!</span><?=esc($err)?></div><?php endif;?>
<form method="post" class="form-stack"><input type="hidden" name="csrf" value="<?=esc($csrf)?>"><input type="hidden" name="action" value="<?=$page?>">
<?php if($page==='register'):?><div class="field"><label>Full name</label><input name="fullname" required></div><div class="field"><label>Phone number</label><input name="phone" required></div><?php endif;?>
<div class="field"><label>Email address</label><input type="email" name="email" required></div><div class="field"><label>Password</label><input type="password" name="password" minlength="6" required></div><button class="btn btn-primary btn-block btn-lg"><?=$page==='login'?'Sign in':'Create account'?> →</button></form>
<p class="auth-switch"><?=$page==='login'?'New here? <a href="?page=register">Create account</a>':'Already registered? <a href="?page=login">Sign in</a>'?></p></div></main></div>

<?php elseif($page==='api'): ?>
<header class="site-header"><a class="brand" href="/"><span class="brand-mark">IH</span><span>IHLink Datasub</span></a><div class="header-actions"><a class="link-btn" href="/">Home</a><a class="btn btn-primary" href="?page=register">Create account</a></div></header>
<main class="api-page"><section class="api-hero"><span class="eyebrow">Developer platform</span><h1>IHLink Datasub API.</h1><p>Architecture prepared for API-key authentication, webhooks, balance checks, plan discovery, purchase endpoints and transaction-status queries. The live provider adapter will be connected when you supply the network API.</p></section>
<section class="api-layout"><div class="card"><h3>Planned endpoints</h3><pre>GET  /api/v1/plans?network=MTN
POST /api/v1/data/purchase
POST /api/v1/airtime/purchase
POST /api/v1/electricity/purchase
POST /api/v1/cable/purchase
GET  /api/v1/transactions/{reference}
POST /api/v1/webhooks/provider</pre></div><div class="card"><h3>Production safeguards</h3><p>Bearer API keys, hashed credentials, idempotency, webhook signatures, rate limiting and provider reconciliation will be activated with the live adapter.</p></div></section></main>

<?php else: ?>
<div class="app-shell"><aside class="sidebar-pro"><a class="brand brand-light sidebar-brand" href="?page=dashboard"><span class="brand-mark">IH</span><span>IHLink Datasub</span></a><nav class="sidebar-nav">
<?=navitem('dashboard','Overview',$page)?><?=navitem('data','Buy Data',$page)?><?=navitem('airtime','Airtime',$page)?><?=navitem('electricity','Electricity',$page)?><?=navitem('cable','Cable TV',$page)?><?=navitem('exam','Exam PINs',$page)?><?=navitem('wallet','Wallet',$page)?><?=navitem('transactions','Transactions',$page)?><?=navitem('profile','Profile',$page)?>
<?php if($u&&$u['is_admin']):?><?=navitem('admin','Admin',$page)?><?php endif;?></nav><form method="post" class="sidebar-logout"><input type="hidden" name="csrf" value="<?=esc($csrf)?>"><input type="hidden" name="action" value="logout"><button class="btn btn-ghost-light">Sign out</button></form></aside>
<main class="dashboard-main"><header class="dash-header"><div><span class="eyebrow">IHLink Datasub</span><h1><?=esc(['dashboard'=>'Overview','data'=>'Buy Data','airtime'=>'Buy Airtime','electricity'=>'Electricity Bills','cable'=>'Cable TV','exam'=>'Education PINs','wallet'=>'Wallet','transactions'=>'Transactions','profile'=>'Profile','admin'=>'Admin Console'][$page]??ucwords($page))?></h1></div><div class="user-chip"><span><?=esc(strtoupper(substr($u['fullname'],0,1)))?></span><div><b><?=esc($u['fullname'])?></b><small><?=esc($u['is_admin']?'Administrator':'Customer')?></small></div></div></header>
<?php if($msg):?><div class="notice success"><span>✓</span><?=esc($msg)?></div><?php endif;?><?php if($err):?><div class="notice error"><span>!</span><?=esc($err)?></div><?php endif;?>

<?php if($page==='dashboard'):
$recent=db()->prepare('SELECT * FROM transactions WHERE user_id=? ORDER BY id DESC LIMIT 5');$recent->execute([$u['id']]);?>
<section class="kpi-grid"><div class="stat-card"><span>Wallet balance</span><strong><?=money($u['wallet_balance'])?></strong><small>Available balance</small></div><div class="stat-card"><span>Provider mode</span><strong><?=live_mode()?'LIVE':'STAGING'?></strong><small><?=live_mode()?'Real fulfilment enabled':'No wallet debit for test orders'?></small></div><div class="stat-card"><span>Account</span><strong><?=$u['is_admin']?'Admin':'Customer'?></strong><small>Active</small></div><div class="stat-card"><span>Statements</span><strong>CSV</strong><small>Download anytime</small></div></section>
<section class="section-block"><div class="section-title"><div><h2>Quick actions</h2><p>Choose a service to get started.</p></div></div><div class="service-grid-pro compact">
<?php foreach([['data','Buy Data','Choose network and plan'],['airtime','Buy Airtime','Top up any number'],['electricity','Electricity','Pay meter bills'],['cable','Cable TV','Renew subscriptions'],['exam','Exam PINs','WAEC, NECO & more'],['wallet','Fund Wallet','Add money to wallet']] as $q):?><a class="service-card-pro" href="?page=<?=$q[0]?>"><h3><?=$q[1]?></h3><p><?=$q[2]?></p><span class="card-link">Continue →</span></a><?php endforeach;?></div></section>
<section class="section-block"><div class="section-title"><h2>Recent transactions</h2><a href="?page=transactions">View all →</a></div><div class="table-card"><table class="data-table"><thead><tr><th>Service</th><th>Amount</th><th>Status</th><th>Date</th></tr></thead><tbody><?php $has=false;foreach($recent as $t):$has=true;?><tr><td><?=esc(ucwords(str_replace('_',' ',$t['type'])))?></td><td><?=money($t['amount'])?></td><td><span class="status-pill <?=esc($t['status'])?>"><?=esc(ucfirst($t['status']))?></span></td><td><?=esc($t['created_at'])?></td></tr><?php endforeach;if(!$has):?><tr><td colspan="4" class="empty">No transactions yet.</td></tr><?php endif;?></tbody></table></div></section>

<?php elseif(in_array($page,['data','airtime','electricity','cable','exam'],true)):
$all=plans($page);$providers=array_values(array_unique(array_column($all,'provider')));?>
<div class="catalog-note">Prices below are your IHLink Datasub catalogue. The ₦250 1GB offer and every other product can be changed from Admin. Live availability/price should later sync with your provider API.</div>
<div class="service-shell"><section><div class="provider-tabs"><?php foreach($providers as $i=>$pr):?><button type="button" class="chip <?=$i===0?'active':''?>" data-provider="<?=esc($pr)?>"><?=esc($pr)?></button><?php endforeach;?></div><div class="plan-grid" id="planGrid">
<?php foreach($all as $pl):?><button type="button" class="plan-card" data-plan-id="<?=$pl['id']?>" data-provider="<?=esc($pl['provider'])?>" data-price="<?=esc($pl['price'])?>" data-name="<?=esc($pl['name'])?>">
<small><?=esc($pl['provider'])?><?= $pl['plan_type']?' • '.esc($pl['plan_type']):''?></small><strong><?=esc($pl['name'])?></strong><?php if($pl['validity']):?><small><?=esc($pl['validity'])?></small><?php endif;?><div class="price"><?= $page==='electricity'?'Enter amount':money($pl['price'])?></div></button><?php endforeach;?></div></section>
<aside class="card checkout"><h3>Complete purchase</h3><p id="selectedPlanText">Select a product to continue.</p><form method="post" class="form-stack" id="purchaseForm"><input type="hidden" name="csrf" value="<?=esc($csrf)?>"><input type="hidden" name="action" value="purchase"><input type="hidden" name="category" value="<?=esc($page)?>"><input type="hidden" name="plan_id" id="planId" required>
<div class="field"><label><?= $page==='electricity'?'Meter number':($page==='cable'?'Smartcard / IUC':($page==='exam'?'Customer phone / identifier':'Phone number'))?></label><input name="recipient" required></div>
<?php if($page==='electricity'):?><div class="field"><label>Amount</label><input type="number" name="custom_amount" min="100" step="50" required></div><?php endif;?>
<button class="btn btn-primary btn-block" type="submit">Continue purchase →</button></form><small><?=live_mode()?'Live provider mode is enabled.':'Staging mode: this test order will not debit your wallet.'?></small></aside></div>

<?php elseif($page==='wallet'):?>
<section class="service-shell"><div><div class="wallet-card premium large"><span>Available balance</span><strong><?=money($u['wallet_balance'])?></strong><div class="wallet-meta"><span>IHLink Datasub Wallet</span><span>Active</span></div></div><div class="card" style="margin-top:18px"><h3>Funding methods roadmap</h3><p>Manual approval is active now. Next integrations can include Paystack checkout, bank transfer/virtual account auto-credit, funding webhooks and reconciliation.</p></div></div><div class="card"><h3>Fund wallet</h3><form method="post" class="form-stack"><input type="hidden" name="csrf" value="<?=esc($csrf)?>"><input type="hidden" name="action" value="fund"><div class="field"><label>Amount</label><input type="number" name="amount" min="100" required></div><button class="btn btn-primary btn-block">Submit funding request</button></form></div></section>

<?php elseif($page==='transactions'):
$s=db()->prepare('SELECT * FROM transactions WHERE user_id=? ORDER BY id DESC LIMIT 200');$s->execute([$u['id']]);?>
<div class="section-title"><div><p>Track service orders, wallet funding and statuses.</p></div><a class="btn btn-ghost" href="?page=transactions&export=1">Download CSV statement</a></div><div class="table-card"><input class="table-search" data-table-search placeholder="Search transactions..."><table class="data-table" data-search-table><thead><tr><th>Reference</th><th>Service</th><th>Amount</th><th>Status</th><th>Date</th></tr></thead><tbody><?php $has=false;foreach($s as $t):$has=true;?><tr><td><?=esc($t['reference'])?></td><td><?=esc(ucwords(str_replace('_',' ',$t['type'])))?></td><td><?=money($t['amount'])?></td><td><span class="status-pill <?=esc($t['status'])?>"><?=esc(ucfirst($t['status']))?></span></td><td><?=esc($t['created_at'])?></td></tr><?php endforeach;if(!$has):?><tr><td colspan="5" class="empty">No transactions yet.</td></tr><?php endif;?></tbody></table></div>

<?php elseif($page==='profile'):?>
<div class="card form-card"><span class="eyebrow">Account details</span><h2><?=esc($u['fullname'])?></h2><div class="profile-list"><div><span>Email</span><b><?=esc($u['email'])?></b></div><div><span>Phone</span><b><?=esc($u['phone'])?></b></div><div><span>Member since</span><b><?=esc($u['created_at'])?></b></div><div><span>Role</span><b><?=$u['is_admin']?'Administrator':'Customer'?></b></div></div></div>

<?php elseif($page==='admin'&&$u['is_admin']):
$stats=['users'=>(int)db()->query('SELECT COUNT(*) FROM users')->fetchColumn(),'plans'=>(int)db()->query('SELECT COUNT(*) FROM service_plans WHERE active=1')->fetchColumn(),'tx'=>(int)db()->query('SELECT COUNT(*) FROM transactions')->fetchColumn(),'pending'=>(int)db()->query("SELECT COUNT(*) FROM funding_requests WHERE status='pending'")->fetchColumn()];
$fund=db()->query("SELECT f.*,u.email FROM funding_requests f JOIN users u ON u.id=f.user_id ORDER BY f.id DESC LIMIT 30");
$catalog=db()->query("SELECT * FROM service_plans ORDER BY category,provider,sort_order,id LIMIT 200");?>
<section class="kpi-grid"><div class="stat-card"><span>Users</span><strong><?=$stats['users']?></strong></div><div class="stat-card"><span>Active products</span><strong><?=$stats['plans']?></strong></div><div class="stat-card"><span>Transactions</span><strong><?=$stats['tx']?></strong></div><div class="stat-card"><span>Pending funding</span><strong><?=$stats['pending']?></strong></div></section>
<section class="section-block"><div class="section-title"><div><h2>Product catalogue</h2><p>Change prices and availability without editing code.</p></div></div><div class="card"><form method="post" class="admin-plan-grid"><input type="hidden" name="csrf" value="<?=esc($csrf)?>"><input type="hidden" name="action" value="save_plan"><div class="field"><label>Category</label><select name="category"><option>data</option><option>airtime</option><option>electricity</option><option>cable</option><option>exam</option></select></div><div class="field"><label>Provider</label><input name="provider" required></div><div class="field"><label>Product name</label><input name="name" required></div><div class="field"><label>Provider/API code</label><input name="code" required></div><div class="field"><label>Selling price</label><input type="number" name="price" step=".01" min="0"></div><div class="field"><label>Face value (optional)</label><input type="number" name="face_value" step=".01"></div><div class="field"><label>Validity</label><input name="validity"></div><div class="field"><label>Plan type</label><input name="plan_type" placeholder="SME, Corporate, VTU..."></div><label><input type="checkbox" name="active" checked> Active</label><button class="btn btn-primary">Add product</button></form></div><div class="admin-plan-grid" style="margin-top:14px"><?php foreach($catalog as $p):?><div class="mini-plan"><small><?=esc(strtoupper($p['category']))?> • <?=esc($p['provider'])?></small><b><?=esc($p['name'])?></b><div><?=money($p['price'])?> • <?=esc($p['active']?'Active':'Disabled')?></div></div><?php endforeach;?></div></section>
<section class="section-block"><div class="section-title"><h2>Funding approvals</h2></div><div class="table-card"><table class="data-table"><thead><tr><th>User</th><th>Amount</th><th>Status</th><th>Action</th></tr></thead><tbody><?php foreach($fund as $r):?><tr><td><?=esc($r['email'])?></td><td><?=money($r['amount'])?></td><td><span class="status-pill <?=esc($r['status'])?>"><?=esc($r['status'])?></span></td><td><?php if($r['status']==='pending'):?><form method="post"><input type="hidden" name="csrf" value="<?=esc($csrf)?>"><input type="hidden" name="action" value="approve"><input type="hidden" name="id" value="<?=$r['id']?>"><button class="btn btn-primary">Approve</button></form><?php endif;?></td></tr><?php endforeach;?></tbody></table></div></section>
<?php endif;?>

</main></div>
<nav class="mobile-bottom"><a href="?page=dashboard">Home</a><a href="?page=data">Data</a><a href="?page=airtime">Airtime</a><a href="?page=wallet">Wallet</a><a href="?page=profile">Profile</a></nav>
<script>
document.querySelectorAll('.provider-tabs').forEach(group=>{const chips=[...group.querySelectorAll('.chip')];const cards=[...document.querySelectorAll('.plan-card')];const filter=provider=>{cards.forEach(c=>c.style.display=c.dataset.provider===provider?'block':'none')};if(chips[0])filter(chips[0].dataset.provider);chips.forEach(ch=>ch.addEventListener('click',()=>{chips.forEach(x=>x.classList.remove('active'));ch.classList.add('active');filter(ch.dataset.provider)}))});
document.querySelectorAll('.plan-card').forEach(card=>card.addEventListener('click',()=>{document.querySelectorAll('.plan-card').forEach(x=>x.classList.remove('selected'));card.classList.add('selected');document.getElementById('planId').value=card.dataset.planId;document.getElementById('selectedPlanText').textContent=card.dataset.name+(card.dataset.price>0?' — ₦'+Number(card.dataset.price).toLocaleString():'')}));
document.querySelector('[data-table-search]')?.addEventListener('input',e=>{const q=e.target.value.toLowerCase();document.querySelectorAll('[data-search-table] tbody tr').forEach(r=>r.style.display=r.textContent.toLowerCase().includes(q)?'':'none')});
</script>
<?php endif;?>
<script src="/assets/js/app.js?v=3"></script></body></html>