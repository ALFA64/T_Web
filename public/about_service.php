<?php
$conn = new PDO("mysql:host=localhost;port=3308;dbname=auto_service;charset=utf8", "root", "");
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$id = $_GET['id'] ?? 1;

/* ---------------- SERVICE ---------------- */
$stmt = $conn->prepare("SELECT * FROM services WHERE id = ?");
$stmt->execute([$id]);
$service = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$service) {
  die("Service not found");
}

/* ---------------- OFFERS ---------------- */
$stmt = $conn->prepare("
    SELECT st.name, so.min_price, so.max_price
    FROM service_offers so
    JOIN service_types st ON st.id = so.type_id
    WHERE so.service_id = ?
");
$stmt->execute([$id]);
$offers = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ---------------- REVIEWS ---------------- */
$stmt = $conn->prepare("SELECT * FROM reviews WHERE service_id = ? ORDER BY id DESC");
$stmt->execute([$id]);
$reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ---------------- SCHEDULE ---------------- */
$stmt = $conn->prepare("SELECT day, open_time, close_time FROM schedule WHERE service_id = ?");
$stmt->execute([$id]);

$hours = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
  $hours[$row['day']] = $row['open_time'] . " - " . $row['close_time'];
}

/* ---------------- AVG RATING ---------------- */
function getAverageRating($conn, $id)
{
  $stmt = $conn->prepare("SELECT AVG(rating) as avg_rating FROM reviews WHERE service_id = ?");
  $stmt->execute([$id]);
  $row = $stmt->fetch(PDO::FETCH_ASSOC);
  return round($row['avg_rating'] ?? 0, 1);
}

