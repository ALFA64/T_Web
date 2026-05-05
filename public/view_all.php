<?php
$conn = new PDO(
    "mysql:host=localhost;port=3308;dbname=auto_service;charset=utf8",
    "root",
    ""
);
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// --------------------
// GET PARAMETERS (pentru prefill)
// --------------------
$service = $_GET['service'] ?? '';
$location = $_GET['location'] ?? '';
$category = $_GET['category'] ?? '';

// --------------------
// DROPDOWNS
// --------------------
$types = $conn->query("SELECT id, name FROM service_types ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$cities = $conn->query("SELECT DISTINCT city FROM services ORDER BY city")->fetchAll(PDO::FETCH_COLUMN);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="../css/view_style.css">
    <title>Auto Services</title>

    <style>
        .category {
            display: block;
            max-width: 100%;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
    </style>
</head>

<body>

<div class="header_panel">
<header class="top-header">

    <div class="header-top">
        <h1>Auto Services</h1>
        <a class="home-btn" href="../index.php">Back to Home</a>
    </div>

    <p class="services-count">Loading...</p>

    <!-- 🔍 SEARCH (DOAR id adăugat) -->
    <form method="GET" class="search-container" id="searchForm">

        <input type="text" name="service"
            value="<?= htmlspecialchars($service) ?>"
            placeholder="Search company or category...">

        <!-- CATEGORIES -->
        <select name="category">
            <option value="">All Categories</option>
            <?php foreach ($types as $t): ?>
                <option value="<?= htmlspecialchars($t['name']) ?>"
                    <?= $category == $t['name'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($t['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <!-- LOCATIONS -->
        <select name="location">
            <option value="">All Locations</option>
            <?php foreach ($cities as $city): ?>
                <option value="<?= htmlspecialchars($city) ?>"
                    <?= $location == $city ? 'selected' : '' ?>>
                    <?= htmlspecialchars($city) ?>
                </option>
            <?php endforeach; ?>
        </select>

    </form>

</header>
</div>

<div class="main-layout">

<aside class="filters">
    <h3>Filters</h3>

    <label>Sort By</label>
    <select id="sort">
        <option>Name (A-Z)</option>
        <option>Price Low</option>
        <option>Price High</option>
    </select>
</aside>

<!-- 🔥 DOAR id adăugat -->
<section class="services-grid" id="servicesGrid"></section>

</div>

<script>
document.addEventListener("DOMContentLoaded", () => {

    const form = document.getElementById("searchForm");
    const grid = document.getElementById("servicesGrid");
    const sortSelect = document.getElementById("sort");
    const countText = document.querySelector(".services-count");

    let currentData = [];

    /* 🔄 FETCH AJAX */
    function fetchServices() {

        const params = new URLSearchParams(new FormData(form));

        fetch(`../api/search_full_ajax.php?${params}`)
            .then(res => res.json())
            .then(data => {
                currentData = data;
                renderServices(data);
                sortServices();
            })
            .catch(err => console.error(err));
    }

    /* 🎨 RENDER IDENTIC */
    function renderServices(data) {

        grid.innerHTML = "";

        countText.innerText = data.length + " services found";

        if (data.length === 0) {
            grid.innerHTML = "<p style='padding:20px;'>No services found.</p>";
            return;
        }

        data.forEach(s => {

            const article = document.createElement("article");
            article.className = "service-card";

            article.innerHTML = `
                <img src="uploads/${s.image}" alt="service">

                <div class="card-content">
                    <h3>${s.business_name}</h3>

                    <span class="category" title="${s.categories}">
                        ${s.categories || 'General'}
                    </span>

                    <div class="card-footer">

                        <div class="service-info">
                            <span>${s.years} years</span>

                            <span class="price">
                                ${
                                    s.min_price == null && s.max_price == null
                                    ? "Price on request"
                                    : s.min_price == s.max_price
                                    ? s.min_price + " MDL"
                                    : s.min_price + " - " + s.max_price + " MDL"
                                }
                            </span>
                        </div>

                        <a href="about_service.php?id=${s.id}" class="details-btn">
                            View Details
                        </a>

                    </div>
                </div>
            `;

            grid.appendChild(article);
        });
    }

    /* 🔽 SORTARE ORIGINALĂ */
    function getMinPrice(card) {
        const text = card.querySelector(".price").innerText;
        if (text.includes("Price on request")) return Infinity;
        const match = text.match(/\d+/);
        return match ? parseInt(match[0]) : 0;
    }

    function sortServices() {

        const cards = Array.from(document.querySelectorAll(".service-card"));
        const type = sortSelect.value;

        cards.sort((a, b) => {

            if (type.includes("Name")) {
                return a.querySelector("h3").innerText
                    .localeCompare(b.querySelector("h3").innerText);
            }

            if (type.includes("Low")) {
                return getMinPrice(a) - getMinPrice(b);
            }

            if (type.includes("High")) {
                return getMinPrice(b) - getMinPrice(a);
            }
        });

        cards.forEach(card => grid.appendChild(card));
    }

    /* 🔍 EVENIMENTE */
    form.addEventListener("input", fetchServices);
    form.addEventListener("change", fetchServices);
    sortSelect.addEventListener("change", sortServices);

    /* 🚀 LOAD INITIAL */
    fetchServices();

});
</script>

</body>
</html>