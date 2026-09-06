<?php
/** IHLink Datasub platform upgrade bootstrap. Safe/idempotent schema extensions. */
function ih_platform_upgrade(PDO $db): void {
  $cols=$db->query('SHOW COLUMNS FROM service_plans')->fetchAll(PDO::FETCH_COLUMN);
  if(!in_array('premium_price',$cols,true)) $db->exec("ALTER TABLE service_plans ADD premium_price DECIMAL(14,2) NOT NULL DEFAULT 0 AFTER retail_price");
  $db->exec("UPDATE service_plans SET premium_price=ROUND(retail_price*0.98,2) WHERE premium_price=0 AND retail_price>0");
  $uc=$db->query('SHOW COLUMNS FROM users')->fetchAll(PDO::FETCH_COLUMN);
  if(!in_array('transaction_pin_hash',$uc,true)) $db->exec("ALTER TABLE users ADD transaction_pin_hash VARCHAR(255) NULL");
  $db->exec("CREATE TABLE IF NOT EXISTS wallet_ledger(
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,user_id INT UNSIGNED NOT NULL,reference VARCHAR(100) NOT NULL,
    direction ENUM('credit','debit') NOT NULL,amount DECIMAL(14,2) NOT NULL,balance_before DECIMAL(14,2) NOT NULL,
    balance_after DECIMAL(14,2) NOT NULL,entry_type VARCHAR(50) NOT NULL,description VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,UNIQUE KEY uniq_ledger_ref(reference,direction),INDEX(user_id),INDEX(created_at)
  ) ENGINE=InnoDB");
  $db->exec("CREATE TABLE IF NOT EXISTS beneficiaries(
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,user_id INT UNSIGNED NOT NULL,label VARCHAR(80) NULL,recipient VARCHAR(80) NOT NULL,
    service_type VARCHAR(40) NOT NULL,provider VARCHAR(60) NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_beneficiary(user_id,recipient,service_type),INDEX(user_id)
  ) ENGINE=InnoDB");
  $db->exec("CREATE TABLE IF NOT EXISTS notifications(
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,user_id INT UNSIGNED NOT NULL,title VARCHAR(140) NOT NULL,message VARCHAR(500) NOT NULL,
    type VARCHAR(30) NOT NULL DEFAULT 'info',read_at DATETIME NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,INDEX(user_id),INDEX(read_at)
  ) ENGINE=InnoDB");
}
function ih_ledger(PDO $db,int $uid,string $ref,string $direction,float $amount,float $before,float $after,string $type,string $description=''): void {
 $q=$db->prepare('INSERT IGNORE INTO wallet_ledger(user_id,reference,direction,amount,balance_before,balance_after,entry_type,description) VALUES(?,?,?,?,?,?,?,?)');
 $q->execute([$uid,$ref,$direction,$amount,$before,$after,$type,$description]);
}
function ih_notify(PDO $db,int $uid,string $title,string $message,string $type='info'): void {
 $q=$db->prepare('INSERT INTO notifications(user_id,title,message,type) VALUES(?,?,?,?)');$q->execute([$uid,$title,$message,$type]);
}
