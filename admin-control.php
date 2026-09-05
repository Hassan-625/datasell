<?php
// bootstrap_roles.php is auto-prepended in production.
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
function ac_db(){static $p=null;if($p)return $p;$p=new PDO('mysql:host='.(getenv('DB_HOST')?:'127.0.0.1').';port='.(getenv('DB_PORT')?:'3306').';dbname='.(getenv('DB_NAME')?:'datasell').';charset=utf8mb4',getenv('DB_USER')?:'root',getenv('DB_PASS')?:'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);return $p;}
function e($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function n($v){return '₦'.number_format((float)$v,2);}
function ac_csrf(){if(empty($_SESSION['admin_csrf']))$_SESSION['admin_csrf']=bin2hex(random_bytes(24));return $_SESSION['admin_csrf'];}
function ac_check_csrf(){if(!hash_equals($_SESSION['admin_csrf']??'',$_POST['csrf']??''))throw new Exception('Session expired. Refresh and try again.');}
function ac_me(){if(empty($_SESSION['uid']))return null;$q=ac_db()->prepare('SELECT id,fullname,email,wallet_balance,is_admin,admin_role,account_type,customer_tier,business_name FROM users WHERE id=?');$q->execute([$_SESSION['uid']]);return $q->fetch()?:null;}
function tier_label($u){if($u['account_type']==='reseller')return 'Reseller';if($u['account_type']==='api')return 'API User';return $u['customer_tier']==='premium'?'Premium Customer':'Standard Customer';}
$me=ac_me();
if(!$me){header('Location:/?page=login');exit;}
if(!in_array($me['admin_role'],['admin','super_admin'],true)){header('Location:/?page=dashboard');exit;}
$msg='';$err='';
try{
 if($_SERVER['REQUEST_METHOD']==='POST'){
  ac_check_csrf();$action=$_POST['action']??'';
  if($action==='set_tier'){
   $uid=(int)($_POST['user_id']??0);$tier=$_POST['tier']??'standard';
   if(!in_array($tier,['standard','premium','reseller','api'],true))throw new Exception('Invalid tier.');
   if($tier==='reseller'){$type='reseller';$customer='standard';}
   elseif($tier==='api'){$type='api';$customer='standard';}
   elseif($tier==='premium'){$type='customer';$customer='premium';}
   else{$type='customer';$customer='standard';}
   ac_db()->prepare('UPDATE users SET account_type=?,customer_tier=?,tier_updated_at=NOW() WHERE id=?')->execute([$type,$customer,$uid]);
   $msg='User commercial tier updated.';
  }
  if($action==='set_admin_role'){
   if($me['admin_role']!=='super_admin')throw new Exception('Only the Super Admin can change administrative access.');
   $uid=(int)($_POST['user_id']??0);$role=$_POST['admin_role']??'none';
   if(!in_array($role,['none','admin'],true))throw new Exception('Invalid administrative role.');
   if($uid===(int)$me['id'])throw new Exception('The active Super Admin cannot demote this account here.');
   $is=$role==='admin'?1:0;
   ac_db()->prepare('UPDATE users SET admin_role=?,is_admin=? WHERE id=? AND admin_role<>\'super_admin\'')->execute([$role,$is,$uid]);
   $msg='Administrative access updated.';
  }
  if($action==='review_upgrade'){
   $rid=(int)($_POST['request_id']??0);$decision=$_POST['decision']??'';
   if(!in_array($decision,['approved','rejected'],true))throw new Exception('Invalid decision.');
   $p=ac_db();$p->beginTransaction();
   $q=$p->prepare("SELECT * FROM upgrade_requests WHERE id=? AND status='pending' FOR UPDATE");$q->execute([$rid]);$r=$q->fetch();
   if(!$r){$p->rollBack();throw new Exception('Upgrade request is no longer pending.');}
   if($decision==='approved'){
    $tier=$r['requested_tier'];
    if($tier==='reseller'){$type='reseller';$customer='standard';}
    elseif($tier==='api'){$type='api';$customer='standard';}
    elseif($tier==='premium'){$type='customer';$customer='premium';}
    else{$type='customer';$customer='standard';}
    $p->prepare('UPDATE users SET account_type=?,customer_tier=?,business_name=COALESCE(NULLIF(?,\'\'),business_name),tier_updated_at=NOW() WHERE id=?')->execute([$type,$customer,$r['business_name'],$r['user_id']]);
   }
   $p->prepare('UPDATE upgrade_requests SET status=?,reviewed_by=?,review_note=?,reviewed_at=NOW() WHERE id=?')->execute([$decision,$me['id'],trim($_POST['review_note']??''),$rid]);
   $p->commit();$msg='Upgrade request '.$decision.'.';
  }
 }
}catch(Throwable $x){if(ac_db()->inTransaction())ac_db()->rollBack();$err=$x->getMessage();}
$csrf=ac_csrf();
$stats=[
 'users'=>(int)ac_db()->query('SELECT COUNT(*) FROM users')->fetchColumn(),
 'premium'=>(int)ac_db()->query("SELECT COUNT(*) FROM users WHERE account_type='customer' AND customer_tier='premium'")->fetchColumn(),
 'resellers'=>(int)ac_db()->query("SELECT COUNT(*) FROM users WHERE account_type='reseller'")->fetchColumn(),
 'api'=>(int)ac_db()->query("SELECT COUNT(*) FROM users WHERE account_type='api'")->fetchColumn(),
 'pending'=>(int)ac_db()->query("SELECT COUNT(*) FROM upgrade_requests WHERE status='pending'")->fetchColumn()
];
$users=ac_db()->query('SELECT id,fullname,email,wallet_balance,is_admin,admin_role,account_type,customer_tier,business_name,created_at FROM users ORDER BY id DESC LIMIT 250')->fetchAll();
$requests=ac_db()->query("SELECT r.*,u.fullname,u.email FROM upgrade_requests r JOIN users u ON u.id=r.user_id ORDER BY FIELD(r.status,'pending','approved','rejected'),r.id DESC LIMIT 100")->fetchAll();
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>IHLink Datasub — Tier Administration</title><link rel="stylesheet" href="/assets/css/app.css?v=6"><style>
body{background:#f5f7fb}.admin-wrap{max-width:1440px;margin:auto;padding:28px}.admin-head{display:flex;align-items:center;justify-content:space-between;gap:20px;margin-bottom:24px}.admin-head h1{margin:4px 0}.top-links{display:flex;gap:10px;flex-wrap:wrap}.stats{display:grid;grid-template-columns:repeat(5,1fr);gap:14px;margin-bottom:24px}.stat{background:#fff;border:1px solid #e4e9f0;border-radius:16px;padding:18px}.stat span{display:block;color:#6d7b8e;font-size:12px}.stat strong{font-size:28px}.panel{background:#fff;border:1px solid #e4e9f0;border-radius:18px;padding:22px;margin:18px 0;overflow:auto}.panel h2{margin-top:0}.notice{padding:12px 14px;border-radius:12px;margin:12px 0}.ok{background:#eaf8f0;color:#137a4a}.bad{background:#fff0f0;color:#b52d2d}.badge{display:inline-flex;padding:5px 9px;border-radius:999px;background:#eef2f6;font-size:11px;font-weight:800}.badge.super{background:#efe8ff;color:#6941c6}.badge.admin{background:#e8f2ff;color:#1469b8}.badge.premium{background:#fff5d6;color:#8a6510}.table{width:100%;border-collapse:collapse;min-width:980px}.table th,.table td{text-align:left;padding:12px;border-bottom:1px solid #edf0f4;vertical-align:top}.table th{font-size:11px;text-transform:uppercase;color:#758195}.inline{display:flex;gap:7px;align-items:center;flex-wrap:wrap}.inline select,.inline input{height:38px;border:1px solid #dfe5ec;border-radius:9px;padding:0 10px}.btn2{border:0;border-radius:9px;padding:9px 12px;font-weight:800;cursor:pointer;background:#0b6bcb;color:#fff}.btn2.secondary{background:#eef2f6;color:#152033}.btn2.danger{background:#fff0f0;color:#b52d2d}.muted{color:#6d7b8e;font-size:12px}@media(max-width:900px){.stats{grid-template-columns:repeat(2,1fr)}.admin-head{align-items:flex-start;flex-direction:column}}@media(max-width:520px){.stats{grid-template-columns:1fr}.admin-wrap{padding:16px}}
</style></head><body><main class="admin-wrap">
<header class="admin-head"><div><span class="eyebrow">Administration</span><h1>Super Admin & Tier Control</h1><div class="muted">Signed in as <?=e($me['email'])?> · <?=e($me['admin_role']==='super_admin'?'Super Admin':'Admin')?></div></div><div class="top-links"><a class="btn btn-ghost" href="/?page=admin">Main Admin Console</a><a class="btn btn-ghost" href="/?page=dashboard">Customer Dashboard</a><a class="btn btn-primary" href="/upgrade.php">Upgrade Page</a></div></header>
<?php if($msg):?><div class="notice ok"><?=e($msg)?></div><?php endif;?><?php if($err):?><div class="notice bad"><?=e($err)?></div><?php endif;?>
<section class="stats"><div class="stat"><span>Total users</span><strong><?=$stats['users']?></strong></div><div class="stat"><span>Premium</span><strong><?=$stats['premium']?></strong></div><div class="stat"><span>Resellers</span><strong><?=$stats['resellers']?></strong></div><div class="stat"><span>API Users</span><strong><?=$stats['api']?></strong></div><div class="stat"><span>Pending upgrades</span><strong><?=$stats['pending']?></strong></div></section>
<section class="panel"><h2>Upgrade requests</h2><p class="muted">Customers request Premium, Reseller or API access here. Approval changes their commercial tier; it never grants administrative authority.</p><table class="table"><thead><tr><th>User</th><th>Requested</th><th>Business / Reason</th><th>Status</th><th>Review</th></tr></thead><tbody><?php if(!$requests):?><tr><td colspan="5">No upgrade requests.</td></tr><?php endif;?><?php foreach($requests as $r):?><tr><td><b><?=e($r['fullname'])?></b><br><span class="muted"><?=e($r['email'])?><br><?=e($r['created_at'])?></span></td><td><span class="badge <?=e($r['requested_tier']==='premium'?'premium':'')?>"><?=e(ucfirst($r['requested_tier']))?></span></td><td><?=e($r['business_name']?:'—')?><br><span class="muted"><?=e($r['reason']?:'No reason provided')?></span></td><td><?=e(ucfirst($r['status']))?></td><td><?php if($r['status']==='pending'):?><form method="post" class="inline"><input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="action" value="review_upgrade"><input type="hidden" name="request_id" value="<?=$r['id']?>"><input name="review_note" placeholder="Optional note"><button class="btn2" name="decision" value="approved">Approve</button><button class="btn2 danger" name="decision" value="rejected">Reject</button></form><?php else:?><span class="muted"><?=e($r['review_note']?:'Reviewed')?></span><?php endif;?></td></tr><?php endforeach;?></tbody></table></section>
<section class="panel"><h2>User access & commercial tiers</h2><p class="muted"><b>Commercial tier</b> controls pricing/workspace. <b>Administrative role</b> controls backend authority. They are intentionally separate.</p><table class="table"><thead><tr><th>User</th><th>Wallet</th><th>Commercial tier</th><th>Change tier</th><th>Administrative access</th></tr></thead><tbody><?php foreach($users as $u):$tl=tier_label($u);?><tr><td><b><?=e($u['fullname'])?></b><br><span class="muted"><?=e($u['email'])?><?= $u['business_name']?'<br>'.e($u['business_name']):''?></span></td><td><?=n($u['wallet_balance'])?></td><td><span class="badge <?=$tl==='Premium Customer'?'premium':''?>"><?=e($tl)?></span></td><td><form method="post" class="inline"><input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="action" value="set_tier"><input type="hidden" name="user_id" value="<?=$u['id']?>"><select name="tier"><option value="standard" <?=$tl==='Standard Customer'?'selected':''?>>Standard Customer</option><option value="premium" <?=$tl==='Premium Customer'?'selected':''?>>Premium Customer</option><option value="reseller" <?=$tl==='Reseller'?'selected':''?>>Reseller</option><option value="api" <?=$tl==='API User'?'selected':''?>>API User</option></select><button class="btn2">Update</button></form></td><td><?php if($u['admin_role']==='super_admin'):?><span class="badge super">Super Admin</span><?php elseif($me['admin_role']==='super_admin'):?><form method="post" class="inline"><input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="action" value="set_admin_role"><input type="hidden" name="user_id" value="<?=$u['id']?>"><select name="admin_role"><option value="none" <?=$u['admin_role']==='none'?'selected':''?>>No admin</option><option value="admin" <?=$u['admin_role']==='admin'?'selected':''?>>Admin</option></select><button class="btn2 secondary">Save</button></form><?php else:?><span class="badge admin"><?=e($u['admin_role']==='admin'?'Admin':'No admin')?></span><?php endif;?></td></tr><?php endforeach;?></tbody></table></section>
</main></body></html>
