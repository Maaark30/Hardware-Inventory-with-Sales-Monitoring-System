<?php
include 'project.php';
session_start();

if (!isset($_SESSION['username']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: login.php");
    exit();
}

$username = $_SESSION['username'];
$role     = $_SESSION['role'] ?? 'Admin';
?>
<script>
function showConfirm({title = 'Are you sure?', message, okText = 'OK', okClass = 'btn-confirm-ok', icon = 'bi-exclamation-triangle-fill', callback}) {
    const id = 'cConfirm_' + Date.now();
    const html = `
        <div class="modal fade" id="${id}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-sm">
                <div class="modal-content confirm-modal">
                    <div class="confirm-modal-body">
                        <div class="confirm-icon" style="${okClass.includes('red') ? '' : 'background:var(--blue-mid); color:var(--blue);'}">
                            <i class="bi ${icon}"></i>
                        </div>
                        <div style="font-weight:800; font-size:.95rem; margin-bottom:8px; color:var(--ink);">${title}</div>
                        <div class="confirm-message" style="font-size:.78rem; color:var(--muted);">${message}</div>
                    </div>
                    <div class="confirm-modal-footer">
                        <button type="button" class="btn-confirm-cancel" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn-confirm-ok ${okClass}" id="ok_${id}">${okText}</button>
                    </div>
                </div>
            </div>
        </div>`;
    document.body.insertAdjacentHTML('beforeend', html);
    const modal = new bootstrap.Modal(document.getElementById(id));
    modal.show();
    document.getElementById('ok_' + id).addEventListener('click', () => { callback(); modal.hide(); });
    document.getElementById(id).addEventListener('hidden.bs.modal', () => document.getElementById(id).remove());
    return false;
}
function showAlert({title = 'Notice', message, icon = 'bi-info-circle-fill', color = 'var(--blue)'}) {
    const id = 'cAlert_' + Date.now();
    const html = `
        <div class="modal fade" id="${id}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-sm">
                <div class="modal-content confirm-modal">
                    <div class="confirm-modal-body">
                        <div class="confirm-icon" style="background:${color}15; color:${color};">
                            <i class="bi ${icon}"></i>
                        </div>
                        <div style="font-weight:800; font-size:.95rem; margin-bottom:8px; color:var(--ink);">${title}</div>
                        <div class="confirm-message" style="font-size:.78rem; color:var(--muted);">${message}</div>
                    </div>
                    <div class="confirm-modal-footer">
                        <button type="button" class="btn-confirm-ok" style="background:${color}; border-color:${color}; width:100%;" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>`;
    document.body.insertAdjacentHTML('beforeend', html);
    const modal = new bootstrap.Modal(document.getElementById(id));
    modal.show();
    document.getElementById(id).addEventListener('hidden.bs.modal', () => document.getElementById(id).remove());
    return false;
}
// Alias for backward compatibility
function showCustomAlert(message, callback) {
    return showConfirm({
        title: 'Confirm Delete',
        message: message,
        okText: 'Delete',
        okClass: 'btn-confirm-ok red',
        icon: 'bi-exclamation-triangle-fill',
        callback: callback
    });
}
</script>
<?php

function e($value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function money($value): string { return '₱' . number_format((float)$value, 2); }
function generateUniqueSKU(mysqli $conn): string {
    do {
        $n = (string)mt_rand(100000000000, 999999999999);
        $sE = $sO = 0;
        for ($i = 0; $i < 12; $i++) { $d = (int)$n[$i]; (($i+1)%2===0) ? $sE+=$d : $sO+=$d; }
        $tot = $sO + ($sE * 3); $rem = $tot % 10;
        $sku = $n . (($rem===0)?0:(10-$rem));
        $st = $conn->prepare("SELECT COUNT(*) FROM products WHERE sku = ?");
        $st->bind_param("s",$sku); $st->execute(); $st->bind_result($ex); $st->fetch(); $st->close();
    } while ($ex > 0);
    return $sku;
}
function buildPaginationUrl(int $page, array $p): string { unset($p['page']); $p['page']=$page; return 'products.php?'.http_build_query($p); }

$limit  = 10;
$page   = isset($_GET['page']) && is_numeric($_GET['page']) && (int)$_GET['page'] > 0 ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

$new_product_sku   = generateUniqueSKU($conn);
$categories_query  = $conn->query("SELECT * FROM categories ORDER BY category_name ASC");
$subcategories_query = $conn->query("SELECT * FROM subcategories ORDER BY subcategory_name ASC");

/* STOCK IN */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['stock_in_submit'])) {
    $stocked_by   = $_SESSION['username'];
    $supplier_id  = (int)($_POST['supplier_id'] ?? 0);
    $reference_no = trim($_POST['reference_no'] ?? '');
    $items_json   = $_POST['stock_in_data_json'] ?? '';
    $items_to_stock_in = json_decode($items_json, true);
    $success_count = 0; $errors = []; $batch_id = 0;
    if ($supplier_id <= 0) $errors[] = "Supplier not selected.";
    if (empty($items_to_stock_in) && empty($errors)) $errors[] = "No items selected.";
    if (empty($errors)) {
        $conn->begin_transaction();
        try {
            $sb = $conn->prepare("INSERT INTO stock_in_batches (reference_no, supplier_id, stocked_by) VALUES (?, ?, ?)");
            $sb->bind_param("sis", $reference_no, $supplier_id, $stocked_by);
            if (!$sb->execute()) throw new Exception("Batch insert failed.");
            $batch_id = $conn->insert_id; $sb->close();
            $su = $conn->prepare("UPDATE products SET stock = stock + ? WHERE product_id = ?");
            $si = $conn->prepare("INSERT INTO stock_history (product_id, supplier_id, quantity, expiry_date, supplier_price, total_cost, item_desc, stocked_by, batch_id, movement_type) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'IN')");
            foreach ($items_to_stock_in as $item) {
                $pid = (int)($item['product_id']??0); $qty = (int)($item['quantity']??0);
                $exp = !empty($item['expiry_date']) ? $item['expiry_date'] : null;
                $spr = (float)($item['supplier_price']??0);
                if ($pid<=0||$qty<=0) continue;
                $tc=$spr*$qty; 
                $su->bind_param("ii",$qty,$pid); if(!$su->execute()) throw new Exception("Stock update failed.");
                
                // Update product's last supplier price as well
                $up = $conn->prepare("UPDATE products SET supplier_price = ? WHERE product_id = ?");
                $up->bind_param("di", $spr, $pid); $up->execute(); $up->close();

                $si->bind_param("iiisddssi",$pid,$supplier_id,$qty,$exp,$spr,$tc,$reference_no,$stocked_by,$batch_id);
                if(!$si->execute()) throw new Exception("History insert failed."); $success_count+=$qty;
            }
            $su->close(); $si->close(); $conn->commit();
        } catch (Exception $ex) { if($conn->in_transaction) $conn->rollback(); $errors[]="Critical: ".$ex->getMessage(); }
    }
    if ($success_count>0&&empty($errors)) $_SESSION['success']="Stocked in {$success_count} item(s) (Batch #{$batch_id}).";
    elseif ($success_count>0) $_SESSION['warning']="Partial. Errors: ".implode("; ",array_unique($errors));
    else $_SESSION['error']="Failed. Errors: ".implode("; ",array_unique($errors));
    header("Location: products.php"); exit();
}

