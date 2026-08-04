<?php
declare(strict_types=1);
require __DIR__ . '/../config/config.php';

$action = $_GET['action'] ?? 'dashboard';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'add_medicine') {
    $stmt = db()->prepare('INSERT INTO medicines (name,generic_name,strength,manufacturer,barcode,unit_name,units_per_strip,strips_per_box,purchase_price,retail_unit_price,reorder_level) VALUES (?,?,?,?,?,?,?,?,?,?,?)');
    $stmt->execute([
        trim($_POST['name']), trim($_POST['generic_name'] ?? ''), trim($_POST['strength'] ?? ''), trim($_POST['manufacturer'] ?? ''),
        trim($_POST['barcode'] ?? '') ?: null, trim($_POST['unit_name'] ?? 'tablet'), max(1,(int)$_POST['units_per_strip']),
        max(1,(int)$_POST['strips_per_box']), max(0,(float)$_POST['purchase_price']), max(0,(float)$_POST['retail_unit_price']), max(0,(int)$_POST['reorder_level'])
    ]);
    header('Location: ?action=medicines&saved=1'); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'receive_stock') {
    $medicineId=(int)$_POST['medicine_id']; $boxes=max(0,(int)$_POST['boxes']); $strips=max(0,(int)$_POST['strips']); $units=max(0,(int)$_POST['units']);
    $m=db()->prepare('SELECT * FROM medicines WHERE id=?'); $m->execute([$medicineId]); $medicine=$m->fetch();
    if ($medicine) {
        $totalUnits=$boxes*$medicine['strips_per_box']*$medicine['units_per_strip']+$strips*$medicine['units_per_strip']+$units;
        $s=db()->prepare('INSERT INTO batches (medicine_id,batch_no,expiry_date,units_received,units_remaining,purchase_unit_cost) VALUES (?,?,?,?,?,?)');
        $s->execute([$medicineId,trim($_POST['batch_no']),$_POST['expiry_date'] ?: null,$totalUnits,$totalUnits,(float)$_POST['purchase_unit_cost']]);
        $batchId=(int)db()->lastInsertId();
        db()->prepare('INSERT INTO stock_movements (medicine_id,batch_id,movement_type,quantity_units,note) VALUES (?,?,"purchase",?,?)')->execute([$medicineId,$batchId,$totalUnits,'Stock received']);
    }
    header('Location: ?action=inventory&saved=1'); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'sale') {
    $items=json_decode($_POST['items'] ?? '[]', true) ?: [];
    $pdo=db(); $pdo->beginTransaction();
    try {
        $subtotal=0; foreach($items as $item) $subtotal += (float)$item['price'] * (int)$item['qty'];
        $discount=max(0,(float)($_POST['discount'] ?? 0)); $total=max(0,$subtotal-$discount);
        $invoice='INV-'.date('YmdHis').'-'.random_int(100,999);
        $pdo->prepare('INSERT INTO sales(invoice_no,subtotal,discount,total,payment_method,paid) VALUES(?,?,?,?,?,?)')->execute([$invoice,$subtotal,$discount,$total,$_POST['payment_method'] ?? 'cash',(float)($_POST['paid'] ?? $total)]);
        $saleId=(int)$pdo->lastInsertId();
        foreach($items as $item){
            $qty=(int)$item['qty']; $mid=(int)$item['id'];
            $b=$pdo->prepare('SELECT * FROM batches WHERE medicine_id=? AND units_remaining>=? ORDER BY expiry_date IS NULL, expiry_date ASC, id ASC FOR UPDATE'); $b->execute([$mid,$qty]); $batch=$b->fetch();
            if(!$batch) throw new RuntimeException('Insufficient stock for medicine ID '.$mid);
            $line=(float)$item['price']*$qty;
            $pdo->prepare('INSERT INTO sale_items(sale_id,medicine_id,batch_id,quantity_units,unit_price,line_total) VALUES(?,?,?,?,?,?)')->execute([$saleId,$mid,$batch['id'],$qty,$item['price'],$line]);
            $pdo->prepare('UPDATE batches SET units_remaining=units_remaining-? WHERE id=?')->execute([$qty,$batch['id']]);
            $pdo->prepare('INSERT INTO stock_movements(medicine_id,batch_id,movement_type,quantity_units,reference_id,note) VALUES(?,?,"sale",?,?,?)')->execute([$mid,$batch['id'],-$qty,$saleId,'POS sale']);
        }
        $pdo->commit(); header('Location: ?action=receipt&id='.$saleId); exit;
    } catch(Throwable $e){ $pdo->rollBack(); http_response_code(400); exit('Sale failed: '.e($e->getMessage())); }
}

