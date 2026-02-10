<?php
// Connexion simple à la base sans Laravel
$host = '127.0.0.1';
$db = 'fasoya';
$user = 'root';
$pass = 'sacko';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db", $user, $pass);
    $stmt = $pdo->query("SHOW COLUMNS FROM tontine_members LIKE 'status'");
    $result = $stmt->fetch();
    
    if ($result) {
        echo "✅ Column 'status' EXISTS in tontine_members\n";
        echo "   Type: " . $result['Type'] . "\n";
    } else {
        echo "❌ Column 'status' MISSING in tontine_members\n";
    }
    
    // Vérifier si des paused members existent
    $stmt2 = $pdo->query("SELECT COUNT(*) as cnt FROM tontine_members WHERE status = 'paused'");
    $count = $stmt2->fetch()['cnt'];
    echo "📊 Paused members in DB: $count\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