/* ADD */
if ($_SERVER['REQUEST_METHOD']==='POST'&&isset($_POST['add_product'])){
    $name=trim($_POST['product_name']??''); $category=(int)($_POST['category_id']??0);
    $subcategory=!empty($_POST['subcategory_id'])?(int)$_POST['subcategory_id']:null;
    $unit=trim($_POST['unit']??''); $supplier_price=(float)($_POST['supplier_price']??0);
    $selling_price=(float)($_POST['selling_price']??0); $sku=trim($_POST['sku']??'');
    $description=isset($_POST['description'])?trim($_POST['description']):null; $description=($description==='')?null:$description;
    $brand=trim($_POST['brand']??''); $variation=trim($_POST['variation']??'');
    $reorder_level=(int)($_POST['reorder_level']??0); $expiring=isset($_POST['expiring'])?1:0;
    $stock=0; $created_by=$_SESSION['username'];
    $cs=$conn->prepare("SELECT COUNT(*) FROM products WHERE product_name=? AND brand=? AND variation=?");
    $cs->bind_param("sss",$name,$brand,$variation); $cs->execute(); $cs->bind_result($dc); $cs->fetch(); $cs->close();
    if($dc>0){$_SESSION['error']="Duplicate product."; header("Location: products.php"); exit();}
    if($sku==='') $sku=generateUniqueSKU($conn);
    $st=$conn->prepare("INSERT INTO products (product_name,category_id,subcategory_id,unit,supplier_price,selling_price,stock,sku,description,brand,variation,reorder_level,expiring,created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
    $st->bind_param("siisddisssssis",$name,$category,$subcategory,$unit,$supplier_price,$selling_price,$stock,$sku,$description,$brand,$variation,$reorder_level,$expiring,$created_by);
    if($st->execute()){$nid=$conn->insert_id;$sl=$conn->prepare("INSERT INTO product_history (product_id,user_username,action_type,description) VALUES (?,?,?,?)");$la="Created";$ld="Product '{$name}' created.";$sl->bind_param("isss",$nid,$created_by,$la,$ld);$sl->execute();$sl->close();$_SESSION['success']="Product '{$name}' added.";}
    else $_SESSION['error']="Error: ".$st->error;
    $st->close(); header("Location: products.php"); exit();
}

/* EDIT */
if ($_SERVER['REQUEST_METHOD']==='POST'&&isset($_POST['edit_product'])){
    $id=(int)($_POST['product_id']??0); $name=trim($_POST['product_name']??'');
    $category=(int)($_POST['category_id']??0); $subcategory=!empty($_POST['subcategory_id'])?(int)$_POST['subcategory_id']:null;
    $unit=trim($_POST['unit']??''); $supplier_price=(float)($_POST['supplier_price']??0);
    $selling_price=(float)($_POST['selling_price']??0); $stock=(int)($_POST['stock']??0);
    $sku=trim($_POST['sku']??''); $description=isset($_POST['description'])?trim($_POST['description']):null;
    $description=($description==='')?null:$description; $brand=trim($_POST['brand']??'');
    $variation=trim($_POST['variation']??''); $reorder_level=(int)($_POST['reorder_level']??0);
    $expiring=isset($_POST['expiring'])?1:0; $updated_by=$_SESSION['username'];
    // Duplicate check: ensure no OTHER product has the same name+brand+variation
    $cs=$conn->prepare("SELECT COUNT(*) FROM products WHERE product_name=? AND brand=? AND variation=? AND product_id != ?");
    $cs->bind_param("sssi",$name,$brand,$variation,$id); $cs->execute(); $cs->bind_result($dc); $cs->fetch(); $cs->close();
    if($dc>0){$_SESSION['error']="Duplicate product: another product with the same name, brand, and variation already exists."; header("Location: products.php"); exit();}
    $st=$conn->prepare("UPDATE products SET product_name=?,category_id=?,subcategory_id=?,unit=?,supplier_price=?,selling_price=?,stock=?,sku=?,description=?,brand=?,variation=?,reorder_level=?,expiring=?,last_updated_by=? WHERE product_id=?");
    $st->bind_param("siisddisssssisi",$name,$category,$subcategory,$unit,$supplier_price,$selling_price,$stock,$sku,$description,$brand,$variation,$reorder_level,$expiring,$updated_by,$id);
    if($st->execute()){$sl=$conn->prepare("INSERT INTO product_history (product_id,user_username,action_type,description) VALUES (?,?,?,?)");$la="Updated";$ld="Product '{$name}' updated.";$sl->bind_param("isss",$id,$updated_by,$la,$ld);$sl->execute();$sl->close();$_SESSION['success']="Product '{$name}' updated.";}
    else $_SESSION['error']="Error: ".$st->error;
    $st->close(); header("Location: products.php"); exit();
}

/* DELETE */
if (($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['delete_product']) || isset($_POST['delete_id']))) || 
    ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['delete_id']))) {
    $idt = (int)($_POST['delete_id'] ?? $_GET['delete_id'] ?? 0);
    $db = $_SESSION['username'];
    $sf = $conn->prepare("SELECT product_id, product_name, sku, stock FROM products WHERE product_id = ?");
    $sf->bind_param("i", $idt); $sf->execute(); $pd = $sf->get_result()->fetch_assoc(); $sf->close();
    
    if ($pd) {
        if ((int)$pd['stock'] > 0) {
            $_SESSION['warning'] = "Cannot delete '{$pd['product_name']}' because it still has " . $pd['stock'] . " in stock. Please stock out first.";
            header("Location: products.php"); exit();
        }
        
        $sdl = $conn->prepare("INSERT INTO product_deletion_log (product_id, product_name, sku, deleted_by, deleted_at) VALUES (?, ?, ?, ?, NOW())");
        $sdl->bind_param("isss", $pd['product_id'], $pd['product_name'], $pd['sku'], $db);
        if ($sdl->execute()) {
            try {
                $sd = $conn->prepare("DELETE FROM products WHERE product_id = ?");
                $sd->bind_param("i", $idt);
                if ($sd->execute()) {
                    $sh = $conn->prepare("INSERT INTO product_history (product_id, user_username, action_type, description) VALUES (?, ?, ?, ?)");
                    $la = "Deleted"; $ld = "Product '{$pd['product_name']}' deleted.";
                    $sh->bind_param("isss", $idt, $db, $la, $ld);
                    $sh->execute(); $sh->close();
                    $_SESSION['success'] = "Product deleted successfully.";
                } else {
                    $_SESSION['error'] = "Delete failed: " . $sd->error;
                }
                $sd->close();
            } catch (mysqli_sql_exception $e) {
                if ($e->getCode() === 1451) {
                    $_SESSION['error'] = "Cannot delete '{$pd['product_name']}' because it has existing transaction history (sales or stock records).";
                } else {
                    $_SESSION['error'] = "Database error: " . $e->getMessage();
                }
            }
        } else {
            $_SESSION['error'] = "Log failed.";
        }
        $sdl->close();
    } else {
        $_SESSION['error'] = "Product not found.";
    }
    header("Location: products.php"); exit();
}

/* SUMMARY */
$sr=$conn->query("SELECT COUNT(*) AS total_products,SUM(CASE WHEN stock>0 THEN 1 ELSE 0 END) AS in_stock,SUM(CASE WHEN stock=0 THEN 1 ELSE 0 END) AS out_of_stock,SUM(CASE WHEN reorder_level>0 AND stock>0 AND stock<=reorder_level THEN 1 ELSE 0 END) AS low_stock FROM products")->fetch_assoc();
$total_products_summary=(int)($sr['total_products']??0);
$in_stock_count=(int)($sr['in_stock']??0);
$out_of_stock_count=(int)($sr['out_of_stock']??0);
$low_stock_count=(int)($sr['low_stock']??0);

$lowStockProducts=[];
$lsq=$conn->query("SELECT product_name,brand,variation,unit,stock,reorder_level FROM products WHERE stock>0 AND stock<=reorder_level AND reorder_level>0 ORDER BY stock ASC");
if($lsq) while($r=$lsq->fetch_assoc()) $lowStockProducts[]=$r;

$outOfStockProducts=[];
$osq=$conn->query("SELECT product_id,product_name,brand,variation,unit,stock,reorder_level,category_id FROM products WHERE stock=0 ORDER BY product_name ASC");
if($osq) while($r=$osq->fetch_assoc()) $outOfStockProducts[]=$r;

$productsByCategory=[];
$cbq=$conn->query("SELECT c.category_id,c.category_name,COUNT(p.product_id) AS total_products,SUM(p.stock) AS total_stock,SUM(CASE WHEN p.stock=0 THEN 1 ELSE 0 END) AS out_of_stock_count,SUM(CASE WHEN p.reorder_level>0 AND p.stock>0 AND p.stock<=p.reorder_level THEN 1 ELSE 0 END) AS low_stock_count FROM categories c LEFT JOIN products p ON c.category_id=p.category_id GROUP BY c.category_id,c.category_name ORDER BY c.category_name ASC");
if($cbq) while($r=$cbq->fetch_assoc()) $productsByCategory[]=$r;

/* FILTERS */
$where_clauses=[]; $params=[]; $types='';
$brand_filter=trim($_GET['brand_filter']??'');
$variant_filter=trim($_GET['variant_filter']??'');
$stock_status_filter=trim($_GET['stock_status']??'');
$search_query=trim($_GET['q']??'');

if($stock_status_filter!==''){
    switch($stock_status_filter){
        case 'ok': $where_clauses[]="(p.stock > p.reorder_level OR (p.stock > 0 AND (p.reorder_level IS NULL OR p.reorder_level = 0)))"; break;
        case 'warning': $where_clauses[]="p.stock > 0 AND p.stock <= p.reorder_level AND p.reorder_level > 0"; break;
        case 'out': $where_clauses[]="p.stock = 0"; break;
        case 'expired': $where_clauses[]="EXISTS (SELECT 1 FROM stock_history sh WHERE sh.product_id = p.product_id AND sh.expiry_date IS NOT NULL AND sh.expiry_date < CURDATE() AND sh.quantity > 0)"; break;
    }
}
if($search_query!==''){$st='%'.$search_query.'%';$where_clauses[]="(p.product_name LIKE ? OR p.sku LIKE ? OR c.category_name LIKE ? OR s.subcategory_name LIKE ? OR p.brand LIKE ? OR p.variation LIKE ?)";array_push($params,$st,$st,$st,$st,$st,$st);$types.='ssssss';}
if(!empty($_GET['category_id'])){$where_clauses[]="p.category_id = ?";$params[]=(int)$_GET['category_id'];$types.='i';}
if(!empty($_GET['subcategory_id'])){$where_clauses[]="p.subcategory_id = ?";$params[]=(int)$_GET['subcategory_id'];$types.='i';}
if($brand_filter!==''){$where_clauses[]="p.brand LIKE ?";$params[]='%'.$brand_filter.'%';$types.='s';}
if($variant_filter!==''){$where_clauses[]="p.variation LIKE ?";$params[]='%'.$variant_filter.'%';$types.='s';}

$where_sql=!empty($where_clauses)?" WHERE ".implode(" AND ",$where_clauses):'';
$base_from="FROM products p LEFT JOIN categories c ON p.category_id=c.category_id LEFT JOIN subcategories s ON p.subcategory_id=s.subcategory_id";

$cs2=$conn->prepare("SELECT COUNT(p.product_id) $base_from $where_sql");
if(!empty($params)) $cs2->bind_param($types,...$params);
$cs2->execute(); $cs2->bind_result($total_filtered_products); $cs2->fetch(); $cs2->close();

$total_pages=max(1,(int)ceil($total_filtered_products/$limit));
if($page>$total_pages){$page=$total_pages;$offset=($page-1)*$limit;}

