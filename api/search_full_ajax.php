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
$category = $_GET['category'] ?? '';

$sql = "
SELECT 
    s.*, 
    GROUP_CONCAT(DISTINCT t.name SEPARATOR ', ') AS categories,
    MIN(o.min_price) AS min_price, 
    MAX(o.max_price) AS max_price
FROM services s
LEFT JOIN service_offers o ON o.service_id = s.id
LEFT JOIN service_types t ON t.id = o.type_id
WHERE 1=1
";

$params = [];

/* 🔍 SEARCH */
if (!empty($service)) {
    $sql .= " AND (
        s.business_name LIKE :search
        OR EXISTS (
            SELECT 1
            FROM service_offers o2
            JOIN service_types t2 ON t2.id = o2.type_id
            WHERE o2.service_id = s.id
            AND t2.name LIKE :search
        )
    )";
    $params[':search'] = "%$service%";
}

/* 📍 LOCATION */
if (!empty($location)) {
    $sql .= " AND s.city = :location";
    $params[':location'] = $location;
}

/* 🏷 CATEGORY */
if (!empty($category)) {
    $sql .= " AND EXISTS (
        SELECT 1 FROM service_offers o2
        JOIN service_types t2 ON t2.id = o2.type_id
        WHERE o2.service_id = s.id
        AND t2.name = :category
    )";
    $params[':category'] = $category;
}

$sql .= " GROUP BY s.id";

$stmt = $conn->prepare($sql);
$stmt->execute($params);

echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));