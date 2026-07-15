<?php
$dbHost = 'db';
$dbPort = '5432';
$dbName = 'apex_pos';
$dbUser = 'postgres';
$dbPass = 'root';
$pdo = new PDO("pgsql:host=$dbHost;port=$dbPort;dbname=$dbName", $dbUser, $dbPass);
$stmt = $pdo->prepare("SELECT stock_out_id FROM stock_out_items WHERE product_detail_id = 20492");
$stmt->execute();
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach($results as $row) {
    $id = $row['stock_out_id'];
    $stmt2 = $pdo->prepare("SELECT * FROM stock_outs WHERE id = ?");
    $stmt2->execute([$id]);
    $parent = $stmt2->fetch(PDO::FETCH_ASSOC);
    echo "ID: {$parent['id']} | Cat: {$parent['category']} | Date: {$parent['created_at']} | Receipt: {$parent['receipt_id']} | DeletedAt: {$parent['deleted_at']}\n";
}
