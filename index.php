<?php
$conn = new PDO(
    "mysql:host=localhost;port=3308;dbname=auto_service;charset=utf8",
    "root",
    ""
);
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

/* ----------------------
Statistics
----------------------*/

$statsStmt = $conn->query("
SELECT 
    (SELECT COUNT(*) FROM services) AS providers,
    (SELECT COUNT(*) FROM reviews) AS reviews,
    (SELECT AVG(rating) FROM reviews) AS avg_rating
");
$stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

/* -------------------------
POPULAR SERVICES
------------------------- */
$popularStmt = $conn->query("
SELECT 
    MIN(s.id) AS id,
    st.name AS service_name,
    COUNT(*) AS popularity,

    MIN(so.min_price) AS min_price,
    MAX(so.max_price) AS max_price,

    MIN(s.image) AS image,
    MIN(s.business_name) AS business_name

FROM service_offers so

JOIN service_types st 
    ON st.id = so.type_id

JOIN services s 
    ON s.id = so.service_id

GROUP BY st.id, st.name

ORDER BY popularity DESC
LIMIT 6
");

$popularServices = $popularStmt->fetchAll(PDO::FETCH_ASSOC);

/* -------------------------
TOP RATED PROVIDERS
------------------------- */
$topStmt = $conn->query("
SELECT 
    s.id,
    s.business_name,
    s.image,
    s.city,
    AVG(r.rating) AS avg_rating,
    COUNT(r.id) AS reviews_count
FROM services s
LEFT JOIN reviews r ON r.service_id = s.id
GROUP BY s.id
ORDER BY avg_rating DESC
LIMIT 6
");

$topProviders = $topStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/main_style.css">
    <title>ServiceAutoFinder</title>
    <style>
        .rating {
            position: relative;
            display: inline-block;
            font-size: 20px;
            line-height: 1;
        }

        .stars-background {
            color: #ccc;
            /* gri */
        }

        .stars-fill {
            color: gold;
            /* culoare stele */
            position: absolute;
            top: 0;
            left: 0;
            white-space: nowrap;
            overflow: hidden;
        }
    </style>
</head>


<body>
    <nav>
        <a href="public/registrate_service.php">Registrate</a>
    </nav>
    <div class="panel">
        <header>
            <h1>Find Trusted Auto Service Near You</h1>
            <p>Compare prices, read reviews, and book auto repair service in your area</p>
        </header>
        <div class="search-bar">
            <form action="api/search.php">
                <img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAADIAAAAyCAYAAAAeP4ixAAAACXBIWXMAAAsTAAALEwEAmpwYAAAChUlEQVR4nO2Zu24TQRSGPxeElgYbEVKYPAIFEC6vgAQO8gUJER4AiYuIkBLogBdAooIUSBGiIDFKHQINl7wBDS2kCiIhsYPRkf6VpjCIxTNj77KfNNLK3v3/Pes9Z86MoaBgJDkEzAALwDqwAexqfAU+6rsrQIUR5CzQBjpA7y+HnbsMnGEEmARWnJvrAq+B68BxoAzs07DjE8ANYE3nJte9Ao4OK4gW8E038h24DxxMcb0F9gDYksYm0CAy887TXATGB9A6Ajx39OaIxD0Z/tRxyZPuNWAvVjAtGZlhLYD+tBNMnYCJneTE7VAmwKyTM9UQBitOToSk5ORMO8Q80VOFmSA846qEPeC0T+G2RK3ExsJKs3ku+Ww7OprA0swTg2LzTFftjR0PzFU9mVXi80bel32ILUjM2o7Y3JT3Ex9i6xKz3ik2U/J+70NsQ2Ix8yOhIu8vPsR2JDZGfPbL+0deAtnMy6v1yWey26IoNqfkbWU4F+X3sQ+xGYnZ8jQ2b+V93td7mrQoXlqFlC1KBzjgS3RZT8YauVg8lKe19N44KdHtIbTxx3yLt50n5Gud3g/TfiGvlyEMqpqYelqOhuKOPGxn8nAok4az+WAbBb656Gw+nCMwc04ws55es5J+iSSIPe3YECuYJGdsk+1fmXBywh1WeptEoO7kzJZKc5p5pqwSu+00ht0+wTSIQNWpZonxmlqLKU2mYxoVfXZLvZN704tK7OYwg0FbNksp/1aw5cGzPs3o74KpE5GyNgqeAh9UQnc0uX0G3gGPdFN/WhY0RiEYX1h57/QJ5hI5CqZFBpnOUzC1/yGYJhkNZrcIJiMFYJ6McsH5Ze6ScWox/84uKAB+ATiuEwljmLOIAAAAAElFTkSuQmCC"
                    alt="search--v1" style="width: 30px; height:30px;">
                <input type="search" id="search_for_service" class="search_for_service"
                    placeholder="Search for service (e.g, oil change, brake repair, ... )">
                <img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAADIAAAAyCAYAAAAeP4ixAAAACXBIWXMAAAsTAAALEwEAmpwYAAADuElEQVR4nO2ZWYiOURjHfzRkmbHPZOJCWYZCiNwoW7JEGtkaV24VuZG9xnZBcodsxYWILJESLiQiDLIklEhqLFnGvoxPR/+3TtN8531f3znffCO/euvr/f7vc/bnOec58J9G6QBUAbuAq8BL4Luel3q3E5gLlFCAVAB7gM9AJuHzCdgN9KUAaAdsBn6ocr/U6yuB0Wpgez0VercKuCZtRqO1CWjTVI0wPXlHlakH9gO9U3zfBzhgNegyUE6eGao5byrwABiWg63hwEPZegYMJo8jETXiDNDZg80uwFmrMeX5WBN3rEYUebTdCjhnTbOga2azNZ18jERjI/NIZawhEBXyTvU5rok4RsgBfAS6hyhgj3rKeKfQHFRZ20JE7E/qqSQutgxYB9xUz5rnBrAWKE3oUH7pu2I8UqUeMsEujplAnSOivwdmJLBzXfo5eGSXjJqIHdeIKMAdUySPIvsY4LgVQCtjbK2WdofHdvwZiYwq5ppO0UgsceiWSvMO6ObQjZXuCh55JaP9HJp11kjEcSKBi+0nzQs88k1GXVvvWwlGrWFvGweQjRJpvuKRLzLqirYfpEniZaJKmqno2kVkdDTwRq2MugJUXYqGdLA8WDZ6SPMcj9yX0YEOzU1pjHeKY5y0NQ7NEGnu4pGTMjrboVkrzfEU9qodmnnSHMUjG2R0vUNTqqmSkYvNxnJp3gJdHbqNCRqbmtkyarbZLmYo2GXkYsdqzRRrOkUjYTTTY2xdlDZOl4oyFf5V3sRFpYJdti3K2wSV66Sdtnk64pkbqsiUBNpuCnY1cst12jtVx0yniFkqy4yKd6LIvZfwHEmw1v6a/pbvj5teudBRAdhsPnuFKqRGjTGuMRQLVcb5gGWwwEoOhKAIeKwyzBkoGMWWRzJna9/MtbYlJqsSlC0q7HDAqbuCPFBuLcZBHu1OsA5cJo7kha0BRsUs7qD5rMboaWVVRnmwV6lGvA6U+HNSbZ3yWuZgp7WVxF5AE9AWeKIKzM/BzhLZuOc5l/xX+a5anfrSUmq584k0IS2AS6qISXCnZZ++Ndv7Jmeors9+AiNTfDfJuktMc9MVlOiYez/hvYbJojzVN4soIFoDtxMchyO2W+eNXDxeEIZoitXHZFImKf6Y0+YACpQ16uknWbYZZUqBGs1iCpgi4IKVxjFeLcJModP671SD/wqSnlbSe2UjR+XaUFdqIZimdWDWy2Rgqn4bFz2eZka1RuCNUkDm9zKaIS2AQ1Y+q+GaaVaU6Mbpsu9LTf5VfgNttDSN+ivn8AAAAABJRU5ErkJggg=="
                    alt="location" style="width: 30px; height: 30px;">
                <input type="text" id="location" class="location" placeholder="Location">
                <button type="submit">Search</button>
            </form>
        </div>
        <section class="stats">
            <div class="stat-card">
                <?= $stats['providers'] ?>+
                <span>Service Providers</span>
            </div>

            <div class="stat-card">
                <?= $stats['reviews'] ?>+
                <span>Happy Customers</span>
            </div>

            <div class="stat-card">
                <?= number_format($stats['avg_rating'], 1) ?>★
                <span>Average Rating</span>
            </div>
        </section>
        <section class="services">
            <div class="section-header">
                <h2>Popular Services</h2>
                <a href="public/view_all.php">View All</a>
            </div>

            <div class="service-grid">
                <?php foreach ($popularServices as $p): ?>
                    <article class="service-card">
                        <a href="public/about_service.php?id=<?= $p['id'] ?>">
                            <img src="public/uploads/<?= htmlspecialchars($p['image']) ?>" alt="Service image">

                            <div class="content">

                                <!-- numele serviciului -->
                                <h3><?= htmlspecialchars($p['service_name']) ?></h3>

                                <!-- exemplu companie -->
                                <small>
                                    by <?= htmlspecialchars($p['business_name']) ?>
                                </small>

                                <p>
                                    <?php
                                    if ($p['min_price'] === null) {
                                        echo "Price on request";
                                    } elseif ($p['min_price'] == $p['max_price']) {
                                        echo $p['min_price'] . " MDL";
                                    } else {
                                        echo $p['min_price'] . " - " . $p['max_price'] . " MDL";
                                    }
                                    ?>
                                </p>

                            </div>
                        </a>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
        <section class="top_Rate">
            <div class="section-header">
                <h2>Top Rated Providers</h2>
            </div>

            <div class="service-grid">
                <?php foreach ($topProviders as $t): ?>
                    <article class="service-card">
                        <a href="public/about_service.php?id=<?= $t['id'] ?>">
                            <img src="public/uploads/<?= htmlspecialchars($t['image']) ?>" alt="service">

                            <div class="content">
                                <h3><?= htmlspecialchars($t['business_name']) ?></h3>

                                <!-- ⭐ RATING -->
                                <div class="rating">
                                    <div class="stars-background">★★★★★</div>
                                    <div class="stars-fill"
                                        style="width: <?= ($t['avg_rating'] / 5) * 100 ?>%;">
                                        ★★★★★
                                    </div>

                                    <strong>
                                        <?= number_format($t['avg_rating'], 1) ?>
                                    </strong>
                                </div>

                                <p>(<?= $t['reviews_count'] ?> reviews)</p>

                                <strong><?= htmlspecialchars($t['city']) ?></strong>
                            </div>
                        </a>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    </div>
    <footer>© Godoroja Ştefan</footer>
</body>

<script>
    document.addEventListener("DOMContentLoaded", () => {

        /* -------------------------
        AUTOCOMPLETE SERVICES
        ------------------------- */

        const services = [
            "oil change",
            "brake repair",
            "engine diagnostic",
            "battery replacement",
            "ac recharge",
            "transmission repair",
            "wheel alignment",
            "tire change"
        ];

        const input = document.getElementById("search_for_service");


        input.addEventListener("input", () => {

            list.innerHTML = "";

            const value = input.value.toLowerCase();

            if (value === "") return;

            services.forEach(service => {

                if (service.includes(value)) {

                    const item = document.createElement("div");

                    item.innerText = service;
                    item.style.padding = "6px";
                    item.style.cursor = "pointer";

                    item.onclick = () => {

                        input.value = service;
                        list.innerHTML = "";

                    };

                    list.appendChild(item);

                }

            });

        });


        /* -------------------------
        AUTO LOCATION
        ------------------------- */

        const locationInput = document.getElementById("location");

        if (navigator.geolocation) {

            navigator.geolocation.getCurrentPosition(pos => {

                locationInput.value = "Your location";

            });

        }


        /* -------------------------
        SAVE RECENT SEARCH
        ------------------------- */

        const form = document.querySelector(".search-bar form");

        form.addEventListener("submit", () => {

            const search = input.value;

            let history = JSON.parse(localStorage.getItem("recentSearch")) || [];

            history.push(search);

            localStorage.setItem("recentSearch", JSON.stringify(history));

        });


        /* -------------------------
        DYNAMIC RATING
        ------------------------- */

        document.querySelectorAll(".rating").forEach(r => {

            const number = parseFloat(r.querySelector("strong").innerText);

            const percent = (number / 5) * 100;

            const fill = r.querySelector(".stars-fill");

            if (fill) {
                fill.style.width = percent + "%";
            }

        });


        /* -------------------------
        SEARCH REDIRECT
        ------------------------- */

        form.addEventListener("submit", (e) => {

            e.preventDefault();

            const service = input.value;
            const location = locationInput.value;

            window.location.href =
                "public/view_all.php?service=" +
                encodeURIComponent(service) +
                "&location=" +
                encodeURIComponent(location);

        });

    });
</script>

</html>