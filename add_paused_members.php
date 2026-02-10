<?php
$host = '127.0.0.1';
$db = 'fasoya';
$user = 'root';
$pass = 'sacko';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db", $user, $pass);
    
    // Mettre 2 premiers membres de la première tontine en 'paused'
    $pdo->exec("UPDATE tontine_members SET status = 'paused' WHERE id IN (1, 2)");
    
    $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM tontine_members WHERE status = 'paused'");
    $count = $stmt->fetch()['cnt'];
    echo "✅ Updated! Paused members now: $count\n";
    
    // Afficher les détails
    $stmt2 = $pdo->query("SELECT id, tontine_id, name, status FROM tontine_members WHERE status = 'paused' LIMIT 5");
    while ($row = $stmt2->fetch(PDO::FETCH_ASSOC)) {
        echo "  - ID {$row['id']}: {$row['name']} (tontine {$row['tontine_id']})\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
