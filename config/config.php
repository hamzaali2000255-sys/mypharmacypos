<?php
declare(strict_types=1);

const DB_HOST = '127.0.0.1';
const DB_NAME = 'mypharmacypos';
const DB_USER = 'root';
const DB_PASS = '';
const APP_NAME = 'ALDAWAPHARMACY';

if (session_status() !== PHP_SESSION_ACTIVE) session_start();

function db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
    return $pdo;
}
function e(?string $value): string { return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8'); }
function money(float|int|string $value): string { return number_format((float)$value, 2); }
function current_user(): ?array { if(empty($_SESSION['user_id'])) return null; return ['id'=>(int)$_SESSION['user_id'],'username'=>(string)($_SESSION['username']??''),'role'=>(string)($_SESSION['role']??'cashier')]; }
function require_login(): void { if(!current_user()){header('Location: login.php');exit;} }
function require_role(array $roles): void { $u=current_user(); if(!$u){header('Location: login.php');exit;} if(!in_array($u['role'],$roles,true)){http_response_code(403); echo '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Access denied</title><link rel="stylesheet" href="style.css"></head><body><main><section class="panel" style="max-width:620px;margin:80px auto;text-align:center"><div style="font-size:48px">🔒</div><h1>Access Denied</h1><p>Your <b>'.e($u['role']).'</b> account does not have permission to open this section.</p><a class="btn" href="index.php">Return to Dashboard</a></section></main></body></html>'; exit;} }
function audit_log(string $action, string $details=''): void { try { $u=current_user(); db()->prepare('INSERT INTO audit_log(user_id,action,details) VALUES(?,?,?)')->execute([$u['id']??null,$action,$details!==''?$details:null]); } catch(Throwable $e) {} }

$publicScript=basename($_SERVER['SCRIPT_FILENAME']??'');
if(!in_array($publicScript,['login.php','logout.php'],true)) require_login();

$roleRules=['index.php'=>['cashier','pharmacist','manager','admin'],'pos.php'=>['cashier','pharmacist','manager','admin'],'customers.php'=>['cashier','pharmacist','manager','admin'],'cash_drawer.php'=>['cashier','pharmacist','manager','admin'],'medicines.php'=>['pharmacist','manager','admin'],'inventory.php'=>['pharmacist','manager','admin'],'purchases.php'=>['pharmacist','manager','admin'],'suppliers.php'=>['manager','admin'],'expenses.php'=>['manager','admin'],'returns.php'=>['pharmacist','manager','admin'],'reports.php'=>['manager','admin'],'users.php'=>['admin'],'backup.php'=>['admin'],'backup_download.php'=>['admin']];
if(isset($roleRules[$publicScript])) require_role($roleRules[$publicScript]);

// Replace page-specific headers with one consistent pharmacy header everywhere.
if(!in_array($publicScript,['login.php','logout.php'],true)) {
    ob_start(function(string $html): string {
        if(stripos($html,'<header')===false) return $html;
        $u=current_user()??['username'=>'','role'=>'cashier'];
        $script=basename($_SERVER['SCRIPT_FILENAME']??'');
        $dashboardActive=$script==='index.php'?' class="active"':'';
        $posActive=$script==='pos.php'?' class="active"':'';
        $managementActive=in_array($script,['manage.php','inventory.php','purchases.php','suppliers.php','customers.php','expenses.php','returns.php','reports.php','cash_drawer.php','backup.php','users.php','audit.php'],true)?' class="active"':'';
        $header='<header><div class="brand">💊 '.e(APP_NAME).'</div><nav><a'.$dashboardActive.' href="index.php">Dashboard</a><a'.$posActive.' href="pos.php">POS</a><a'.$managementActive.' href="manage.php">Management</a></nav><div class="user-info">👤 '.e($u['username']).' · '.e(ucfirst($u['role'])).' <a href="logout.php">Logout</a></div></header>';
        return preg_replace('~<header\b[^>]*>.*?</header>~is',$header,$html,1)??$html;
    });
}