/* ---------------- ADD REVIEW ---------------- */
if (isset($_POST['send'])) {

  $name = htmlspecialchars($_POST['name']);
  $rating = (int)$_POST['rating'];
  $comment = htmlspecialchars($_POST['comment']);

  $stmt = $conn->prepare("
        INSERT INTO reviews (service_id, name, rating, comment)
        VALUES (?, ?, ?, ?)
    ");

  $stmt->execute([$id, $name, $rating, $comment]);

  header("Location: about_service.php?id=$id");
  exit;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($service['business_name']) ?></title>

  <!-- CSS EXTERN -->
  <link rel="stylesheet" href="../css/about_service_style.css?v=2">

  <!-- 🔥 CSS INTERN (GARANTAT FUNCȚIONEAZĂ) -->
  <style>
    .review-form {
      margin-top: 30px;
      padding: 25px;
      border-radius: 16px;
      background: linear-gradient(135deg, #ffffff, #eef2ff);
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
      border: 1px solid #ddd;
    }

    .review-form h3 {
      margin-bottom: 5px;
    }

    .review-form small {
      display: block;
      margin-bottom: 15px;
      color: #666;
    }

    .form-group {
      margin-bottom: 15px;
    }

    .review-form input,
    .review-form textarea {
      width: 95%;
      padding: 12px;
      border-radius: 10px;
      border: 1px solid #ccc;
      font-size: 14px;
    }

    .review-form textarea {
      min-height: 100px;
    }

    .review-form input:focus,
    .review-form textarea:focus {
      border-color: #4a6cff;
      outline: none;
      box-shadow: 0 0 5px rgba(74, 108, 255, 0.3);
    }

    .review-form button {
      width: 100%;
      background: #4a6cff;
      color: white;
      border: none;
      padding: 12px;
      border-radius: 10px;
      cursor: pointer;
      font-weight: bold;
    }

    .review-form button:hover {
      background: #2f4ed8;
    }

    /* ⭐ STAR RATING */
    .star-rating {
      direction: rtl;
      display: flex;
      gap: 5px;
      font-size: 28px;
      margin-bottom: 15px;
    }

    .star-rating input {
      display: none;
    }

    .star-rating label {
      color: #ccc;
      cursor: pointer;
    }

    .star-rating input:checked~label {
      color: gold;
    }

    .star-rating label:hover,
    .star-rating label:hover~label {
      color: gold;
    }

    /* OVERLAY */
    .popup {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      display: none;
      justify-content: center;
      align-items: center;
      background: rgba(0, 0, 0, 0.6);
      backdrop-filter: blur(4px);
      z-index: 1000;
    }

    /* POPUP CARD */
    .popup-content {
      background: white;
      padding: 30px;
      border-radius: 16px;
      width: 350px;
      max-width: 90%;
      box-shadow: 0 20px 50px rgba(0, 0, 0, 0.2);
      animation: fadeIn 0.3s ease;
      position: relative;
    }

    /* CLOSE BUTTON */
    .close {
      position: absolute;
      top: 12px;
      right: 15px;
      font-size: 22px;
      cursor: pointer;
    }

    /* TITLES */
    .popup-content h3 {
      margin-top: 0;
    }

    .popup-content h4 {
      margin: 15px 0 10px;
    }

    /* CONTACT INFO */
    .contact-info {
      background: #f5f7ff;
      padding: 10px;
      border-radius: 10px;
      margin-bottom: 10px;
    }

    .contact-info p {
      margin: 5px 0;
    }

    /* INPUTS */
    .popup-content input,
    .popup-content textarea {
      width: 100%;
      margin-bottom: 10px;
      padding: 10px;
      border-radius: 8px;
      border: 1px solid #ddd;
      font-size: 14px;
    }

    /* FOCUS */
    .popup-content input:focus,
    .popup-content textarea:focus {
      border-color: #4a6cff;
      outline: none;
      box-shadow: 0 0 5px rgba(74, 108, 255, 0.3);
    }

    /* BUTTON */
    #sendMessage {
      width: 100%;
      background: #4a6cff;
      color: white;
      border: none;
      padding: 12px;
      border-radius: 10px;
      cursor: pointer;
      font-weight: bold;
    }

    #sendMessage:hover {
      background: #2f4ed8;
    }

    /* ANIMATION */
    @keyframes fadeIn {
      from {
        transform: translateY(20px);
        opacity: 0;
      }

      to {
        transform: translateY(0);
        opacity: 1;
      }
    }
  </style>

</head>

<script>
  document.addEventListener("DOMContentLoaded", () => {

    const contactBtn = document.querySelector(".btn");

    const popup = document.createElement("div");

    popup.innerHTML = `
    <div id="contactPopup" class="popup">
      <div class="popup-content">

        <span id="closePopup" class="close">&times;</span>

        <h3>Contact Provider</h3>

        <div class="contact-info">
          <p>📞 <b>+373 600 00000</b></p>
          <p>📧 service@email.com</p>
        </div>

        <hr>

        <h4>Send a message</h4>

        <input type="text" id="name" placeholder="Your Name">
        <input type="email" id="email" placeholder="Your Email">
        <textarea id="message" placeholder="Write your message..."></textarea>

        <button id="sendMessage">Send Message</button>

      </div>
    </div>
  `;

    document.body.appendChild(popup);

    /* OPEN */
    contactBtn?.addEventListener("click", (e) => {
      e.preventDefault();
      document.getElementById("contactPopup").style.display = "flex";
    });

    /* CLOSE */
    document.addEventListener("click", (e) => {
      if (e.target.id === "closePopup" || e.target.id === "contactPopup") {
        document.getElementById("contactPopup").style.display = "none";
      }
    });

    /* SEND MESSAGE (DOAR O SINGURĂ DATĂ!) */
    document.addEventListener("click", async (e) => {

      if (e.target.id !== "sendMessage") return;

      const name = document.getElementById("name").value.trim();
      const email = document.getElementById("email").value.trim();
      const message = document.getElementById("message").value.trim();

      if (!name || !email || !message) {
        alert("Please fill all fields!");
        return;
      }

      const data = new FormData();
      data.append("service_id", "<?= $id ?>");
      data.append("name", name);
      data.append("email", email);
      data.append("message", message);

      try {
        const res = await fetch("../api/send_message.php", {
          method: "POST",
          body: data
        });

        const text = await res.text();

        alert("Message sent successfully!");
        document.getElementById("contactPopup").style.display = "none";

      } catch (err) {
        alert("Error sending message!");
      }

    });

    /* OPEN/CLOSED STATUS */
    function checkOpen() {
      const hour = new Date().getHours();
      const status = document.createElement("p");

      status.innerHTML = (hour >= 8 && hour < 18) ?
        "🟢 Open Now" :
        "🔴 Closed";

      status.style.color = (hour >= 8 && hour < 18) ? "green" : "red";

      document.querySelector(".provider-info").appendChild(status);
    }

    checkOpen();

  });
</script>

<body>

  <div class="container">

    <a class="back" href="view_all.php">← Back</a>

    <div class="top">

      <div class="provider-image">
        <img src="uploads/<?= htmlspecialchars($service['image']) ?>" alt="Service image">
      </div>

      <div class="provider-info">

        <h1><?= htmlspecialchars($service['business_name']) ?></h1>

        <div class="rating">
          ⭐ <?= getAverageRating($conn, $id) ?>
        </div>

        <div class="info">
          <p>📍 <?= htmlspecialchars($service['address']) ?></p>
          <p>🏆 <?= htmlspecialchars($service['years']) ?> years experience</p>
        </div>

        <p><?= htmlspecialchars($service['description']) ?></p>



        <a class="btn" href="#">Contact Provider</a>

      </div>

    </div>

    <div class="grid">

      <div class="left">

        <div class="card">
          <h2>About</h2>
          <p><?= htmlspecialchars($service['description']) ?></p>
        </div>

        <div class="card">
          <h2>Services Offered</h2>

          <div class="services">
            <?php foreach ($offers as $o): ?>
              <div class="service">
                <span><?= htmlspecialchars($o['name']) ?></span>

                <b>
                  <?php if ($o['min_price'] === null && $o['max_price'] === null): ?>
                    Price on request
                  <?php elseif ($o['min_price'] == $o['max_price']): ?>
                    <?= $o['min_price'] ?> MDL
                  <?php else: ?>
                    <?= $o['min_price'] ?> - <?= $o['max_price'] ?> MDL
                  <?php endif; ?>
                </b>
              </div>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="reviews-container">

          <h2>Customer Reviews</h2>

          <?php foreach ($reviews as $r): ?>
            <div class="review">

              <div class="review-header">
                <strong><?= htmlspecialchars($r['name']) ?></strong>

                <!-- ⭐ STELE -->
                <span>
                  <?php for ($i = 1; $i <= 5; $i++): ?>
                    <?= $i <= $r['rating'] ? "★" : "☆" ?>
                  <?php endfor; ?>
                </span>
              </div>

              <p class="review-date">
                <?= $r['created_at'] ?>
              </p>

              <p><?= htmlspecialchars($r['comment']) ?></p>

            </div>
          <?php endforeach; ?>

          <!-- FORM -->
          <form method="POST" class="review-form">

            <h3>Add Review</h3>
            <small>Share your experience with this service</small>

            <div class="form-group">
              <input type="text" name="name" placeholder="Your Name" required>
            </div>

            <div class="star-rating">
              <input type="radio" name="rating" value="5" id="star5"><label for="star5">★</label>
              <input type="radio" name="rating" value="4" id="star4"><label for="star4">★</label>
              <input type="radio" name="rating" value="3" id="star3"><label for="star3">★</label>
              <input type="radio" name="rating" value="2" id="star2"><label for="star2">★</label>
              <input type="radio" name="rating" value="1" id="star1" checked><label for="star1">★</label>
            </div>

            <div class="form-group">
              <textarea name="comment" placeholder="Write your experience..." required></textarea>
            </div>

            <button type="submit" name="send">Submit Review</button>

          </form>

        </div>

      </div>

      <div class="right">

        <div class="card">
          <h3>Business Hours</h3>

          <ul class="hours">
            <?php
            $allDays = ["monday", "tuesday", "wednesday", "thursday", "friday", "saturday", "sunday"];

            foreach ($allDays as $day):
            ?>
              <li>
                <span><?= ucfirst($day) ?></span>
                <b>
                  <?= isset($hours[$day])
                    ? $hours[$day]
                    : "Zi liberă" ?>
                </b>
              </li>
            <?php endforeach; ?>
          </ul>

        </div>

        <div class="card">
          <h3>Quick Stats</h3>
          <p>Rating: ⭐ <?= getAverageRating($conn, $id) ?></p>
        </div>

      </div>

    </div>

  </div>

</body>

</html>