$report_fields_map=[
    'Category'=>fn($r)=>e($r['category_name']??'Unknown'),
    'Subcategory'=>fn($r)=>e($r['subcategory_name']??''),
    'Product'=>fn($r)=>e($r['product_name']),
    'Brand'=>fn($r)=>e($r['brand']??''),
    'Variation'=>fn($r)=>e($r['variation']??''),
    'Stock'=>fn($r)=>formatQty($r['stock']??0).' '.e($r['unit']??''),
    'Selling Price'=>fn($r)=>money($r['selling_price']??0),
    'Description'=>fn($r)=>e($r['description']??''),
];

$print_all=isset($_GET['print_all']);
$selected_report_fields=[];
if($print_all&&isset($_GET['report_fields'])){
    $selected_report_fields=array_values(array_intersect(array_keys($report_fields_map),array_filter(array_map('trim',explode(',',$_GET['report_fields'])))));
}
if(empty($selected_report_fields)) $selected_report_fields=array_keys($report_fields_map);

$sql="SELECT p.*,c.category_name,s.subcategory_name,CASE WHEN EXISTS (SELECT 1 FROM stock_history sh WHERE sh.product_id=p.product_id AND sh.expiry_date IS NOT NULL AND sh.expiry_date<CURDATE() AND sh.quantity>0) THEN 1 ELSE 0 END AS has_expired_batches $base_from $where_sql ORDER BY p.created_at DESC";
if(!$print_all) $sql.=" LIMIT ? OFFSET ?";

$stmt=$conn->prepare($sql);
if($print_all){if(!empty($params))$stmt->bind_param($types,...$params);}
else{$pp=array_merge($params,[$limit,$offset]);$stmt->bind_param($types.'ii',...$pp);}
$stmt->execute(); $result=$stmt->get_result(); $stmt->close();

/* ============================================================
   PRINT / EXPORT VIEW
   ============================================================ */
