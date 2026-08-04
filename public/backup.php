<?php declare(strict_types=1);
require __DIR__.'/../config/config.php';

// Backup/restore is intentionally local-only and should be protected by your app's admin login when authentication is enabled.
$db = db();
$error = '';
$success = '';
$backupDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'backups';
if (!is_dir($backupDir)) @mkdir($backupDir, 0755, true);

function backup_sql(PDO $db): string {
    $out = "-- MyPharmacyPOS database backup\n-- Generated: ".date('Y-m-d H:i:s')."\nSET FOREIGN_KEY_CHECKS=0;\n\n";
    $tables = $db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $table) {
        $safe = str_replace('`','``',$table);
        $row = $db->query("SHOW CREATE TABLE `{$safe}`")->fetch(PDO::FETCH_ASSOC);
        $create = $row['Create Table'] ?? '';
        if ($create === '') continue;
        $out .= "DROP TABLE IF EXISTS `{$safe}`;\n".$create.";\n\n";
        $rows = $db->query("SELECT * FROM `{$safe}`")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $data) {
            $cols=[];$vals=[];
            foreach ($data as $col=>$value) {
                $cols[]='`'.str_replace('`','``',$col).'`';
                $vals[] = $value === null ? 'NULL' : $db->quote((string)$value);
            }
            $out .= 'INSERT INTO `'.$safe.'` ('.implode(',',$cols).') VALUES ('.implode(',',$vals).');' . "\n";
        }
        $out .= "\n";
    }
    return $out."SET FOREIGN_KEY_CHECKS=1;\n";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = $_POST['action'] ?? '';
        if ($action === 'create') {
            $sql = backup_sql($db);
            $name = 'mypharmacypos_backup_'.date('Y-m-d_H-i-s').'.sql';
            $path = $backupDir . DIRECTORY_SEPARATOR . $name;
            if (file_put_contents($path, $sql) === false) throw new RuntimeException('Could not write the backup file. Check permissions on the backups folder.');
            $success = 'Backup created successfully: '.$name;
        } elseif ($action === 'restore') {
            $file = basename((string)($_POST['backup_file'] ?? ''));
            if ($file === '' || !preg_match('/^mypharmacypos_backup_[0-9_-]+\.sql$/', $file)) throw new RuntimeException('Invalid backup file.');
            $path = $backupDir . DIRECTORY_SEPARATOR . $file;
            if (!is_file($path)) throw new RuntimeException('Backup file not found.');
            $sql = file_get_contents($path);
            if ($sql === false) throw new RuntimeException('Could not read backup file.');
            $db->exec($sql);
            $success = 'Backup restored successfully. Please refresh the page.';
        }
    } catch (Throwable $e) { $error = $e->getMessage(); }
}
$files = glob($backupDir . DIRECTORY_SEPARATOR . 'mypharmacypos_backup_*.sql') ?: [];
usort($files, fn($a,$b)=>filemtime($b)<=>filemtime($a));
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Backup & Restore · <?=e(APP_NAME)?></title><link rel="stylesheet" href="style.css"><style>.backup-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px}.backup-card{padding:22px;border:1px solid #e2e8f0;border-radius:18px;background:#fff}.backup-icon{font-size:34px;margin-bottom:10px}.backup-card h2{margin:0 0 7px}.backup-card p{color:#64748b;font-size:13px;line-height:1.6}.backup-list{margin-top:18px}.backup-row{display:flex;justify-content:space-between;align-items:center;gap:15px;padding:14px 0;border-bottom:1px solid #e2e8f0}.backup-row small{display:block;color:#64748b;margin-top:3px}.danger{background:#fff7ed;border:1px solid #fed7aa;padding:13px;border-radius:12px;color:#9a3412;font-size:13px;margin-top:15px}.backup-actions{display:flex;gap:10px;flex-wrap:wrap}@media(max-width:760px){.backup-grid{grid-template-columns:1fr}.backup-row{align-items:flex-start;flex-direction:column}}</style></head><body><header><div class="brand">💊 <?=e(APP_NAME)?></div><nav><a href="index.php">Dashboard</a><a href="pos.php">POS</a><a href="inventory.php">Inventory</a><a class="active" href="manage.php">Management</a></nav></header><main><div class="page-title"><div><div class="eyebrow">DATA SAFETY</div><h1>Backup & Restore</h1><p>Protect your pharmacy database before making major changes.</p></div></div><?php if($error):?><div class="alert"><?=e($error)?></div><?php endif;?><?php if($success):?><div class="success">✓ <?=e($success)?></div><?php endif;?><div class="backup-grid"><section class="backup-card"><div class="backup-icon">💾</div><h2>Create Backup</h2><p>Create a complete SQL backup of your current pharmacy database. The backup is stored in the local <code>backups</code> folder.</p><form method="post"><input type="hidden" name="action" value="create"><button class="btn">Create Database Backup</button></form></section><section class="backup-card"><div class="backup-icon">⚠️</div><h2>Restore Backup</h2><p>Restoring replaces the current database tables and data with the selected backup.</p><div class="danger"><b>Important:</b> Always create a fresh backup before restoring an older one.</div></section></div><section class="panel backup-list"><h2>Available Backups</h2><?php if(!$files):?><div class="empty"><div class="empty-icon">💾</div><b>No backups yet</b><small>Create your first backup above.</small></div><?php else: foreach($files as $file):$name=basename($file);?><div class="backup-row"><div><b><?=e($name)?></b><small><?=date('d M Y, h:i A',filemtime($file))?> · <?=number_format(filesize($file)/1024,1)?> KB</small></div><div class="backup-actions"><a class="btn" href="backup_download.php?file=<?=rawurlencode($name)?>">Download</a><form method="post" onsubmit="return confirm('Restore this backup? Current database data may be replaced. Create a fresh backup first. Continue?')"><input type="hidden" name="action" value="restore"><input type="hidden" name="backup_file" value="<?=e($name)?>"><button class="btn" type="submit">Restore</button></form></div></div><?php endforeach; endif;?></section></main></body></html>