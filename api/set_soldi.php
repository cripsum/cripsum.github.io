<?php  
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

session_start();
$user_id = $_SESSION['user_id'] ?? 0;
$achievement_id = $_GET['achievement_id'] ?? 0;

$stmt2 = $mysqli->prepare("SELECT punti FROM achievement WHERE id = ?");
$stmt2->bind_param("i", $achievement_id);

if ($stmt2->execute()) {
    $result2 = $stmt2->get_result();
    if ($row2 = $result2->fetch_assoc()) {
        $punti = (int)$row2['punti'];
        $stmt3 = $mysqli->prepare("UPDATE utenti SET soldi = soldi + ? WHERE id = ?");
        $stmt3->bind_param("ii", $punti, $user_id);
        $stmt3->execute();
        $stmt3->close();
    }
}

header('Content-Type: application/json');
echo json_encode($response);

?>