if($print_all){
    $printedOn  = (new DateTime())->format('M j, Y, g:i A');
    $dateFrom   = $_GET['date_from'] ?? null;
    $dateTo     = $_GET['date_to']   ?? null;
    $reportRange = ($dateFrom && $dateTo)
        ? date('M j, Y', strtotime($dateFrom)) . ' to ' . date('M j, Y', strtotime($dateTo))
        : 'All dates';

    // Convert logo to base64 so it embeds in the printed page
    $logoPath = __DIR__ . '/images/logo.png';
    $logoTag  = '';
    if (file_exists($logoPath)) {
        $logoB64 = base64_encode(file_get_contents($logoPath));
        $logoMime = 'image/png';
        $logoTag  = '<img src="data:' . $logoMime . ';base64,' . $logoB64 . '" alt="Logo" class="rpt-logo">';
    }
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/x-icon" href="favicon.ico">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product Report — K&amp;J B Hardware</title>
    <style>
        /* ── Reset & base ── */
       
        /* ── Report header ── */
        .rpt-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 24px;
            padding-bottom: 16px;
            border-bottom: 2px solid #1e3a8a;
            margin-bottom: 22px;
        }

        .rpt-header-left {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .rpt-logo-wrap {
            width: 52px;
            height: 52px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            overflow: hidden;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f8fafc;
        }

        .rpt-logo {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .rpt-logo-fallback {
            font-size: 18px;
            font-weight: 800;
            color: #1e3a8a;
            letter-spacing: -1px;
        }

        .rpt-company {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .rpt-company-name {
            font-size: 15px;
            font-weight: 700;
            color: #0f172a;
            letter-spacing: -0.3px;
            line-height: 1.2;
        }

        .rpt-subtitle {
            font-size: 11px;
            color: #64748b;
            font-weight: 400;
        }

        .rpt-header-right {
            text-align: right;
            flex-shrink: 0;
        }

        .rpt-generated {
            font-size: 10.5px;
            color: #64748b;
            line-height: 1.6;
        }

        .rpt-generated strong {
            display: block;
            font-size: 11px;
            color: #334155;
            font-weight: 600;
        }

        /* ── Active filters strip ── */
        .rpt-filters {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            padding: 8px 12px;
            background: #f1f5f9;
            border-radius: 6px;
            margin-bottom: 18px;
            font-size: 10.5px;
            color: #475569;
        }

        .rpt-filters-label {
            font-weight: 700;
            color: #1e3a8a;
            text-transform: uppercase;
            letter-spacing: .06em;
            font-size: 9.5px;
            margin-right: 4px;
        }

        .rpt-filter-chip {
            background: #fff;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            padding: 2px 7px;
            font-size: 10px;
            color: #334155;
            font-weight: 500;
        }

        /* ── Section label ── */
        .rpt-section-label {
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .12em;
            color: #94a3b8;
            margin-bottom: 8px;
        }

        /* ── Data table ── */
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
        }

        thead tr {
            background: #1e3a8a;
        }

        thead th {
            padding: 9px 11px;
            text-align: left;
            font-size: 9.5px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .09em;
            color: #ffffff;
            white-space: nowrap;
        }

        tbody tr:nth-child(even) { background: #f8fafc; }
        tbody tr:nth-child(odd)  { background: #ffffff; }

        tbody td {
            padding: 8px 11px;
            border-bottom: 1px solid #e2e8f0;
            color: #334155;
            vertical-align: top;
            line-height: 1.45;
        }

        tbody tr:last-child td { border-bottom: none; }

        /* ── Footer ── */
        .rpt-footer {
            margin-top: 28px;
            padding-top: 12px;
            border-top: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 9.5px;
            color: #94a3b8;
        }

        .rpt-footer-brand {
            font-weight: 600;
            color: #1e3a8a;
        }

        /* ── Empty state ── */
        .rpt-empty {
            text-align: center;
            padding: 40px 20px;
            color: #94a3b8;
            font-size: 12px;
        }

        /* ── Print overrides ── */
        @media print {
            body { padding: 16px 20px; }
            thead { display: table-header-group; }
            tbody tr { page-break-inside: avoid; }
        }
    </style>
</head>
<body>

    <!-- ── Report header ── -->
    <div class="rpt-header">
        <div class="rpt-header-left">
            <div class="rpt-logo-wrap">
                <?php if ($logoTag): echo $logoTag; else: ?>
                    <span class="rpt-logo-fallback">KJ</span>
                <?php endif; ?>
            </div>
            <div class="rpt-company">
                <div class="rpt-company-name">K&amp;J B Hardware &amp; Construction Supplies</div>
                <div class="rpt-subtitle">Sales &amp; Inventory Report &mdash; <?= e($reportRange) ?></div>
            </div>
        </div>
        <div class="rpt-header-right">
            <div class="rpt-generated">
                <strong>Generated: <?= e($printedOn) ?></strong>
                Total products: <?= number_format($total_filtered_products) ?>
            </div>
        </div>
    </div>

    <!-- ── Active filters strip (only shown when filters are applied) ── -->
    <?php
    $activeFilters = [];
    if (!empty($_GET['q']))              $activeFilters[] = 'Search: ' . e($_GET['q']);
    if (!empty($_GET['category_id']))    $activeFilters[] = 'Category ID: ' . (int)$_GET['category_id'];
    if (!empty($_GET['subcategory_id'])) $activeFilters[] = 'Subcategory ID: ' . (int)$_GET['subcategory_id'];
    if (!empty($_GET['brand_filter']))   $activeFilters[] = 'Brand: ' . e($_GET['brand_filter']);
    if (!empty($_GET['variant_filter'])) $activeFilters[] = 'Variation: ' . e($_GET['variant_filter']);
    if (!empty($_GET['stock_status']))   $activeFilters[] = 'Status: ' . ucfirst(e($_GET['stock_status']));
    ?>
    <?php if (!empty($activeFilters)): ?>
    <div class="rpt-filters">
        <span class="rpt-filters-label">Filters</span>
        <?php foreach ($activeFilters as $af): ?>
            <span class="rpt-filter-chip"><?= $af ?></span>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- ── Columns label ── -->
    <div class="rpt-section-label">Product Inventory</div>

    <!-- ── Data table ── -->
    <table>
        <thead>
            <tr>
                <?php foreach ($selected_report_fields as $f): ?>
                    <th><?= e($f) ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php if ($result && $result->num_rows > 0):
                while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <?php foreach ($selected_report_fields as $f): ?>
                        <td><?= $report_fields_map[$f]($row) ?></td>
                    <?php endforeach; ?>
                </tr>
            <?php endwhile; else: ?>
                <tr>
                    <td colspan="<?= count($selected_report_fields) ?>" class="rpt-empty">
                        No products found matching the current filters.
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- ── Footer ── -->
    <div class="rpt-footer">
        <span><span class="rpt-footer-brand">K&amp;J B Hardware &amp; Construction Supplies</span> &mdash; Confidential</span>
        <span>Printed <?= e($printedOn) ?></span>
    </div>

    <script>
        window.addEventListener('DOMContentLoaded', () => window.print());
    </script>
</body>
</html>
    <?php exit();
}

$has_active_filters = ($search_query || !empty($_GET['category_id']) || !empty($_GET['subcategory_id']) || $brand_filter || $variant_filter || $stock_status_filter);
?>
<!DOCTYPE html>
<html lang="en">
<head>
        <link rel="icon" type="image/x-icon" href="favicon.ico">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/admin1.css">
    <link rel="stylesheet" href="css/alert.css">

    <style>
        :root {
            --bg:           #eef1f8;
            --surface:      #ffffff;
            --surface-2:    #f7f9fc;
            --border:       #e2e8f0;
            --border-light: #edf2f7;
            --ink:          #0f172a;
            --ink-2:        #334155;
            --muted:        #64748b;
            --faint:        #94a3b8;
            --blue:         #2563eb;
            --blue-dk:      #1d4ed8;
            --blue-lt:      #eff6ff;
            --blue-mid:     #dbeafe;
            --green:        #059669;
            --green-lt:     #ecfdf5;
            --amber:        #d97706;
            --amber-lt:     #fffbeb;
            --red:          #dc2626;
            --red-lt:       #fef2f2;
            --violet:       #7c3aed;
            --violet-lt:    #f5f3ff;
            --r:            12px;
            --r-sm:         8px;
            --r-lg:         18px;
            --sh-xs:        0 1px 3px rgba(0,0,0,.05);
            --sh-sm:        0 2px 8px rgba(0,0,0,.06);
            --sh:           0 4px 20px rgba(0,0,0,.08);
            --sh-lg:        0 8px 32px rgba(0,0,0,.1);
            --font:         'Plus Jakarta Sans', sans-serif;
            --mono:         'JetBrains Mono', monospace;
        }
        *,*::before,*::after{box-sizing:border-box;}
        body { font-family:var(--font); background:var(--bg); color:var(--ink); font-size:14px; }
        .content { background:var(--bg); min-height:100vh; }
        .main-wrap { padding:28px 28px 64px; }
        .dropdown-toggle::after { display:none; }

        /* ── Toast notifications ── */
        .toast-stack {
            position:fixed; top:20px; right:20px; z-index:9999;
            display:flex; flex-direction:column; gap:8px; min-width:300px; max-width:420px;
        }
        .toast-item {
            display:flex; align-items:flex-start; gap:12px;
            padding:14px 16px; border-radius:var(--r);
            font-size:.82rem; font-weight:500;
            box-shadow:var(--sh-lg); border:1px solid transparent;
            animation: toastIn .3s cubic-bezier(.22,1,.36,1);
        }
        @keyframes toastIn { from{opacity:0;transform:translateX(24px)} to{opacity:1;transform:translateX(0)} }
        .toast-item.success { background:#fff; border-color:#bbf7d0; }
        .toast-item.success .toast-icon { color:var(--green); }
        .toast-item.error   { background:#fff; border-color:#fecaca; }
        .toast-item.error   .toast-icon { color:var(--red); }
        .toast-item.warning { background:#fff; border-color:#fde68a; }
        .toast-item.warning .toast-icon { color:var(--amber); }
        .toast-icon { font-size:1.1rem; flex-shrink:0; margin-top:1px; }
        .toast-text { flex:1; color:var(--ink-2); }
        .toast-close { background:none;border:none;color:var(--faint);cursor:pointer;font-size:.9rem;padding:0;line-height:1; }
        .toast-close:hover { color:var(--muted); }

        /* ── Page header ── */
        .page-hdr {
            display:flex; justify-content:space-between; align-items:flex-start;
            gap:16px; flex-wrap:wrap; margin-bottom:24px;
        }
        .page-hdr-left { display:flex; align-items:center; gap:14px; }
        .page-hdr-icon {
            width:48px; height:48px; border-radius:var(--r-sm);
            background:var(--blue); color:#fff;
            display:flex; align-items:center; justify-content:center;
            font-size:22px; flex-shrink:0;
            box-shadow:0 4px 12px rgba(37,99,235,.3);
        }
        .page-hdr h4 { font-size:1.3rem; font-weight:800; margin:0 0 3px; letter-spacing:-.4px; }
        .page-hdr p  { margin:0; font-size:.75rem; color:var(--muted); }
        .hdr-actions { display:flex; gap:10px; flex-wrap:wrap; }

        /* ── Buttons ── */
        .btn-hdr {
            display:inline-flex; align-items:center; gap:7px;
            padding:10px 18px; border:none; border-radius:var(--r-sm);
            font-family:var(--font); font-size:.8rem; font-weight:700;
            cursor:pointer; transition:all .15s; white-space:nowrap;
        }
        .btn-hdr.blue   { background:var(--blue); color:#fff; box-shadow:0 2px 8px rgba(37,99,235,.3); }
        .btn-hdr.blue:hover { background:var(--blue-dk); transform:translateY(-1px); box-shadow:0 4px 14px rgba(37,99,235,.4); }
        .btn-hdr.green  { background:var(--green); color:#fff; box-shadow:0 2px 8px rgba(5,150,105,.25); }
        .btn-hdr.green:hover { background:#047857; transform:translateY(-1px); }
        .btn-hdr.red    { background:var(--red); color:#fff; box-shadow:0 2px 8px rgba(220,38,38,.25); }
        .btn-hdr.red:hover { background:#b91c1c; transform:translateY(-1px); }

        /* ── Low stock banner ── */
        .alert-banner {
            display:flex; align-items:center; justify-content:space-between;
            gap:12px; flex-wrap:wrap;
            background:linear-gradient(135deg, #7f1d1d 0%, #991b1b 100%);
            border-radius:var(--r); padding:14px 20px;
            margin-bottom:20px;
            box-shadow:0 4px 16px rgba(153,27,27,.3);
        }
        .alert-banner-left { display:flex; align-items:center; gap:12px; }
        .alert-banner-icon { font-size:1.3rem; color:#fca5a5; }
        .alert-banner-title { font-weight:700; color:#fff; font-size:.9rem; }
        .alert-banner-sub   { font-size:.74rem; color:#fca5a5; margin-top:1px; }
        .btn-alert-view {
            padding:7px 16px; border-radius:var(--r-sm); border:1px solid rgba(255,255,255,.25);
            background:rgba(255,255,255,.12); color:#fff;
            font-family:var(--font); font-size:.75rem; font-weight:700;
            cursor:pointer; transition:background .12s; white-space:nowrap;
        }
        .btn-alert-view:hover { background:rgba(255,255,255,.22); }

        /* ── Stat cards ── */
        .stat-row {
            display:grid;
            grid-template-columns:repeat(4,1fr);
            gap:14px; margin-bottom:22px;
        }
        @media(max-width:1100px){.stat-row{grid-template-columns:repeat(2,1fr);}}
        @media(max-width:580px){.stat-row{grid-template-columns:1fr 1fr;}}

        .stat-card {
            background:var(--surface); border:1px solid var(--border);
            border-radius:var(--r); padding:18px 20px;
            box-shadow:var(--sh-xs);
            position:relative; overflow:hidden;
            transition:box-shadow .15s,transform .15s;
        }
        .stat-card:hover { box-shadow:var(--sh); transform:translateY(-1px); }
        .stat-card::after {
            content:''; position:absolute; top:0; left:0; right:0; height:3px;
            background:var(--ac, var(--blue)); border-radius:var(--r) var(--r) 0 0;
        }
        .stat-card.g { --ac:var(--green); }
        .stat-card.a { --ac:var(--amber); }
        .stat-card.r { --ac:var(--red); }
        .stat-lbl { font-size:.64rem; font-weight:700; text-transform:uppercase; letter-spacing:.1em; color:var(--muted); margin-bottom:10px; display:flex; align-items:center; gap:5px; }
        .stat-val { font-size:1.8rem; font-weight:800; letter-spacing:-.06em; line-height:1; }
        .stat-sub { font-size:.7rem; color:var(--faint); margin-top:5px; }

        /* ── Panel (filter / report) ── */
        .panel {
            background:var(--surface); border:1px solid var(--border);
            border-radius:var(--r); box-shadow:var(--sh-xs);
            margin-bottom:16px; overflow:hidden;
        }
        .panel-header {
            display:flex; align-items:center; justify-content:space-between;
            padding:13px 20px; border-bottom:1px solid var(--border-light);
            cursor:pointer; user-select:none; transition:background .12s;
        }
        .panel-header:hover { background:var(--surface-2); }
        .panel-hl { display:flex; align-items:center; gap:8px; font-size:.82rem; font-weight:700; color:var(--ink-2); }
        .panel-hl i { color:var(--blue); }
        .panel-chevron { color:var(--muted); font-size:.8rem; transition:transform .2s; }
        .panel-chevron.open { transform:rotate(180deg); }
        .panel-body { padding:20px; display:none; }
        .panel-body.open { display:block; }

        /* Active filter dot */
        .filter-dot { width:7px; height:7px; border-radius:50%; background:var(--blue); display:inline-block; }
        .filter-active-pill { font-size:.63rem; font-weight:700; color:var(--blue); background:var(--blue-lt); padding:2px 8px; border-radius:20px; }

        /* ── Form inputs ── */
        .f-label { display:block; font-size:.65rem; font-weight:700; text-transform:uppercase; letter-spacing:.08em; color:var(--muted); margin-bottom:5px; }
        .f-input {
            width:100%; padding:8px 12px;
            font-family:var(--font); font-size:.82rem;
            border:1.5px solid var(--border); border-radius:var(--r-sm);
            background:var(--surface-2); color:var(--ink);
            outline:none; transition:border-color .15s,box-shadow .15s;
        }
        .f-input:focus { border-color:var(--blue); box-shadow:0 0 0 3px rgba(37,99,235,.1); background:#fff; }
        .f-input::placeholder { color:var(--faint); }
        .f-search-wrap { position:relative; }
        .f-search-wrap i { position:absolute; left:11px; top:50%; transform:translateY(-50%); color:var(--faint); font-size:.95rem; pointer-events:none; }
        .f-search-wrap .f-input { padding-left:34px; }

        .filter-actions { display:flex; gap:10px; margin-top:18px; flex-wrap:wrap; }
        .btn-apply {
            display:inline-flex; align-items:center; gap:6px;
            padding:8px 18px; border:none; border-radius:var(--r-sm);
            background:var(--blue); color:#fff;
            font-family:var(--font); font-size:.78rem; font-weight:700;
            cursor:pointer; transition:background .12s;
            box-shadow:0 2px 8px rgba(37,99,235,.25);
        }
        .btn-apply:hover { background:var(--blue-dk); }
        .btn-reset {
            display:inline-flex; align-items:center; gap:6px;
            padding:8px 14px; border:1.5px solid var(--border); border-radius:var(--r-sm);
            background:#fff; color:var(--muted);
            font-family:var(--font); font-size:.78rem; font-weight:600;
            text-decoration:none; cursor:pointer; transition:all .12s;
        }
        .btn-reset:hover { background:var(--surface-2); color:var(--ink-2); }

        /* ── Report panel ── */
        .report-chips { display:flex; flex-wrap:wrap; gap:8px; margin-top:14px; }
        .report-chip {
            display:inline-flex; align-items:center; gap:6px;
            padding:5px 12px; border-radius:20px;
            border:1.5px solid var(--border); background:var(--surface-2);
            font-size:.73rem; font-weight:600; color:var(--ink-2);
            cursor:pointer; transition:all .12s; user-select:none;
        }
        .report-chip input { position:absolute; opacity:0; width:0; height:0; }
        .report-chip:has(input:checked) {
            background:var(--blue-lt); border-color:rgba(37,99,235,.4); color:var(--blue);
        }
        .report-chip-dot { width:7px; height:7px; border-radius:50%; background:var(--border); flex-shrink:0; transition:background .12s; }
        .report-chip:has(input:checked) .report-chip-dot { background:var(--blue); }

        .report-actions { display:flex; gap:10px; margin-top:16px; flex-wrap:wrap; }
        .btn-report {
            display:inline-flex; align-items:center; gap:7px;
            padding:8px 16px; border-radius:var(--r-sm);
            font-family:var(--font); font-size:.78rem; font-weight:700;
            cursor:pointer; transition:all .12s;
        }
        .btn-report.dl { background:var(--green-lt); color:var(--green); border:1.5px solid rgba(5,150,105,.25); }
        .btn-report.dl:hover { background:#d1fae5; }
        .btn-report.pr { background:var(--surface-2); color:var(--ink-2); border:1.5px solid var(--border); }
        .btn-report.pr:hover { background:var(--border-light); }
        .btn-toggle-sm {
            padding:5px 12px; border-radius:20px;
            font-family:var(--font); font-size:.7rem; font-weight:700;
            cursor:pointer; transition:all .12s; border:1.5px solid var(--border);
            background:var(--surface-2); color:var(--muted);
        }
        .btn-toggle-sm:hover { border-color:var(--blue); color:var(--blue); background:var(--blue-lt); }

        /* ── Table card ── */
        .table-card {
            background:var(--surface); border:1px solid var(--border);
            border-radius:var(--r); box-shadow:var(--sh-xs); overflow:hidden;
        }
        .table-card-hdr {
            display:flex; align-items:center; justify-content:space-between;
            padding:14px 20px; border-bottom:1px solid var(--border-light);
        }
        .table-card-title { font-size:.85rem; font-weight:700; color:var(--ink); display:flex; align-items:center; gap:8px; }
        .table-card-title i { color:var(--blue); }
        .record-pill { font-size:.7rem; color:var(--muted); background:var(--surface-2); border:1px solid var(--border); padding:3px 10px; border-radius:20px; font-weight:600; }

        /* ── Data table ── */
        .data-tbl { width:100%; border-collapse:collapse; }
        .data-tbl thead th {
            padding:14px 16px; font-size:.7rem; font-weight:800;
            text-transform:uppercase; letter-spacing:.1em; color:var(--ink);
            background:var(--surface-2); border-bottom:2px solid var(--border);
            white-space:nowrap;
        }
        .data-tbl tbody td { padding:16px 16px; border-bottom:1px solid var(--border-light); vertical-align:middle; }
        .data-tbl tbody tr:last-child td { border-bottom:none; }
        .data-tbl tbody tr { transition:background .1s; }
        .data-tbl tbody tr:hover { background:#f8fafc; }

        /* Cells */
        .cell-cat { font-size:.8rem; font-weight:600; color:var(--ink-2); }
        .cell-subcat { font-size:.72rem; color:var(--muted); margin-top:2px; }
        .cell-name { font-size:.95rem; font-weight:800; color:var(--ink); letter-spacing:-.2px; }
        .cell-meta { font-size:.78rem; color:var(--ink-2); margin-top:3px; font-weight:500; }
        .cell-price { font-family:var(--mono); font-size:.9rem; font-weight:700; color:var(--ink); }
        .cell-price-sub { font-family:var(--mono); font-size:.72rem; color:var(--muted); margin-top:3px; }
        .cell-sku { font-family:var(--mono); font-size:.75rem; color:var(--muted); margin-top:4px; font-weight:500; }
        .cell-desc { font-size:.78rem; color:var(--ink-2); max-width:180px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }

        /* Stock badge */
        .stock-badge {
            display:inline-flex; align-items:center; gap:6px;
            padding:6px 14px; border-radius:20px;
            font-size:.8rem; font-weight:700;
        }
        .stock-badge.ok      { background:var(--green-lt); color:var(--green); border:1.5px solid rgba(5,150,105,.1); }
        .stock-badge.low     { background:var(--amber-lt); color:var(--amber); border:1.5px solid rgba(217,119,6,.1); }
        .stock-badge.out     { background:var(--red-lt); color:var(--red); border:1.5px solid rgba(220,38,38,.1); }
        .stock-badge.expired { background:var(--violet-lt); color:var(--violet); border:1.5px solid rgba(124,58,237,.1); }
        .stock-num { font-family:var(--mono); font-size:.82rem; }
        .stock-status-lbl { font-size:.7rem; font-weight:700; color:var(--muted); margin-top:5px; text-transform:uppercase; letter-spacing:.05em; }

        /* Action dropdown */
        .tbl-actions-btn {
            width:30px; height:30px; border-radius:var(--r-sm);
            border:1.5px solid var(--border); background:var(--surface-2);
            color:var(--muted); cursor:pointer;
            display:inline-flex; align-items:center; justify-content:center;
            font-size:.9rem; transition:all .12s;
        }
        .tbl-actions-btn:hover { border-color:var(--blue); color:var(--blue); background:var(--blue-lt); }
        .dropdown-menu {
            border:1px solid var(--border); border-radius:var(--r-sm);
            box-shadow:var(--sh-lg); padding:6px;
            font-family:var(--font); min-width:170px;
        }
        .dropdown-item {
            border-radius:var(--r-sm); font-size:.8rem; font-weight:500;
            padding:8px 12px; color:var(--ink-2); transition:background .1s;
        }
        .dropdown-item:hover { background:var(--surface-2); }
        .dropdown-item.di-danger { color:var(--red); }
        .dropdown-item.di-danger:hover { background:var(--red-lt); }
        .dropdown-item.di-success { color:var(--green); }
        .dropdown-item.di-success:hover { background:var(--green-lt); }
        .dropdown-item.di-warning { color:var(--amber); }
        .dropdown-item.di-warning:hover { background:var(--amber-lt); }
        .dropdown-item.di-info { color:var(--blue); }
        .dropdown-item.di-info:hover { background:var(--blue-lt); }
        .dropdown-divider { border-color:var(--border-light); margin:4px 0; }

        /* ── Empty state ── */
        .empty-state { text-align:center; padding:56px 20px; }
        .empty-icon { width:56px; height:56px; background:var(--blue-lt); color:var(--blue); border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:22px; margin:0 auto 14px; }
        .empty-state h6 { font-size:.9rem; font-weight:700; margin-bottom:4px; }
        .empty-state p { font-size:.78rem; color:var(--muted); }

        /* ── Pagination ── */
        .pager-wrap { display:flex; align-items:center; justify-content:space-between; padding:14px 20px; border-top:1px solid var(--border-light); background:var(--surface-2); flex-wrap:wrap; gap:10px; }
        .pager-info { font-size:.71rem; color:var(--muted); }
        .pager { display:flex; gap:4px; }
        .pager a,.pager span { display:inline-flex; align-items:center; justify-content:center; width:32px; height:32px; font-size:.74rem; font-weight:600; border-radius:var(--r-sm); text-decoration:none; color:var(--ink-2); border:1.5px solid var(--border); background:#fff; transition:all .12s; }
        .pager a:hover:not(.active) { background:var(--blue-lt); border-color:rgba(37,99,235,.2); color:var(--blue); }
        .pager a.active { background:var(--blue); border-color:var(--blue); color:#fff; }
        .pager span.disabled { opacity:.35; cursor:not-allowed; }
        .pager span.dots { background:transparent; border-color:transparent; color:var(--faint); }

        /* ── Confirm modal ── */
        .confirm-modal { border:none; border-radius:var(--r); box-shadow:var(--sh-lg); font-family:var(--font); }
        .confirm-modal-body { padding:28px 24px 16px; text-align:center; }
        .confirm-icon { width:52px; height:52px; background:var(--red-lt); color:var(--red); border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:22px; margin:0 auto 14px; }
        .confirm-message { font-size:.88rem; color:var(--ink-2); line-height:1.5; }
        .confirm-modal-footer { display:flex; gap:8px; padding:12px 24px 20px; justify-content:center; }
        .btn-confirm-cancel { padding:9px 20px; border-radius:var(--r-sm); border:1.5px solid var(--border); background:#fff; color:var(--muted); font-family:var(--font); font-size:.8rem; font-weight:600; cursor:pointer; }
        .btn-confirm-cancel:hover { background:var(--surface-2); }
        .btn-confirm-ok { padding:9px 20px; border-radius:var(--r-sm); border:none; background:var(--green); color:#fff; font-family:var(--font); font-size:.8rem; font-weight:700; cursor:pointer; }
        .btn-confirm-ok:hover { background:#15803d; }
        .btn-confirm-ok.red { background:var(--red); }
        .btn-confirm-ok.red:hover { background:#b91c1c; }
        .btn-confirm-ok.green { background:var(--green); }
        .btn-confirm-ok.green:hover { background:#15803d; }

        /* ── Stock alert modal ── */
        .modal-content { border:none; border-radius:var(--r); box-shadow:var(--sh-lg); font-family:var(--font); }
        .modal-header { border-bottom:1px solid var(--border-light); padding:18px 22px; }
        .modal-title { font-size:.95rem; font-weight:700; }
        .modal-body { padding:20px 22px; }
        .modal-footer { border-top:1px solid var(--border-light); padding:14px 22px; }
        .nav-tabs { border-bottom:1px solid var(--border); gap:4px; }
        .nav-tabs .nav-link { border:none; border-radius:var(--r-sm) var(--r-sm) 0 0; font-size:.78rem; font-weight:600; color:var(--muted); padding:8px 14px; }
        .nav-tabs .nav-link.active { background:var(--blue); color:#fff; }
        .alert-modal-tbl { width:100%; border-collapse:collapse; font-size:.78rem; }
        .alert-modal-tbl thead th { padding:8px 10px; font-size:.62rem; font-weight:700; text-transform:uppercase; letter-spacing:.08em; color:var(--muted); background:var(--surface-2); border-bottom:1px solid var(--border); }
        .alert-modal-tbl tbody td { padding:9px 10px; border-bottom:1px solid var(--border-light); }
        .alert-modal-tbl tbody tr:last-child td { border-bottom:none; }
    </style>
</head>
<body>
<div class="d-flex">

    <!-- ── Sidebar ── -->
    <div class="sidebar flex-column p-0" id="sidebar">
        <div class="sidebar-logo text-center">
            <img src="images/logo.png" alt="Inventory Logo">
            <h5 class="mt-2 text-white">Inventory System</h5>
        </div>
        <hr class="text-white">
        <ul class="nav flex-column">
            <li class="sidebar-title">Main</li>
            <li class="nav-item mb-2"><a class="nav-link" href="admin_dashboard.php"><i class="bi bi-speedometer2 me-2"></i> Dashboard</a></li>
            <li class="sidebar-title">Management</li>
            <li class="nav-item mb-2"><a class="nav-link active" href="products.php"><i class="bi bi-box-seam me-2"></i> Product Management</a></li>
            <li class="nav-item mb-2"><a class="nav-link" href="categories.php"><i class="bi bi-tags me-2"></i> Categories</a></li>
            <li class="nav-item mb-2"><a class="nav-link" href="sales.php"><i class="bi bi-cart-check me-2"></i> Sales</a></li>
            <li class="nav-item mb-2"><a class="nav-link" href="p_os.php"><i class="bi bi-receipt me-2"></i> Invoice</a></li>
            <li class="nav-item mb-2"><a class="nav-link" href="returns.php"><i class="bi bi-arrow-return-left me-2"></i> Returns</a></li>
            <li class="nav-item mb-2"><a class="nav-link" href="stock_in_batches.php"><i class="bi bi-box-arrow-down me-2"></i> Stock-In Records</a></li>
            <li class="nav-item mb-2"><a class="nav-link" href="stock_out_history.php"><i class="bi bi-box-arrow-up me-2"></i> Stock-Out Records</a></li>
            <li class="nav-item mb-2"><a class="nav-link" href="product_history.php"><i class="bi bi-clock-history me-2"></i> Product History</a></li>
            <li class="nav-item mb-2"><a class="nav-link" href="admin_seasonal_report.php"><i class="bi bi-calendar-range me-2"></i> Seasonal Analysis</a></li>
            <li class="nav-item mb-2"><a class="nav-link" href="reports.php"><i class="bi bi-bar-chart-line me-2"></i> Reports</a></li>
            <li class="nav-item mb-2"><a class="nav-link" href="supplier.php"><i class="bi bi-truck me-2"></i> Suppliers</a></li>
            <li class="sidebar-title">Users</li>
            <li class="nav-item mb-2"><a class="nav-link" href="manageUser.php"><i class="bi bi-people me-2"></i> Manage Users</a></li>
            <li class="sidebar-title">Settings</li>
            <li class="nav-item mb-2"><a class="nav-link" href="settings.php"><i class="bi bi-gear me-2"></i> Settings</a></li>
            <li class="nav-item"><a class="nav-link text-danger" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i> Logout</a></li>
        </ul>
    </div>

    <!-- ── Content ── -->
    <div class="content flex-grow-1">
        <div class="main-wrap">

            <!-- Toast notifications -->
            <div class="toast-stack" id="toastStack">
                <?php if (!empty($_SESSION['success'])): ?>
                    <div class="toast-item success">
                        <i class="bi bi-check-circle-fill toast-icon"></i>
                        <span class="toast-text"><?= e($_SESSION['success']) ?></span>
                        <button class="toast-close" onclick="this.parentElement.remove()"><i class="bi bi-x"></i></button>
                    </div>
                    <?php unset($_SESSION['success']); ?>
                <?php endif; ?>
                <?php if (!empty($_SESSION['error'])): ?>
                    <div class="toast-item error">
                        <i class="bi bi-x-circle-fill toast-icon"></i>
                        <span class="toast-text"><?= e($_SESSION['error']) ?></span>
                        <button class="toast-close" onclick="this.parentElement.remove()"><i class="bi bi-x"></i></button>
                    </div>
                    <?php unset($_SESSION['error']); ?>
                <?php endif; ?>
                <?php if (!empty($_SESSION['warning'])): ?>
                    <div class="toast-item warning">
                        <i class="bi bi-exclamation-triangle-fill toast-icon"></i>
                        <span class="toast-text"><?= e($_SESSION['warning']) ?></span>
                        <button class="toast-close" onclick="this.parentElement.remove()"><i class="bi bi-x"></i></button>
                    </div>
                    <?php unset($_SESSION['warning']); ?>
                <?php endif; ?>
            </div>
            <script>
                window.addEventListener('DOMContentLoaded', () => {
                    document.querySelectorAll('.toast-item').forEach(t => {
                        setTimeout(() => { t.style.opacity='0'; t.style.transform='translateX(24px)'; t.style.transition='all .3s'; setTimeout(()=>t.remove(),300); }, 4000);
                    });
                });
            </script>

            <!-- Low stock banner -->
            <?php if (!empty($lowStockProducts) || !empty($outOfStockProducts)): ?>
            <div class="alert-banner">
                <div class="alert-banner-left">
                    <i class="bi bi-exclamation-triangle-fill alert-banner-icon"></i>
                    <div>
                        <div class="alert-banner-title">Stock Alert</div>
                        <div class="alert-banner-sub">
                            <?= count($outOfStockProducts) ?> out of stock &bull; <?= count($lowStockProducts) ?> low stock
                        </div>
                    </div>
                </div>
                <button type="button" class="btn-alert-view" data-bs-toggle="modal" data-bs-target="#lowStockModal">
                    View Alerts
                </button>
            </div>
            <?php endif; ?>

            <!-- Page header -->
            <div class="page-hdr">
                <div class="page-hdr-left">
                    <div class="page-hdr-icon"><i class="bi bi-box-seam-fill"></i></div>
                    <div>
                        <h4>Product Management</h4>
                        <p>Manage inventory, pricing, and stock levels</p>
                    </div>
                </div>
                <div class="hdr-actions">
                    <button class="btn-hdr green" data-bs-toggle="modal" data-bs-target="#stockInModal">
                        <i class="bi bi-box-arrow-in-down"></i> Stock In
                    </button>
                    <button class="btn-hdr red" data-bs-toggle="modal" data-bs-target="#stockOutModal">
                        <i class="bi bi-box-arrow-up"></i> Stock Out
                    </button>
                    <button class="btn-hdr blue" data-bs-toggle="modal" data-bs-target="#addProductModal">
                        <i class="bi bi-plus-lg"></i> Add Product
                    </button>
                </div>
            </div>

            <!-- Stat cards -->
            <div class="stat-row">
                <div class="stat-card">
                    <div class="stat-lbl"><i class="bi bi-box-seam" style="color:var(--blue)"></i> Total Products</div>
                    <div class="stat-val"><?= number_format($total_products_summary) ?></div>
                    <div class="stat-sub">All SKUs in system</div>
                </div>
                <div class="stat-card g">
                    <div class="stat-lbl"><i class="bi bi-check-circle" style="color:var(--green)"></i> In Stock</div>
                    <div class="stat-val" style="color:var(--green)"><?= number_format($in_stock_count) ?></div>
                    <div class="stat-sub">Products available</div>
                </div>
                <div class="stat-card a">
                    <div class="stat-lbl"><i class="bi bi-exclamation-circle" style="color:var(--amber)"></i> Low Stock</div>
                    <div class="stat-val" style="color:var(--amber)"><?= number_format($low_stock_count) ?></div>
                    <div class="stat-sub">Below reorder level</div>
                </div>
                <div class="stat-card r">
                    <div class="stat-lbl"><i class="bi bi-x-circle" style="color:var(--red)"></i> Out of Stock</div>
                    <div class="stat-val" style="color:var(--red)"><?= number_format($out_of_stock_count) ?></div>
                    <div class="stat-sub">Zero inventory</div>
                </div>
            </div>

            <!-- Filter panel -->
            <div class="panel">
                <div class="panel-header" id="filterPanelBtn">
                    <div class="panel-hl">
                        <i class="bi bi-sliders"></i>
                        Filter Products
                        <?php if ($has_active_filters): ?>
                            <span class="filter-dot"></span>
                            <span class="filter-active-pill">Active</span>
                        <?php endif; ?>
                    </div>
                    <i class="bi bi-chevron-down panel-chevron <?= $has_active_filters ? 'open' : '' ?>" id="filterChevron"></i>
                </div>
                <div class="panel-body <?= $has_active_filters ? 'open' : '' ?>" id="filterPanelBody">
                    <form action="products.php" method="GET" id="productFilterForm">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="f-label">Search</label>
                                <div class="f-search-wrap">
                                    <i class="bi bi-search"></i>
                                    <input type="text" class="f-input" name="q" placeholder="Name, SKU, category, brand, variation…" value="<?= e($_GET['q'] ?? '') ?>">
                                </div>
                            </div>
                            <div class="col-md-3 col-sm-6">
                                <label class="f-label">Category</label>
                                <select class="f-input" name="category_id">
                                    <option value="">All categories</option>
                                    <?php
                                    $categories_query->data_seek(0);
                                    while ($cat = $categories_query->fetch_assoc()): ?>
                                        <option value="<?= (int)$cat['category_id'] ?>" <?= ((string)($_GET['category_id']??'') === (string)$cat['category_id']) ? 'selected' : '' ?>>
                                            <?= e($cat['category_name']) ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-3 col-sm-6">
                                <label class="f-label">Subcategory</label>
                                <select class="f-input" name="subcategory_id">
                                    <option value="">All subcategories</option>
                                    <?php
                                    $subcategories_query->data_seek(0);
                                    while ($sub = $subcategories_query->fetch_assoc()): ?>
                                        <option value="<?= (int)$sub['subcategory_id'] ?>" <?= ((string)($_GET['subcategory_id']??'') === (string)$sub['subcategory_id']) ? 'selected' : '' ?>>
                                            <?= e($sub['subcategory_name']) ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-2 col-sm-6">
                                <label class="f-label">Brand</label>
                                <input type="text" class="f-input" name="brand_filter" value="<?= e($brand_filter) ?>" placeholder="e.g. Makita">
                            </div>
                            <div class="col-md-2 col-sm-6">
                                <label class="f-label">Variation</label>
                                <input type="text" class="f-input" name="variant_filter" value="<?= e($variant_filter) ?>" placeholder="e.g. 10mm">
                            </div>
                            <div class="col-md-2 col-sm-6">
                                <label class="f-label">Stock Status</label>
                                <select class="f-input" name="stock_status">
                                    <option value="">All statuses</option>
                                    <option value="ok" <?= ($stock_status_filter==='ok')?'selected':'' ?>>Sufficient</option>
                                    <option value="warning" <?= ($stock_status_filter==='warning')?'selected':'' ?>>Low</option>
                                    <option value="out" <?= ($stock_status_filter==='out')?'selected':'' ?>>Out of Stock</option>
                                    <option value="expired" <?= ($stock_status_filter==='expired')?'selected':'' ?>>Expired</option>
                                </select>
                            </div>
                        </div>
                        <div class="filter-actions">
                            <button type="submit" class="btn-apply"><i class="bi bi-funnel-fill"></i> Apply</button>
                            <a href="products.php" class="btn-reset"><i class="bi bi-x-circle"></i> Reset</a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Report panel -->
            <div class="panel">
                <div class="panel-header" id="reportPanelBtn">
                    <div class="panel-hl"><i class="bi bi-file-earmark-bar-graph"></i> Generate Report</div>
                    <i class="bi bi-chevron-down panel-chevron" id="reportChevron"></i>
                </div>
                <div class="panel-body" id="reportPanelBody">
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
                        <p style="font-size:.78rem; color:var(--muted); margin:0;">Select columns to include in your export.</p>
                        <div style="display:flex;gap:6px;">
                            <button type="button" class="btn-toggle-sm" onclick="toggleReportFields(true)">Select All</button>
                            <button type="button" class="btn-toggle-sm" onclick="toggleReportFields(false)">Clear All</button>
                        </div>
                    </div>
                    <div class="report-chips">
                        <?php foreach (array_keys($report_fields_map) as $field): $fid = 'rf_' . strtolower(str_replace(' ', '_', $field)); ?>
                        <label class="report-chip">
                            <input class="report-field-checkbox" type="checkbox" id="<?= $fid ?>" value="<?= e($field) ?>" checked>
                            <span class="report-chip-dot"></span>
                            <?= e($field) ?>
                        </label>
                        <?php endforeach; ?>
                    </div>
                    <div class="report-actions">
                        <button type="button" class="btn-report dl" onclick="downloadSelectedProductsReport()">
                            <i class="bi bi-download"></i> Download CSV
                        </button>
                        <button type="button" class="btn-report pr" onclick="printProductsTable()">
                            <i class="bi bi-printer"></i> Print Report
                        </button>
                    </div>
                </div>
            </div>

            <!-- Products table -->
            <div class="table-card">
                <div class="table-card-hdr">
                    <div class="table-card-title"><i class="bi bi-table"></i> Product Inventory</div>
                    <span class="record-pill"><?= number_format($total_filtered_products) ?> product<?= $total_filtered_products !== 1 ? 's' : '' ?></span>
                </div>

                <div style="overflow-x:auto;">
                    <?php if ($result && $result->num_rows > 0): ?>
                    <table class="data-tbl">
                        <thead>
                            <tr>
                                <th>Category / Sub</th>
                                <th>Product</th>
                                <th style="text-align:center;">Stock</th>
                                <th style="text-align:right;">Selling Price</th>
                                <th>Details</th>
                                <th style="text-align:right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $result->fetch_assoc()):
                                $sl = (float)$row['stock'];
                                $rl = (int)($row['reorder_level'] ?? 0);
                                $exp = (int)$row['has_expired_batches'];
                                if ($exp)            { $bc='expired'; $bt='Expired'; }
                                elseif ($sl === 0)   { $bc='out';     $bt='Out of Stock'; }
                                elseif ($rl > 0 && $sl <= $rl) { $bc='low'; $bt='Low Stock'; }
                                else                 { $bc='ok';      $bt='Sufficient'; }
                            ?>
                            <tr
                                data-category="<?= e($row['category_name']??'Unknown') ?>"
                                data-subcategory="<?= e($row['subcategory_name']??'None') ?>"
                                data-product="<?= e($row['product_name']) ?>"
                                data-brand="<?= e($row['brand']??'') ?>"
                                data-variation="<?= e($row['variation']??'') ?>"
                                data-stock="<?= $sl ?> <?= e($row['unit']??'') ?>"
                                data-stock-status="<?= $bt ?>"
                                data-supplier-price="<?= money($row['supplier_price']??0) ?>"
                                data-selling-price="<?= money($row['selling_price']??0) ?>"
                                data-description="<?= e($row['description']??'-') ?>">
                                <td>
                                    <div class="cell-cat"><?= e($row['category_name'] ?? 'Unknown') ?></div>
                                    <div class="cell-subcat"><?= e($row['subcategory_name'] ?? '—') ?></div>
                                </td>
                                <td>
                                    <div class="cell-name"><?= e($row['product_name']) ?></div>
                                    <?php $meta = array_filter([e($row['brand']??''), e($row['variation']??'')]); ?>
                                    <?php if (!empty($meta)): ?>
                                        <div class="cell-meta"><?= implode(' · ', $meta) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align:center;">
                                    <span class="stock-badge <?= $bc ?>">
                                        <?php if ($exp): ?><i class="bi bi-exclamation-triangle-fill"></i><?php endif; ?>
                                        <span class="stock-num"><?= ($sl == (int)$sl) ? (int)$sl : rtrim(rtrim(number_format($sl, 4), '0'), '.') ?> <?= e($row['unit']??'') ?></span>
                                    </span>
                                    <div class="stock-status-lbl"><?= $bt ?></div>
                                </td>
                                <td style="text-align:right;">
                                    <div class="cell-price"><?= money($row['selling_price']??0) ?></div>
                                </td>
                                <td>
                                    <div class="cell-desc" title="<?= e($row['description']??'') ?>"><?= e($row['description'] ?: '—') ?></div>
                                    <div class="cell-sku"><?= e($row['sku']??'—') ?></div>
                                </td>
                                <td style="text-align:right;">
                                    <div class="dropdown">
                                        <button class="tbl-actions-btn dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                            <i class="bi bi-three-dots-vertical"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end">
                                            <li><button class="dropdown-item di-info" data-bs-toggle="modal" data-bs-target="#productDetailsModal" data-product-id="<?= (int)$row['product_id'] ?>" data-product-name="<?= e($row['product_name']) ?>" data-view="details"><i class="bi bi-eye me-2"></i>View Details</button></li>
                                            <li><button class="dropdown-item di-success" data-bs-toggle="modal" data-bs-target="#productDetailsModal" data-product-id="<?= (int)$row['product_id'] ?>" data-product-name="<?= e($row['product_name']) ?>" data-view="stockin"><i class="bi bi-box-arrow-in-down me-2"></i>View Stock In</button></li>
                                            <li><button class="dropdown-item di-danger" data-bs-toggle="modal" data-bs-target="#productDetailsModal" data-product-id="<?= (int)$row['product_id'] ?>" data-product-name="<?= e($row['product_name']) ?>" data-view="stockout"><i class="bi bi-box-arrow-up me-2"></i>View Stock Out</button></li>
                                            <li><button class="dropdown-item di-warning" data-bs-toggle="modal" data-bs-target="#productDetailsModal" data-product-id="<?= (int)$row['product_id'] ?>" data-product-name="<?= e($row['product_name']) ?>" data-view="expired"><i class="bi bi-exclamation-triangle me-2"></i>View Expired</button></li>
                                            <li><hr class="dropdown-divider"></li>
                                            <li><button class="dropdown-item di-warning" data-bs-toggle="modal" data-bs-target="#editProductModal<?= (int)$row['product_id'] ?>"><i class="bi bi-pencil me-2"></i>Edit Product</button></li>
                                            <li>
                                                <form method="POST" onsubmit="return showCustomAlert('This will permanently delete this product. Are you sure?', () => this.submit());">
                                                    <input type="hidden" name="delete_id" value="<?= (int)$row['product_id'] ?>">
                                                    <button type="submit" name="delete_product" class="dropdown-item di-danger"><i class="bi bi-trash me-2"></i>Delete</button>
                                                </form>
                                            </li>
                                        </ul>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-icon"><i class="bi bi-search"></i></div>
                        <h6>No products found</h6>
                        <p>Try adjusting your filters or add a new product.</p>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div class="pager-wrap">
                    <div class="pager-info">
                        Showing <?= min($offset+1,$total_filtered_products) ?>–<?= min($offset+$limit,$total_filtered_products) ?> of <?= number_format($total_filtered_products) ?> &middot; Page <?= $page ?>/<?= $total_pages ?>
                    </div>
                    <div class="pager">
                        <?php if ($page>1): ?><a href="<?= buildPaginationUrl($page-1,$_GET) ?>"><i class="bi bi-chevron-left" style="font-size:.7rem"></i></a><?php else: ?><span class="disabled"><i class="bi bi-chevron-left" style="font-size:.7rem"></i></span><?php endif; ?>
                        <?php $sp=max(1,$page-2); $ep=min($total_pages,$page+2);
                        if($sp>1){echo '<a href="'.buildPaginationUrl(1,$_GET).'">1</a>';if($sp>2)echo '<span class="dots">…</span>';}
                        for($i=$sp;$i<=$ep;$i++) echo '<a href="'.buildPaginationUrl($i,$_GET).'" class="'.($i===$page?'active':'').'">'.$i.'</a>';
                        if($ep<$total_pages){if($ep<$total_pages-1)echo '<span class="dots">…</span>';echo '<a href="'.buildPaginationUrl($total_pages,$_GET).'">'.$total_pages.'</a>';}
                        ?>
                        <?php if ($page<$total_pages): ?><a href="<?= buildPaginationUrl($page+1,$_GET) ?>"><i class="bi bi-chevron-right" style="font-size:.7rem"></i></a><?php else: ?><span class="disabled"><i class="bi bi-chevron-right" style="font-size:.7rem"></i></span><?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>

        </div>
    </div>
</div>

<?php require 'product_modals.php'; ?>
<?php require 'stock_out_modal.php'; ?>
<?php require 'stock_in_modal.php'; ?>

<!-- STOCK ALERTS MODAL -->
<div class="modal fade" id="lowStockModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <span class="modal-title" style="color:var(--red)"><i class="bi bi-exclamation-triangle-fill me-2"></i>Stock Alerts</span>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <ul class="nav nav-tabs mb-4" role="tablist">
                    <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-out">
                        <i class="bi bi-x-circle me-1"></i>Out of Stock (<?= count($outOfStockProducts) ?>)
                    </button></li>
                    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-low">
                        <i class="bi bi-graph-down me-1"></i>Low Stock (<?= count($lowStockProducts) ?>)
                    </button></li>
                    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-cat">
                        <i class="bi bi-tags me-1"></i>By Category
                    </button></li>
                </ul>
                <div class="tab-content">
                    <div class="tab-pane fade show active" id="tab-out">
                        <?php if (empty($outOfStockProducts)): ?>
                            <div style="text-align:center;padding:32px;color:var(--green);"><i class="bi bi-check-circle-fill" style="font-size:2rem;"></i><p class="mt-2">No out-of-stock items!</p></div>
                        <?php else: ?>
                        <div style="overflow-x:auto;">
                            <table class="alert-modal-tbl">
                                <thead><tr><th>Product</th><th style="text-align:center;">Stock</th><th style="text-align:center;">Reorder Level</th><th style="text-align:center;">Status</th></tr></thead>
                                <tbody>
                                    <?php foreach ($outOfStockProducts as $p): ?>
                                    <tr>
                                        <td>
                                            <div style="font-weight:600;"><?= e($p['product_name']) ?></div>
                                            <div style="font-size:0.75rem; color:var(--muted);">
                                                <?php 
                                                $meta = array_filter([$p['brand'] ?? '', $p['variation'] ?? '', $p['unit'] ?? '']);
                                                echo htmlspecialchars(implode(' · ', $meta));
                                                ?>
                                            </div>
                                        </td>
                                        <td style="text-align:center;"><span class="stock-badge out">0</span></td>
                                        <td style="text-align:center;font-family:var(--mono)"><?= formatQty($p['reorder_level']??0) ?></td>
                                        <td style="text-align:center;"><span class="stock-badge out">Out of Stock</span></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="tab-pane fade" id="tab-low">
                        <?php if (empty($lowStockProducts)): ?>
                            <div style="text-align:center;padding:32px;color:var(--green);"><i class="bi bi-check-circle-fill" style="font-size:2rem;"></i><p class="mt-2">No low-stock items!</p></div>
                        <?php else: ?>
                        <div style="overflow-x:auto;">
                            <table class="alert-modal-tbl">
                                <thead><tr><th>Product</th><th style="text-align:center;">Stock</th><th style="text-align:center;">Reorder Level</th><th style="text-align:center;">Status</th></tr></thead>
                                <tbody>
                                    <?php foreach ($lowStockProducts as $p): ?>
                                    <tr>
                                        <td>
                                            <div style="font-weight:600;"><?= e($p['product_name']) ?></div>
                                            <div style="font-size:0.75rem; color:var(--muted);">
                                                <?php 
                                                $meta = array_filter([$p['brand'] ?? '', $p['variation'] ?? '', $p['unit'] ?? '']);
                                                echo htmlspecialchars(implode(' · ', $meta));
                                                ?>
                                            </div>
                                        </td>
                                        <td style="text-align:center;"><span class="stock-badge low"><?= formatQty($p['stock']) ?></span></td>
                                        <td style="text-align:center;font-family:var(--mono)"><?= formatQty($p['reorder_level']) ?></td>
                                        <td style="text-align:center;"><span class="stock-badge low">Low Stock</span></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="tab-pane fade" id="tab-cat">
                        <div style="overflow-x:auto;">
                            <table class="alert-modal-tbl">
                                <thead><tr><th>Category</th><th style="text-align:center;">Products</th><th style="text-align:center;">Total Stock</th><th style="text-align:center;">Out of Stock</th><th style="text-align:center;">Low Stock</th></tr></thead>
                                <tbody>
                                    <?php foreach ($productsByCategory as $cat): ?>
                                    <tr>
                                        <td style="font-weight:600;"><?= e($cat['category_name']?:'Uncategorized') ?></td>
                                        <td style="text-align:center;font-family:var(--mono)"><?= (int)$cat['total_products'] ?></td>
                                        <td style="text-align:center;font-family:var(--mono)"><?= formatQty($cat['total_stock']??0) ?></td>
                                        <td style="text-align:center;"><?= (int)$cat['out_of_stock_count']>0 ? '<span class="stock-badge out">'.(int)$cat['out_of_stock_count'].'</span>' : '<span style="color:var(--faint)">—</span>' ?></td>
                                        <td style="text-align:center;"><?= (int)$cat['low_stock_count']>0 ? '<span class="stock-badge low">'.(int)$cat['low_stock_count'].'</span>' : '<span style="color:var(--faint)">—</span>' ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-reset" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Panel toggles
function makeToggle(btnId, bodyId, chevId) {
    const btn = document.getElementById(btnId);
    const body = document.getElementById(bodyId);
    const chev = document.getElementById(chevId);
    if (!btn) return;
    btn.addEventListener('click', () => {
        body.classList.toggle('open');
        chev.classList.toggle('open');
    });
}
makeToggle('filterPanelBtn','filterPanelBody','filterChevron');
makeToggle('reportPanelBtn','reportPanelBody','reportChevron');

// Report helpers
function printProductsTable() {
    const fields = Array.from(document.querySelectorAll('.report-field-checkbox:checked')).map(cb=>cb.value);
    if (!fields.length) { alert('Select at least one field.'); return; }
    const p = new URLSearchParams(window.location.search);
    p.delete('page'); p.set('print_all','1'); p.set('report_fields',fields.join(','));
    window.open(window.location.pathname+'?'+p.toString(),'_blank');
}
function toggleReportFields(enabled) {
    document.querySelectorAll('.report-field-checkbox').forEach(cb => cb.checked = enabled);
}
function downloadSelectedProductsReport() {
    const fields = Array.from(document.querySelectorAll('.report-field-checkbox:checked')).map(cb=>cb.value);
    if (!fields.length) { alert('Select at least one field.'); return; }
    const rows = Array.from(document.querySelectorAll('.data-tbl tbody tr[data-product]'));
    if (!rows.length) { alert('No products to export.'); return; }
    const csv = [fields.map(f=>'"'+f.replace(/"/g,'""')+'"').join(',')];
    rows.forEach(r => {
        csv.push(fields.map(f => {
            const k = f.toLowerCase().replace(/ /g,'-');
            return '"'+String(r.dataset[k]??'').replace(/"/g,'""')+'"';
        }).join(','));
    });
    const a = document.createElement('a');
    a.href = 'data:text/csv;charset=utf-8,%EF%BB%BF'+encodeURIComponent(csv.join('\r\n'));
    a.download = 'products-'+new Date().toISOString().slice(0,10)+'.csv';
    document.body.appendChild(a); a.click(); document.body.removeChild(a);
}
</script>
</body>
</html>
</html>