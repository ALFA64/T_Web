<?php
header('Content-Type: application/json');

$conn = new PDO(
    "mysql:host=localhost;port=3308;dbname=auto_service;charset=utf8",
    "root",
    ""
);
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$service = $_GET['service'] ?? '';
$location = $_GET['location'] ?? '';

$stmt = $conn->prepare("
SELECT 
    s.id,
    s.business_name,
    s.image,
    s.city,
    st.name AS service_name,
    so.min_price,
    so.max_price
FROM services s
JOIN service_offers so ON so.service_id = s.id
JOIN service_types st ON st.id = so.type_id
WHERE st.name LIKE :service
AND s.city LIKE :location
LIMIT 10
");

$stmt->execute([
    ':service' => "%$service%",
    ':location' => "%$location%"
]);

echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));