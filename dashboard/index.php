<?php

session_start();

require '../config/database.php';
require '../functions/auth.php';

// checkLogin(); // bypassed

$totalCompare =
mysqli_fetch_row(
mysqli_query(
$conn,
"
SELECT COUNT(*)
FROM compare_jobs
"
)
)[0];

$totalValidation =
mysqli_fetch_row(
mysqli_query(
$conn,
"
SELECT COUNT(*)
FROM validation_jobs
"
)
)[0];

$lastCompare =
mysqli_query(
$conn,
"
SELECT *
FROM compare_jobs
ORDER BY id DESC
LIMIT 1
"
);

$lastCompare =
mysqli_fetch_assoc(
$lastCompare
);

$lastValidation =
mysqli_query(
$conn,
"
SELECT *
FROM validation_jobs
ORDER BY id DESC
LIMIT 1
"
);

$lastValidation =
mysqli_fetch_assoc(
$lastValidation
);

?>

<!DOCTYPE html>
<html>

<head>

<meta charset="utf-8">

<meta
name="viewport"
content="width=device-width, initial-scale=1">

<title>

Dashboard Program2

</title>

<link
href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
rel="stylesheet">

<style>
        :root {
            color-scheme: dark;
            font-family: "Inter", "Segoe UI", Roboto, Arial, sans-serif;
        }

        * {
            box-sizing: border-box;
        }

        body {
            min-height: 100vh;
            margin: 0;
            background: radial-gradient(circle at top left, rgba(56, 189, 248, 0.16), transparent 28%),
                radial-gradient(circle at top right, rgba(168, 85, 247, 0.14), transparent 24%),
                linear-gradient(180deg, #090c1f 0%, #05060c 100%);
            color: #ffffff;
        }

        body,
        body * {
            color: #ffffff !important;
        }

        body a {
            text-decoration: none;
            color: #ffffff !important;
        }

        .navbar {
            background: rgba(7, 11, 25, 0.94);
            border-bottom: 1px solid rgba(148, 163, 184, 0.12);
            backdrop-filter: blur(14px);
        }

        .navbar-brand {
            letter-spacing: 0.16em;
            font-size: 1.1rem;
        }

        .nav-link {
            color: rgba(226, 232, 240, 0.72) !important;
            transition: color 0.2s ease;
        }

        .nav-link:hover,
        .nav-link.active {
            color: #ffffff !important;
        }

        .hero-card,
        .card-dark {
            background: rgba(10, 16, 38, 0.96);
            border: 1px solid rgba(148, 163, 184, 0.12);
            border-radius: 1.5rem;
            color: #f8fafc;
            box-shadow: 0 24px 70px rgba(0, 0, 0, 0.25);
        }

        .hero-card {
            overflow: hidden;
            position: relative;
        }

        .hero-card::before {
            content: "";
            position: absolute;
            inset: 0;
            background: radial-gradient(circle at top left, rgba(56, 189, 248, 0.18), transparent 34%),
                radial-gradient(circle at bottom right, rgba(34, 197, 94, 0.12), transparent 26%);
            pointer-events: none;
        }

        .hero-card .card-body,
        .card-dark .card-body {
            position: relative;
            z-index: 1;
        }

        .hero-title {
            letter-spacing: 0.04em;
            line-height: 1.05;
        }

        .text-muted,
        .badge-soft,
        .card-header,
        .section-title,
        .dashboard-footer,
        .detail-label,
        .detail-value,
        .lead,
        .nav-link,
        .navbar-brand,
        .badge,
        .btn-outline-light {
            color: #ffffff !important;
        }

        .badge-soft {
            background: rgba(56, 189, 248, 0.16);
            border: 1px solid rgba(56, 189, 248, 0.18);
            backdrop-filter: blur(12px);
        }

        .stat-card {
            transition: transform 0.28s ease, box-shadow 0.28s ease;
        }

        .stat-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 32px 90px rgba(0, 0, 0, 0.28);
        }

        .stat-card .badge {
            font-size: 0.8rem;
            letter-spacing: 0.02em;
        }

        .sidebar-btn {
            padding: 1rem 1.2rem;
            min-height: 110px;
            font-size: 1rem;
            font-weight: 700;
            border-radius: 1.1rem;
            box-shadow: 0 16px 30px rgba(15, 23, 42, 0.2);
        }

        .sidebar-btn.primary {
            background: #2563eb;
            border-color: transparent;
        }

        .sidebar-btn.success {
            background: #22c55e;
            border-color: transparent;
        }

        .section-title {
            font-weight: 700;
            letter-spacing: 0.04em;
        }

        .overview-card {
            min-height: 160px;
        }

        .detail-label {
            color: rgba(226, 232, 240, 0.72);
        }

        .detail-value {
            color: #ffffff;
        }

        .dashboard-footer {
            color: rgba(226, 232, 240, 0.65);
            text-align: center;
            padding-top: 1rem;
            font-size: 0.92rem;
        }

        @media (max-width: 767.98px) {
            .sidebar-btn {
                min-height: auto;
                font-size: 0.95rem;
                padding: 0.95rem 1rem;
            }

            .navbar .navbar-nav {
                text-align: center;
            }

            .navbar .nav-link {
                padding: 0.5rem 0;
            }
        }

    </style>

</head>

