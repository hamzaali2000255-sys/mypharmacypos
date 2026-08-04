<?php
declare(strict_types=1);

const DB_HOST = '127.0.0.1';
const DB_NAME = 'mypharmacypos';
const DB_USER = 'root';
const DB_PASS = '';
const APP_NAME = 'MyPharmacyPOS';

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
function require_role(array $roles): void { $u=current_user(); if(!$u){header('Location: login.php');exit;} if(!in_array($u['role'],$roles,true)){http_response_code(403);exit('Access denied. Your user role does not have permission to open this page.');} }
$publicScript=basename($_SERVER['SCRIPT_FILENAME']??''); if(!in_array($publicScript,['login.php','logout.php'],true)) require_login();
