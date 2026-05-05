<?php
header('Content-Type: application/json');

$conn = new PDO(
    "mysql:host=localhost;port=3308;dbname=auto_service;charset=utf8",
    "root",
    ""
);
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

function clean($d) {
    return htmlspecialchars(trim($d), ENT_QUOTES, 'UTF-8');
}

try {

    $required = ['bussines_name','manager','email','phone_number','address','city','fyear'];

    foreach ($required as $f) {
        if (empty($_POST[$f])) {
            throw new Exception("Missing: $f");
        }
    }

    if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
        throw new Exception("Invalid email");
    }

    if (empty($_POST['services'])) {
        throw new Exception("Select services");
    }

    $business_name = clean($_POST['bussines_name']);
    $manager = clean($_POST['manager']);
    $email = clean($_POST['email']);
    $phone = clean($_POST['phone_number']);
    $address = clean($_POST['address']);
    $city = clean($_POST['city']);
    $years = (int)$_POST['fyear'];
    $description = clean($_POST['describe-bussiness'] ?? '');

    $services = $_POST['services'];
    $prices = $_POST['price'] ?? [];

    $transport = isset($_POST['transport'])
        ? implode(", ", array_map('clean', $_POST['transport']))
        : '';

    $extra = $_POST['extra_addresses'] ?? [];
    $extra = array_map('clean', $extra);

    $address_string = implode(" | ", array_filter(array_merge([$address], $extra)));

    /* IMAGE */
    $image_name = null;

    if (!empty($_FILES['representative_image']['name'])) {

        $file = $_FILES['representative_image'];

        if ($file['error'] === UPLOAD_ERR_OK) {

            $mime = mime_content_type($file['tmp_name']);
            $allowed = ['image/jpeg','image/png'];

            if (!in_array($mime, $allowed)) {
                throw new Exception("Invalid image");
            }

            if (!is_dir("uploads")) mkdir("uploads", 0777, true);

            $ext = $mime === 'image/png' ? 'png' : 'jpg';
            $image_name = uniqid("img_", true) . "." . $ext;

            move_uploaded_file($file['tmp_name'], "uploads/".$image_name);
        }
    }

    /* INSERT */
    $stmt = $conn->prepare("
        INSERT INTO services
        (business_name, manager, email, phone, address, city, years, description, transport, image)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $business_name,$manager,$email,$phone,
        $address_string,$city,$years,$description,$transport,$image_name
    ]);

    $service_id = $conn->lastInsertId();

    /* OFFERS */
    $stmt = $conn->prepare("
        INSERT INTO service_offers (service_id,type_id,min_price,max_price)
        VALUES (?,?,?,?)
    ");

    foreach ($services as $type_id => $v) {

        $raw = $prices[$type_id] ?? null;

        $min = $max = null;

        if ($raw) {
            if (strpos($raw,'-') !== false) {
                [$a,$b] = explode('-',$raw);
                $min = (int)$a;
                $max = (int)$b;
            } else {
                $min = $max = (int)$raw;
            }
        }

        $stmt->execute([$service_id,$type_id,$min,$max]);
    }

    /* SUCCESS */
    echo json_encode([
        "success" => true,
        "message" => "Service registered successfully!"
    ]);

} catch (Exception $e) {

    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}