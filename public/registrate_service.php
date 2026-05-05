<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

$success = "";
$error = "";

try {
    $conn = new PDO(
        "mysql:host=localhost;port=3308;dbname=auto_service;charset=utf8",
        "root",
        ""
    );
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("DB error: " . $e->getMessage());
}

function clean($data)
{
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

$types = $conn->query("SELECT id, name FROM service_types")->fetchAll(PDO::FETCH_ASSOC);

/* ========================= SUBMIT ========================= */
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    try {
        $conn->beginTransaction();

        $required = ['bussines_name', 'manager', 'email', 'phone_number', 'address', 'city', 'fyear'];

        foreach ($required as $f) {
            if (empty($_POST[$f])) {
                throw new Exception("Missing: $f");
            }
        }

        if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Invalid email");
        }

        if (!is_numeric($_POST['fyear']) || $_POST['fyear'] < 0) {
            throw new Exception("Invalid years");
        }

        if (empty($_POST['services'])) {
            throw new Exception("Select at least one service");
        }

        if (!isset($_POST['terms'])) {
            throw new Exception("Accept terms");
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

            if ($file['error'] !== UPLOAD_ERR_OK) {
                throw new Exception("Upload error");
            }

            if ($file['size'] > 10 * 1024 * 1024) {
                throw new Exception("Image too large");
            }

            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $file['tmp_name']);

            $allowed = ['image/jpeg', 'image/png'];

            if (!in_array($mime, $allowed)) {
                throw new Exception("Invalid image type");
            }

            if (!is_dir("uploads")) {
                mkdir("uploads", 0777, true);
            }

            $ext = $mime === 'image/png' ? 'png' : 'jpg';
            $image_name = uniqid("img_", true) . "." . $ext;

            if (!move_uploaded_file($file['tmp_name'], "uploads/" . $image_name)) {
                throw new Exception("Failed to move file");
            }
        }

        /* INSERT SERVICE */
        $stmt = $conn->prepare("
            INSERT INTO services
            (business_name, manager, email, phone, address, city, years, description, transport, image)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $business_name,
            $manager,
            $email,
            $phone,
            $address_string,
            $city,
            $years,
            $description,
            $transport,
            $image_name
        ]);

        $service_id = $conn->lastInsertId();

        /* SERVICES */
        $stmt = $conn->prepare("
            INSERT INTO service_offers (service_id, type_id, min_price, max_price)
            VALUES (?, ?, ?, ?)
        ");

        foreach ($services as $type_id => $val) {

            $rawPrice = $prices[$type_id] ?? null;

            $min = null;
            $max = null;

            if ($rawPrice !== null && $rawPrice !== '') {

                if (strpos($rawPrice, '-') !== false) {
                    [$p1, $p2] = explode('-', $rawPrice);
                    $min = (int)trim($p1);
                    $max = (int)trim($p2);
                } else {
                    $min = (int)$rawPrice;
                    $max = (int)$rawPrice;
                }
            }

            $stmt->execute([$service_id, (int)$type_id, $min, $max]);
        }

        /* SCHEDULE */
        $days = ["monday", "tuesday", "wednesday", "thursday", "friday", "saturday", "sunday"];

        $stmt = $conn->prepare("
            INSERT INTO schedule (service_id, day, open_time, close_time)
            VALUES (?, ?, ?, ?)
        ");

        foreach ($days as $d) {
            $from = $_POST[$d . "_from"] ?? null;
            $to   = $_POST[$d . "_to"] ?? null;

            if ($from && $to) {

                if ($from >= $to) {
                    throw new Exception("Invalid time interval for $d");
                }

                $stmt->execute([$service_id, $d, $from, $to]);
            }
        }

        $conn->commit();

        $success = "✅ Service registered successfully!";
    } catch (Exception $e) {

        if ($conn->inTransaction()) {
            $conn->rollBack();
        }

        $error = "❌ " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/regis_style.css">
    <title>Registrate service</title>
</head>
<script>
    document.addEventListener("DOMContentLoaded", () => {

        const form = document.querySelector("form");

        /* =========================
           VALIDARE
        ========================= */

        function showError(input, message) {
            input.classList.add("error");

            let msg = input.parentNode.querySelector(".error-message");

            if (!msg) {
                msg = document.createElement("div");
                msg.className = "error-message";
                input.parentNode.appendChild(msg);
            }

            msg.textContent = message;
        }

        window.toggleService = function(cb) {
            const box = cb.closest(".service-item").querySelector(".service-extra");

            if (cb.checked) {
                box.style.display = "block";
            } else {
                box.style.display = "none";
                box.querySelector("input").value = "";
            }
        };

        function clearError(input) {
            input.classList.remove("error");
            const msg = input.parentNode.querySelector(".error-message");
            if (msg) msg.remove();
        }

        function validateInput(input) {
            if (!input || input.value.trim() === "") {
                if (input) showError(input, "This field is required");
                return false;
            }
            clearError(input);
            return true;
        }

        function validateEmail(input) {
            const pattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!pattern.test(input.value)) {
                showError(input, "Invalid email");
                return false;
            }
            clearError(input);
            return true;
        }

        function validatePhone(input) {
            const pattern = /^[0-9+\-\s()]{7,20}$/;
            if (!pattern.test(input.value)) {
                showError(input, "Invalid phone");
                return false;
            }
            clearError(input);
            return true;
        }

        /* =========================
           WORK HOURS FIX
        ========================= */
        window.toggleHours = function(cb) {
            const box = cb.closest(".day")?.querySelector(".hours");
            if (!box) return;

            const inputs = box.querySelectorAll("input");

            if (cb.checked) {
                box.style.display = "flex";
            } else {
                box.style.display = "none";
                inputs.forEach(i => i.value = "");
            }
        };

        document.querySelectorAll(".day input[type=checkbox]").forEach(toggleHours);

        /* =========================
           LIVE VALIDATION
        ========================= */
        document.querySelectorAll("input, textarea").forEach(input => {
            input.addEventListener("blur", () => validateInput(input));
        });

        const email = document.getElementById("email");
        const phone = document.getElementById("phone_number");

        email?.addEventListener("blur", () => validateEmail(email));
        phone?.addEventListener("blur", () => validatePhone(phone));

        /* =========================
           ADD EXTRA ADDRESS
        ========================= */
        const addBtn = document.getElementById("addAddressBtn");
        const container = document.getElementById("extra-addresses");

        addBtn?.addEventListener("click", () => {
            const wrapper = document.createElement("div");
            wrapper.classList.add("extra-address"); // 🔥 IMPORTANT FIX

            const input = document.createElement("input");
            input.type = "text";
            input.name = "extra_addresses[]";
            input.placeholder = "Other address";

            const btn = document.createElement("button");
            btn.type = "button";
            btn.textContent = "Remove";
            btn.classList.add("remove-btn"); // optional
            btn.onclick = () => wrapper.remove();

            wrapper.appendChild(input);
            wrapper.appendChild(btn);
            container.appendChild(wrapper);
        });

        /* =========================
           REPREZENTATIV IMAGE (SINGLE)
        ========================= */
        const drop = document.getElementById("drop-zone-representative");
        const input = document.getElementById("fileInputRepresentative");
        const btn = document.getElementById("chooseBtnRepresentative");
        const preview = document.getElementById("previewRepresentative");

        btn?.addEventListener("click", () => input.click());

        input?.addEventListener("change", () => showFile(input.files[0]));

        drop?.addEventListener("dragover", e => {
            e.preventDefault();
            drop.style.border = "2px dashed blue";
        });

        drop?.addEventListener("dragleave", () => drop.style.border = "");

        drop?.addEventListener("drop", e => {
            e.preventDefault();
            drop.style.border = "";
            const file = e.dataTransfer.files[0];
            input.files = e.dataTransfer.files;
            showFile(file);
        });

        function showFile(file) {
            if (!preview) return;
            preview.innerHTML = "";
            if (!file) return;

            const div = document.createElement("div");
            div.textContent = file.name + " (" + (file.size / 1024 / 1024).toFixed(2) + " MB)";
            preview.appendChild(div);
        }

        /* =========================
           MULTIPLE FILES (CERTIFICATES)
        ========================= */
        const drop2 = document.getElementById("drop-zone-certificates");
        const input2 = document.getElementById("fileInput");
        const btn2 = document.getElementById("chooseBtn");
        const preview2 = document.getElementById("preview");

        btn2?.addEventListener("click", () => input2.click());

        input2?.addEventListener("change", () => showFiles(input2.files));

        drop2?.addEventListener("drop", e => {
            e.preventDefault();
            const files = e.dataTransfer.files;
            input2.files = files;
            showFiles(files);
        });

        function showFiles(files) {
            if (!preview2) return;
            preview2.innerHTML = "";

            for (let f of files) {
                const div = document.createElement("div");
                div.textContent = `${f.name} (${(f.size/1024/1024).toFixed(2)} MB)`;
                preview2.appendChild(div);
            }
        }

        /* =========================
           SUBMIT VALIDATION
        ========================= */
        form.addEventListener("submit", e => {

            let valid = true;
            let firstError = null;

            const required = [
                "bussines_name",
                "manager",
                "email",
                "phone_number",
                "address",
                "city",
                "fyear"
            ];

            required.forEach(id => {
                const el = document.getElementById(id);
                if (!validateInput(el)) {
                    valid = false;
                    firstError = firstError || el;
                }
            });

            if (!validateEmail(email)) valid = false;
            if (!validatePhone(phone)) valid = false;

            /* SERVICES FIX */
            const services = document.querySelectorAll('input[name^="services["]');
            const hasService = [...services].some(cb => cb.checked);

            if (!hasService) {
                alert("Select at least one service");
                valid = false;
            }

            /* TERMS */
            const terms = document.getElementById("terms");
            if (!terms.checked) {
                alert("Accept terms");
                valid = false;
            }

            if (!valid) {
                e.preventDefault();
                firstError?.scrollIntoView({
                    behavior: "smooth",
                    block: "center"
                });
            }
        });

    });

    document.querySelector("form").addEventListener("submit", async function(e) {
    e.preventDefault();

    const formData = new FormData(this);

    try {
        const res = await fetch("register_ajax.php", {
            method: "POST",
            body: formData
        });

        const data = await res.json();

        if (data.success) {

            alert(data.message);

            // reset form optional
            this.reset();

        } else {
            alert("Error: " + data.message);
        }

    } catch (err) {
        alert("Server error");
    }
});
</script>
<style>
    /* =========================
   ADD ADDRESS BUTTON
========================= */
    #addAddressBtn {
        background: linear-gradient(135deg, #2563eb, #1e40af);
        color: #fff;
        border: none;
        padding: 10px 14px;
        margin-top: 10px;
        border-radius: 8px;
        font-size: 14px;
        cursor: pointer;
        transition: all 0.2s ease-in-out;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        box-shadow: 0 4px 10px rgba(37, 99, 235, 0.25);
    }

    #addAddressBtn:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 14px rgba(37, 99, 235, 0.35);
    }

    #addAddressBtn:active {
        transform: translateY(0);
        box-shadow: 0 3px 8px rgba(37, 99, 235, 0.2);
    }

    #addAddressBtn::before {
        content: "+";
        font-weight: bold;
        font-size: 16px;
    }

    /* =========================
   EXTRA ADDRESS WRAPPER
========================= */
    .extra-address {
        display: flex;
        gap: 10px;
        margin-top: 10px;
        align-items: center;
        animation: fadeIn 0.2s ease-in-out;
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(-5px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    /* =========================
   INPUT
========================= */
    .extra-address input {
        flex: 1;
        padding: 10px 12px;
        border: 1px solid #d1d5db;
        border-radius: 8px;
        outline: none;
        transition: all 0.2s ease;
    }

    .extra-address input:focus {
        border-color: #2563eb;
        box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.15);
    }

    /* =========================
   REMOVE BUTTON (FIXED & SAFE)
========================= */
    .remove-btn {
        all: unset;
        display: inline-flex;
        align-items: center;
        justify-content: center;

        background: #ef4444;
        color: white;

        padding: 8px 12px;
        border-radius: 8px;
        font-size: 13px;
        cursor: pointer;

        transition: 0.2s ease;
        box-shadow: 0 3px 8px rgba(239, 68, 68, 0.2);
    }

    .remove-btn:hover {
        background: #dc2626;
        transform: translateY(-2px);
    }

    /* Pune în CSS */

    .alert {
        width: 90%;
        max-width: 700px;
        margin: 20px auto;
        padding: 18px 22px;
        border-radius: 12px;
        font-size: 16px;
        font-weight: 600;
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.08);
        animation: slideDown .4s ease;
    }

    .success {
        background: #ecfdf5;
        color: #065f46;
        border-left: 6px solid #10b981;
    }

    .error {
        background: #fef2f2;
        color: #991b1b;
        border-left: 6px solid #ef4444;
    }

    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translateY(-20px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
</style>

<body>
    <!-- Pune imediat după <body> -->

    <?php if ($success): ?>
        <div class="alert success">
            <?= $success ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert error">
            <?= $error ?>
        </div>
    <?php endif; ?>
    <header style="background-color: f3f3f3;">
        <a href="../index.php" style="position: absolute; left: 20px; top: 20px;">Back to Home</a>
        <h1>Submit Your Auto Service Business</h1>
        <p>Join our platform and connerct with customers in your area</p>
    </header>
    <!-- Registration form -->
    <form method="POST" enctype="multipart/form-data">
        <div class="form-group">
            <h2>Business Information</h2>

            <div class="row">
                <div class="field">
                    <label>Business Name *</label>
                    <input type="text" id="bussines_name" name="bussines_name">
                </div>

                <div class="field">
                    <label>Owner/Manager *</label>
                    <input type="text" id="manager" name="manager">
                </div>
            </div>

            <div class="row">
                <div class="field">
                    <label>Email *</label>
                    <input type="email" id="email" name="email">
                </div>

                <div class="field">
                    <label>Phone *</label>
                    <input type="tel" id="phone_number" name="phone_number">
                </div>
            </div>

            <!-- ADDRESS BLOCK -->
            <div class="address-block">
                <h3>Address *</h3>

                <div class="row">
                    <div class="field">
                        <label>Country</label>
                        <input type="text" name="country" placeholder="Moldova">
                    </div>

                    <div class="field">
                        <label>City</label>
                        <input type="text" id="city" name="city" placeholder="Chișinău">
                    </div>
                </div>

                <div class="field">
                    <label>Street</label>
                    <input type="text" id="address" name="address" placeholder="Str. Petricani 6">
                </div>

                <button type="button" id="addAddressBtn">
                    Add another address
                </button>

                <div id="extra-addresses"></div>
            </div>

            <div class="row">
                <div class="field">
                    <label>Years in Business *</label>
                    <input type="number" id="fyear" name="fyear">
                </div>
            </div>

            <div class="field">
                <label>Description</label>
                <input type="text" id="describe-bussiness" name="describe-bussiness">
            </div>
        </div>

        <div>
            <h2>Reprezentativ Image</h2>

            <div id="drop-zone-representative">
                <h3>Upload reprezentativ (optional)</h3>
                <p>JPG, JPEG, PNG (MAX 10 MB)</p>

                <input type="file" id="fileInputRepresentative" name="representative_image" accept="image/*" hidden>

                <button type="button" id="chooseBtnRepresentative">Choose Image</button>

                <div id="previewRepresentative"></div>
            </div>
        </div>
        </div>
        <div class="work-program">
            <h2>Work program *</h2>

            <div class="day">
                <label>
                    <input type="checkbox" onchange="toggleHours(this)">
                    Monday
                </label>

                <div class="hours">
                    <input type="time" name="monday_from">
                    <span>–</span>
                    <input type="time" name="monday_to">
                </div>
            </div>

            <div class="day">
                <label>
                    <input type="checkbox" onchange="toggleHours(this)"> Tuesday
                </label>

                <div class="hours">
                    <input type="time" name="tuesday_from">
                    <span>–</span>
                    <input type="time" name="tuesday_to">
                </div>
            </div>

            <div class="day">
                <label>
                    <input type="checkbox" onchange="toggleHours(this)"> Wednesday
                </label>

                <div class="hours">
                    <input type="time" name="wednesday_from">
                    <span>–</span>
                    <input type="time" name="wednesday_to">
                </div>
            </div>

            <div class="day">
                <label>
                    <input type="checkbox" onchange="toggleHours(this)"> Thursday
                </label>

                <div class="hours">
                    <input type="time" name="thursday_from">
                    <span>–</span>
                    <input type="time" name="thursday_to">
                </div>
            </div>
            <div class="day">
                <label>
                    <input type="checkbox" onchange="toggleHours(this)"> Friday
                </label>

                <div class="hours">
                    <input type="time" name="friday_from">
                    <span>–</span>
                    <input type="time" name="friday_to">
                </div>
            </div>
            <div class="day">
                <label>
                    <input type="checkbox" onchange="toggleHours(this)"> Saturday
                </label>

                <div class="hours">
                    <input type="time" name="saturday_from">
                    <span>–</span>
                    <input type="time" name="saturday_to">
                </div>
            </div>
            <div class="day">
                <label>
                    <input type="checkbox" onchange="toggleHours(this)"> Sunday
                </label>

                <div class="hours">
                    <input type="time" name="sunday_from">
                    <span>–</span>
                    <input type="time" name="sunday_to">
                </div>
            </div>

        </div>
        <div>
            <h2>Services Offered *</h2>
            <p>Select services and set price or interval:</p>

            <?php foreach ($types as $type): ?>
                <div class="service-item">
                    <label>
                        <input type="checkbox"
                            name="services[<?= $type['id'] ?>]"
                            value="1"
                            onchange="toggleService(this)">
                        <?= htmlspecialchars($type['name']) ?>
                    </label>

                    <div class="service-extra" style="display:none;">
                        <input type="text"
                            name="price[<?= $type['id'] ?>]"
                            placeholder="ex: 300 MDL sau 200-400">
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <div>
            <h2>Category of transports</h2>
            <p>Select all the transport categories you can service</p>
            <label><input type="checkbox" name="transport[]" value="Motorcycles">Motorcycles (A1-A)</label><br>
            <label><input type="checkbox" name="transport[]" value="Car">Car (B1-B,BE)</label><br>
            <label><input type="checkbox" name="transport[]" value="Truck">Truck (C1-C,C1E-CE)</label><br>
            <label><input type="checkbox" name="transport[]" value="Minibus">Minibus</label><br>
            <label><input type="checkbox" name="transport[]" value="Bus">Bus</label><br>
            <label><input type="checkbox" name="transport[]" value="Tractor">Tractor</label><br>
            <label><input type="checkbox" name="transport[]" value="Special Transport">Special Transport</label><br>
            <label><input type="checkbox" name="transport[]" value="Other">Other</label>
        </div>
        <div>
            <h2>Certifications</h2>

            <p>
                <input type="checkbox" id="certified">
                My business has ASE or equivalent certifications
            </p>

            <div id="drop-zone-certificates">
                <h3>Upload certification documents (optional)</h3>
                <p>PDF, JPG, PNG up to 10 MB</p>

                <!-- 🔥 INPUT REAL -->
                <input type="file" id="fileInput" name="files[]" multiple hidden>

                <button type="button" id="chooseBtn">Choose Files</button>

                <!-- preview -->
                <div id="preview"></div>
            </div>
        </div>
        <div>
            <h2>Terms and Conditions</h2>
            <div class="legacy">
                <p>By submitting the form, you agree to the following terms:</p>
                <ul>
                    <li>All information provided is accurate and up-to-date</li>
                    <li>You have the authority to register this business</li>
                    <li>You will maintain quality service standards</li>
                    <li>You agree to our platform's terms of service</li>
                    <li>You consent to customer reviews and ratings</li>
                </ul>
                <p>Search Service Auto reserves the right to verify all information and reject application that don't
                    meet our standards.</p>
            </div>
            <label><input type="checkbox" id="terms" name="terms">I agree to the tearms and conditions *</label>
        </div>
        <button type="submit">Register / Send</button>
    </form>

    <footer>© Godoroja Ştefan</footer>
</body>

</html>