<body>
    <nav class="navbar navbar-expand-lg navbar-dark py-3">
        <div class="container-fluid">
            <a class="navbar-brand fw-bold" href="#">PROGRAM2</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain"
                aria-controls="navbarMain" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarMain">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link active" aria-current="page" href="../dashboard/index.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../features/compare/upload_a.php">Compare Data</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../features/validation/master_upload.php">Validation Data</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../features/history/compare.php">History</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../features/settings/index.php">Settings</a>
                    </li>
                </ul>
                <ul class="navbar-nav align-items-center">
                    <li class="nav-item me-3">
                        <span class="nav-link text-white px-0"><?= htmlspecialchars($_SESSION['username']) ?></span>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white px-0" href="../logout.php">Logout</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <main class="container py-5">
        <div class="row g-4 mb-4">
            <div class="col-12">
                <div class="card hero-card p-4 p-md-5">
                    <div class="row align-items-center justify-content-between">
                        <div class="col-lg-8">
                            <span class="badge badge-soft mb-3">Dashboard Utama</span>
                            <h1 class="display-6 fw-bold hero-title">Kelola Compare & Validation dengan Lebih Mudah</h1>
                            <p class="lead text-muted mb-4">Platform Program2 membantu Anda memantau pekerjaan perbandingan dan validasi data dengan tampilan yang rapi, modern, dan intuitif.</p>
                            <div class="d-flex flex-wrap gap-2">
                                <a href="../features/compare/upload_a.php" class="btn btn-primary">Mulai Compare</a>
                                <a href="../features/validation/master_upload.php" class="btn btn-outline-light">Mulai Validation</a>
                            </div>
                        </div>
                        <div class="col-lg-4 text-lg-end">
                            <div class="badge bg-info bg-opacity-20 text-white p-3 rounded-pill">Selamat datang, <?= htmlspecialchars($_SESSION['username']) ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-12 col-md-6 col-xl-4">
                <div class="card card-dark stat-card p-4 overview-card">
                    <div class="d-flex align-items-start justify-content-between mb-4">
                        <div>
                            <p class="mb-1 text-muted">Total Compare Job</p>
                            <h2 class="fw-bold mb-0"><?= number_format($totalCompare) ?></h2>
                        </div>
                        <span class="badge bg-primary rounded-pill py-2 px-3">Compare</span>
                    </div>
                    <p class="mb-0 text-muted">Total batch perbandingan yang telah dibuat, direkam untuk kontrol kualitas dan pelacakan.</p>
                </div>
            </div>
            <div class="col-12 col-md-6 col-xl-4">
                <div class="card card-dark stat-card p-4 overview-card">
                    <div class="d-flex align-items-start justify-content-between mb-4">
                        <div>
                            <p class="mb-1 text-muted">Total Validation Job</p>
                            <h2 class="fw-bold mb-0"><?= number_format($totalValidation) ?></h2>
                        </div>
                        <span class="badge bg-success rounded-pill py-2 px-3">Validation</span>
                    </div>
                    <p class="mb-0 text-muted">Jumlah pekerjaan validasi yang sudah diproses untuk menjaga data tetap akurat.</p>
                </div>
            </div>
            <div class="col-12 col-xl-4">
                <div class="card card-dark p-4 overview-card h-100">
                    <h5 class="section-title mb-3">Quick Action</h5>
                    <div class="d-grid gap-3">
                        <a href="../features/compare/upload_a.php" class="btn btn-primary sidebar-btn">Compare Data</a>
                        <a href="../features/validation/master_upload.php" class="btn btn-success sidebar-btn">Validation Data</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-12 col-lg-6">
                <div class="card card-dark h-100">
                    <div class="card-header">Compare Terakhir</div>
                    <div class="card-body">
                        <?php if($lastCompare): ?>
                            <div class="mb-3">
                                <p class="detail-label mb-1">Batch</p>
                                <p class="detail-value fs-5 fw-semibold mb-0"><?= htmlspecialchars($lastCompare['batch_id']) ?></p>
                            </div>
                            <div class="mb-2">
                                <p class="detail-label mb-1">File A</p>
                                <p class="detail-value mb-0"><?= htmlspecialchars($lastCompare['file_a']) ?></p>
                            </div>
                            <div>
                                <p class="detail-label mb-1">File B</p>
                                <p class="detail-value mb-0"><?= htmlspecialchars($lastCompare['file_b']) ?></p>
                            </div>
                        <?php else: ?>
                            <p class="mb-0 text-muted">Belum ada data compare. Silakan buat pekerjaan perbandingan untuk mulai melacak data terbaru.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-12 col-lg-6">
                <div class="card card-dark h-100">
                    <div class="card-header">Validation Terakhir</div>
                    <div class="card-body">
                        <?php if($lastValidation): ?>
                            <div class="mb-3">
                                <p class="detail-label mb-1">Batch</p>
                                <p class="detail-value fs-5 fw-semibold mb-0"><?= htmlspecialchars($lastValidation['batch_id']) ?></p>
                            </div>
                            <div>
                                <p class="detail-label mb-1">File</p>
                                <p class="detail-value mb-0"><?= htmlspecialchars($lastValidation['file_name']) ?></p>
                            </div>
                        <?php else: ?>
                            <p class="mb-0 text-muted">Belum ada data validation. Unggah file master dan target untuk memulai proses validasi.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="dashboard-footer mt-5">
            &copy; <?= date('Y') ?> Program2. Semua data aman dan antarmuka dioptimalkan untuk pengalaman kerja yang nyaman.
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>