function totalStock(int $medicineId): int { $s=db()->prepare('SELECT COALESCE(SUM(units_remaining),0) n FROM batches WHERE medicine_id=?'); $s->execute([$medicineId]); return (int)$s->fetch()['n']; }
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?=e(APP_NAME)?></title><link rel="stylesheet" href="style.css"></head><body>
<header><div class="brand">💊 MyPharmacyPOS</div><nav><a href="?action=dashboard">Dashboard</a><a href="?action=pos">POS</a><a href="?action=medicines">Medicines</a><a href="?action=inventory">Inventory</a><a href="?action=reports">Reports</a></nav></header>
<main>
<?php if($action==='dashboard'): $sales=(float)db()->query('SELECT COALESCE(SUM(total),0) n FROM sales WHERE DATE(created_at)=CURDATE()')->fetch()['n']; $count=(int)db()->query('SELECT COUNT(*) n FROM medicines WHERE active=1')->fetch()['n']; $exp=(int)db()->query('SELECT COUNT(*) n FROM batches WHERE expiry_date IS NOT NULL AND expiry_date<=DATE_ADD(CURDATE(),INTERVAL 90 DAY) AND units_remaining>0')->fetch()['n']; ?>
<h1>Pharmacy Dashboard</h1><div class="cards"><div><span>Today's sales</span><b>Rs. <?=money($sales)?></b></div><div><span>Medicines</span><b><?=$count?></b></div><div><span>Expiring ≤ 90 days</span><b><?=$exp?></b></div></div><div class="panel"><h2>Pharmacy-first POS</h2><p>Sell boxes, strips, or individual tablets. Inventory is maintained at the smallest unit so every loose-tablet sale is accurately deducted.</p><a class="btn" href="?action=pos">Open POS</a></div>
<?php elseif($action==='medicines'): $rows=db()->query('SELECT * FROM medicines WHERE active=1 ORDER BY name')->fetchAll(); ?>
<h1>Medicines</h1><div class="panel"><h2>Add medicine</h2><form method="post" action="?action=add_medicine" class="grid"><input name="name" placeholder="Brand name" required><input name="generic_name" placeholder="Generic name"><input name="strength" placeholder="Strength e.g. 500mg"><input name="manufacturer" placeholder="Manufacturer"><input name="barcode" placeholder="Barcode"><input name="unit_name" value="tablet" placeholder="Smallest unit"><input type="number" name="units_per_strip" value="10" min="1" placeholder="Units/strip"><input type="number" name="strips_per_box" value="10" min="1" placeholder="Strips/box"><input type="number" step="0.01" name="purchase_price" placeholder="Purchase price/unit"><input type="number" step="0.01" name="retail_unit_price" placeholder="Retail price/unit" required><input type="number" name="reorder_level" value="20" min="0" placeholder="Reorder level"><button class="btn">Save medicine</button></form></div><div class="panel"><table><tr><th>Medicine</th><th>Pack</th><th>Retail/unit</th><th>Stock units</th></tr><?php foreach($rows as $r): ?><tr><td><?=e($r['name'])?><small><?=e($r['generic_name'])?> <?=e($r['strength'])?></small></td><td><?=$r['units_per_strip']?>/strip × <?=$r['strips_per_box']?>/box</td><td>Rs. <?=money((float)$r['retail_unit_price'])?></td><td><?=totalStock((int)$r['id'])?></td></tr><?php endforeach;?></table></div>
<?php elseif($action==='inventory'): $rows=db()->query('SELECT m.*,COALESCE(SUM(b.units_remaining),0) stock FROM medicines m LEFT JOIN batches b ON b.medicine_id=m.id GROUP BY m.id ORDER BY m.name')->fetchAll(); $meds=db()->query('SELECT id,name,units_per_strip,strips_per_box FROM medicines WHERE active=1 ORDER BY name')->fetchAll(); ?>
<h1>Inventory & Batch Receiving</h1><div class="panel"><h2>Receive stock</h2><form method="post" action="?action=receive_stock" class="grid"><select name="medicine_id" required><option value="">Medicine</option><?php foreach($meds as $m): ?><option value="<?=$m['id']?>"><?=e($m['name'])?> (<?=$m['units_per_strip']?>/strip, <?=$m['strips_per_box']?> strips/box)</option><?php endforeach;?></select><input name="batch_no" placeholder="Batch number" required><input type="date" name="expiry_date"><input type="number" name="boxes" min="0" value="0" placeholder="Boxes"><input type="number" name="strips" min="0" value="0" placeholder="Loose strips"><input type="number" name="units" min="0" value="0" placeholder="Loose tablets"><input type="number" step="0.0001" name="purchase_unit_cost" min="0" placeholder="Cost per smallest unit"><button class="btn">Receive stock</button></form></div><div class="panel"><table><tr><th>Medicine</th><th>Units in stock</th><th>Reorder</th></tr><?php foreach($rows as $r): ?><tr><td><?=e($r['name'])?></td><td><?=$r['stock']?></td><td><?=$r['reorder_level']?></td></tr><?php endforeach;?></table></div>
<?php elseif($action==='pos'): $meds=db()->query('SELECT m.*,COALESCE(SUM(b.units_remaining),0) stock FROM medicines m LEFT JOIN batches b ON b.medicine_id=m.id WHERE m.active=1 GROUP BY m.id ORDER BY m.name')->fetchAll(); ?>
<h1>Point of Sale</h1><div class="pos"><div class="panel"><input id="search" placeholder="Search medicine or barcode" autofocus><div id="results"></div></div><div class="panel"><h2>Cart</h2><div id="cart"></div><div class="totals">Subtotal: <b id="subtotal">0.00</b><label>Discount <input id="discount" type="number" value="0" min="0" step="0.01"></label><strong>Total: <span id="total">0.00</span></strong></div><select id="payment"><option value="cash">Cash</option><option value="card">Card</option><option value="credit">Credit</option></select><input id="paid" type="number" min="0" step="0.01" placeholder="Amount paid"><button class="btn" onclick="checkout()">Complete sale</button></div></div>
<script>const medicines=<?=json_encode($meds)?>;let cart=[];const fmt=n=>Number(n).toFixed(2);function renderResults(){let q=document.getElementById('search').value.toLowerCase();let rs=medicines.filter(m=>(m.name+' '+(m.generic_name||'')+' '+(m.barcode||'')).toLowerCase().includes(q)).slice(0,12);document.getElementById('results').innerHTML=rs.map(m=>`<button class="result" onclick="add(${m.id})"><b>${m.name}</b><span>Rs. ${fmt(m.retail_unit_price)} / ${m.unit_name} · stock ${m.stock}</span></button>`).join('')}function add(id){let m=medicines.find(x=>x.id==id);if(!m||m.stock<1)return;let x=cart.find(x=>x.id==id);if(x){if(x.qty>=m.stock)return;x.qty++}else cart.push({id:m.id,name:m.name,price:Number(m.retail_unit_price),qty:1,max:Number(m.stock)});renderCart()}function renderCart(){document.getElementById('cart').innerHTML=cart.map((x,i)=>`<div class="cartrow"><span>${x.name}<small>Rs. ${fmt(x.price)} each</small></span><input type="number" min="1" max="${x.max}" value="${x.qty}" onchange="qty(${i},this.value)"><b>Rs. ${fmt(x.price*x.qty)}</b><button onclick="cart.splice(${i},1);renderCart()">×</button></div>`).join('');let s=cart.reduce((a,x)=>a+x.price*x.qty,0);let d=Number(document.getElementById('discount').value)||0;document.getElementById('subtotal').textContent=fmt(s);document.getElementById('total').textContent=fmt(Math.max(0,s-d))}function qty(i,v){cart[i].qty=Math.max(1,Math.min(cart[i].max,parseInt(v)||1));renderCart()}function checkout(){if(!cart.length)return alert('Cart is empty');let d=Number(document.getElementById('discount').value)||0;let s=cart.reduce((a,x)=>a+x.price*x.qty,0),t=Math.max(0,s-d),paid=Number(document.getElementById('paid').value)||t;let f=document.createElement('form');f.method='post';f.action='?action=sale';[['items',JSON.stringify(cart)],['discount',d],['payment_method',document.getElementById('payment').value],['paid',paid]].forEach(([n,v])=>{let i=document.createElement('input');i.type='hidden';i.name=n;i.value=v;f.appendChild(i)});document.body.appendChild(f);f.submit()}document.getElementById('search').addEventListener('input',renderResults);document.getElementById('discount').addEventListener('input',renderCart);renderResults();renderCart();</script>
<?php elseif($action==='receipt'): $s=db()->prepare('SELECT * FROM sales WHERE id=?');$s->execute([(int)$_GET['id']]);$sale=$s->fetch();$i=db()->prepare('SELECT si.*,m.name FROM sale_items si JOIN medicines m ON m.id=si.medicine_id WHERE sale_id=?');$i->execute([$sale['id']]);$items=$i->fetchAll(); ?><div class="receipt panel"><h1><?=e(APP_NAME)?></h1><p>Invoice: <?=e($sale['invoice_no'])?></p><?php foreach($items as $x): ?><div class="cartrow"><span><?=e($x['name'])?> × <?=$x['quantity_units']?></span><b>Rs. <?=money((float)$x['line_total'])?></b></div><?php endforeach;?><hr><p>Subtotal: Rs. <?=money((float)$sale['subtotal'])?></p><p>Discount: Rs. <?=money((float)$sale['discount'])?></p><h2>Total: Rs. <?=money((float)$sale['total'])?></h2><button class="btn" onclick="window.print()">Print receipt</button></div>
<?php elseif($action==='reports'): $top=db()->query('SELECT m.name,SUM(si.quantity_units) units,SUM(si.line_total) sales FROM sale_items si JOIN medicines m ON m.id=si.medicine_id GROUP BY m.id ORDER BY units DESC LIMIT 20')->fetchAll(); ?><h1>Sales Report</h1><div class="panel"><table><tr><th>Medicine</th><th>Units sold</th><th>Sales</th></tr><?php foreach($top as $r): ?><tr><td><?=e($r['name'])?></td><td><?=$r['units']?></td><td>Rs. <?=money((float)$r['sales'])?></td></tr><?php endforeach;?></table></div><?php endif; ?></main></body></html>
