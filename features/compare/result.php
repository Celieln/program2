<?php

session_start();

require '../../config/database.php';
require '../../functions/auth.php';

checkLogin();

if(
    !isset($_SESSION['compare_batch_id'])
)
{
    header(
        'Location:upload_a.php'
    );
    exit;
}

$batchId =
mysqli_real_escape_string(
    $conn,
    $_SESSION['compare_batch_id']
);

$jobQuery =
mysqli_query(
    $conn,
    "
    SELECT *
    FROM compare_jobs
    WHERE batch_id='$batchId'
    LIMIT 1
    "
);

if(
    !$jobQuery
    ||
    mysqli_num_rows($jobQuery) == 0
)
{
    die(
        'Data compare job tidak ditemukan'
    );
}

$job =
mysqli_fetch_assoc(
    $jobQuery
);

$compareMode =
$_SESSION['compare_mode']
?? 'exact';

$compareSimilarity =
(int)(
    $_SESSION['compare_similarity']
    ?? 90
);

$compareKeys =
$_SESSION['compare_keys']
?? [];

$compareOptions =
$_SESSION['compare_options']
?? [];

function countRowsBySql(
    mysqli $conn,
    string $sql
): int
{
    $q = mysqli_query($conn, $sql);

    if(
        !$q
    )
    {
        return 0;
    }

    $row = mysqli_fetch_row($q);

    return
    (int)(
        $row[0] ?? 0
    );
}

function resultCountByType(
    mysqli $conn,
    string $batchId,
    string $type
): int
{
    $batchIdEsc =
    mysqli_real_escape_string(
        $conn,
        $batchId
    );

    $typeEsc =
    mysqli_real_escape_string(
        $conn,
        $type
    );

    return countRowsBySql(
        $conn,
        "
        SELECT COUNT(*)
        FROM compare_duplicates
        WHERE batch_id='$batchIdEsc'
        AND compare_type='$typeEsc'
        "
    );
}

$totalRows =
countRowsBySql(
    $conn,
    "
    SELECT COUNT(*)
    FROM compare_rows
    WHERE batch_id='$batchId'
    "
);

$totalSafe =
countRowsBySql(
    $conn,
    "
    SELECT COUNT(*)
    FROM compare_rows
    WHERE batch_id='$batchId'
    AND row_status='normal'
    "
);

$totalDuplicateInternal =
resultCountByType(
    $conn,
    $batchId,
    'duplicate_internal'
);

$totalNikMismatch =
resultCountByType(
    $conn,
    $batchId,
    'nik_mismatch'
);

$totalNameMismatch =
resultCountByType(
    $conn,
    $batchId,
    'name_mismatch'
);

$totalNotFound =
resultCountByType(
    $conn,
    $batchId,
    'not_found'
);

$totalDuplicate =
$totalDuplicateInternal
+
$totalNikMismatch
+
$totalNameMismatch
+
$totalNotFound;

$totalEdited =
countRowsBySql(
    $conn,
    "
    SELECT COUNT(*)
    FROM compare_rows
    WHERE batch_id='$batchId'
    AND row_status='edited'
    "
);

$totalDeleted =
countRowsBySql(
    $conn,
    "
    SELECT COUNT(*)
    FROM compare_rows
    WHERE batch_id='$batchId'
    AND row_status='deleted'
    "
);

$query =
mysqli_query(
    $conn,
    "
    SELECT
        d.*,
        r.row_status,
        r.row_json AS current_row_json
    FROM compare_duplicates d
    LEFT JOIN compare_rows r
    ON d.compare_row_id = r.id
    WHERE d.batch_id='$batchId'
    AND d.compare_type IN (
        'duplicate_internal',
        'nik_mismatch',
        'name_mismatch',
        'not_found'
    )
    ORDER BY d.id DESC
    "
);

if(
    !$query
)
{
    die(
        mysqli_error($conn)
    );
}

function formatPreview(
    $json,
    int $limit = 5
): string
{
    if(
        is_string($json)
    )
    {
        $decoded =
        json_decode(
            $json,
            true
        );

        if(
            is_array($decoded)
        )
        {
            $json = $decoded;
        }
    }

    if(
        !is_array($json)
    )
    {
        return '';
    }

    $isAssoc =
    array_keys($json)
    !==
    range(
        0,
        count($json) - 1
    );

    if(
        $isAssoc
    )
    {
        $parts = [];

        foreach(
            $json as $key => $value
        )
        {
            if(
                is_array($value)
            )
            {
                $value =
                json_encode(
                    $value,
                    JSON_UNESCAPED_UNICODE
                );
            }

            $parts[] =
            $key
            .
            ' : '
            .
            $value;
        }

        return
        implode(
            ' | ',
            array_slice(
                $parts,
                0,
                $limit
            )
        );
    }

    $parts = [];

    foreach(
        array_slice(
            $json,
            0,
            $limit
        ) as $value
    )
    {
        if(
            is_array($value)
        )
        {
            $value =
            json_encode(
                $value,
                JSON_UNESCAPED_UNICODE
            );
        }

        $parts[] = $value;
    }

    return
    implode(
        ' | ',
        $parts
    );
}

function badgeCompareType(
    ?string $type
): string
{
    return match($type) {
        'duplicate_internal' =>
            '<span class="badge bg-warning text-dark">Duplicate Internal</span>',
        'nik_mismatch' =>
            '<span class="badge bg-info text-dark">NIK Mismatch</span>',
        'name_mismatch' =>
            '<span class="badge bg-primary">Name Mismatch</span>',
        'not_found' =>
            '<span class="badge bg-secondary">Not Found</span>',
        default =>
            '<span class="badge bg-light text-dark">Unknown</span>',
    };
}

function badgeRowStatus(
    ?string $status
): string
{
    return match($status) {
        'edited' =>
            '<span class="badge bg-success">EDITED</span>',
        'deleted' =>
            '<span class="badge bg-dark">DELETED</span>',
        'duplicate' =>
            '<span class="badge bg-warning text-dark">DUPLICATE</span>',
        default =>
            '<span class="badge bg-secondary">NORMAL</span>',
    };
}

function humanCompareMode(
    string $mode
): string
{
    return match($mode) {
        'similar' => 'SIMILAR',
        default => 'EXACT',
    };
}

function optionLabel(
    string $key
): string
{
    return match($key) {
        'duplicate_internal' => 'Duplicate Internal File B',
        'compare_master' => 'Compare Dengan Master',
        'nik_mismatch' => 'Nama Sama NIK Berbeda',
        'name_mismatch' => 'NIK Sama Nama Berbeda',
        'not_found' => 'Data Tidak Ada Di Master',
        default => $key,
    };
}

function compareTypeLabel(
    ?string $type
): string
{
    return match($type) {
        'duplicate_internal' => 'Duplicate Internal',
        'nik_mismatch' => 'NIK Mismatch',
        'name_mismatch' => 'Name Mismatch',
        'not_found' => 'Not Found',
        default => 'Unknown',
    };
}

function rowPreviewArray(
    ?string $json
): array
{
    $arr =
    json_decode(
        $json ?? '{}',
        true
    );

    return
    is_array($arr)
    ? $arr
    : [];
}

?>

<!DOCTYPE html>
<html>

<head>

<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title>
Compare Result
</title>

<link
href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
rel="stylesheet">

<style>

body{
    background:#0f172a;
    color:#ffffff;
}

body, body * {
    color: #ffffff !important;
}

.card-dark{
    background:#111827;
    border:1px solid #1f2937;
    border-radius:16px;
}

.stat-card{
    transition:.25s;
}

.stat-card:hover{
    transform:translateY(-3px);
}

.summary-number{
    font-size:32px;
    font-weight:700;
    line-height:1.1;
}

.preview-box{
    font-size:12px;
    line-height:1.4;
    white-space:normal;
}

.table-dark-custom td,
.table-dark-custom th{
    border-color:#334155;
    vertical-align:top;
}

.subtle{
    color:#ffffff !important;
}

.badge-wrap .badge{
    margin-right:.35rem;
    margin-bottom:.35rem;
}

.small-caption{
    font-size:12px;
    color:#ffffff !important;
}

</style>

</head>

<body>

<div class="container-fluid py-4">

<div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">

<div>

<h2 class="mb-1">

Compare Result

</h2>

<div class="subtle">

Analisa duplicate internal, mismatch, dan data not found untuk file target.

</div>

</div>

<div class="d-flex gap-2 flex-wrap">

<a
href="export.php?batch_id=<?= urlencode($batchId) ?>"
class="btn btn-success">

Export Clean File

</a>

<a
href="upload_a.php"
class="btn btn-primary">

Compare Lagi

</a>

<a
href="../../dashboard/index.php"
class="btn btn-secondary">

Dashboard

</a>

</div>

</div>

<div class="card card-dark mb-4">

<div class="card-body">

<div class="row g-3">

<div class="col-lg-4">

<div class="p-3 rounded-3" style="background:#0f172a;border:1px solid #334155;">
<div class="subtle mb-1">Master File</div>
<div class="fw-semibold"><?= htmlspecialchars($job['file_a']) ?></div>
</div>

</div>

<div class="col-lg-4">

<div class="p-3 rounded-3" style="background:#0f172a;border:1px solid #334155;">
<div class="subtle mb-1">Target File</div>
<div class="fw-semibold"><?= htmlspecialchars($job['file_b']) ?></div>
</div>

</div>

<div class="col-lg-4">

<div class="p-3 rounded-3" style="background:#0f172a;border:1px solid #334155;">
<div class="subtle mb-1">Batch ID</div>
<div class="fw-semibold"><?= htmlspecialchars($batchId) ?></div>
</div>

</div>

</div>

<hr class="border-secondary my-4">

<div class="row g-3">

<div class="col-md-3">
<div class="card card-dark stat-card">
<div class="card-body text-center">
<div class="subtle">Total Row</div>
<div class="summary-number"><?= number_format($totalRows) ?></div>
</div>
</div>
</div>

<div class="col-md-3">
<div class="card card-dark stat-card">
<div class="card-body text-center">
<div class="subtle">Row Aman</div>
<div class="summary-number"><?= number_format($totalSafe) ?></div>
</div>
</div>
</div>

<div class="col-md-3">
<div class="card card-dark stat-card">
<div class="card-body text-center">
<div class="subtle">Duplicate Internal</div>
<div class="summary-number"><?= number_format($totalDuplicateInternal) ?></div>
</div>
</div>
</div>

<div class="col-md-3">
<div class="card card-dark stat-card">
<div class="card-body text-center">
<div class="subtle">Total Temuan</div>
<div class="summary-number"><?= number_format($totalDuplicate) ?></div>
</div>
</div>
</div>

<div class="col-md-3">
<div class="card card-dark stat-card">
<div class="card-body text-center">
<div class="subtle">NIK Mismatch</div>
<div class="summary-number"><?= number_format($totalNikMismatch) ?></div>
</div>
</div>
</div>

<div class="col-md-3">
<div class="card card-dark stat-card">
<div class="card-body text-center">
<div class="subtle">Name Mismatch</div>
<div class="summary-number"><?= number_format($totalNameMismatch) ?></div>
</div>
</div>
</div>

<div class="col-md-3">
<div class="card card-dark stat-card">
<div class="card-body text-center">
<div class="subtle">Not Found</div>
<div class="summary-number"><?= number_format($totalNotFound) ?></div>
</div>
</div>
</div>

<div class="col-md-3">
<div class="card card-dark stat-card">
<div class="card-body text-center">
<div class="subtle">Edited</div>
<div class="summary-number"><?= number_format($totalEdited) ?></div>
</div>
</div>
</div>

<div class="col-md-3">
<div class="card card-dark stat-card">
<div class="card-body text-center">
<div class="subtle">Deleted</div>
<div class="summary-number"><?= number_format($totalDeleted) ?></div>
</div>
</div>
</div>

</div>

</div>

</div>

<div class="card card-dark mb-4">

<div class="card-header">

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2">

<div class="fw-semibold">

Konfigurasi Compare

</div>

</div>

</div>

<div class="card-body">

<div class="row g-3">

<div class="col-lg-4">

<div class="p-3 rounded-3" style="background:#0f172a;border:1px solid #334155;">
<div class="subtle mb-1">Compare Mode</div>
<div class="fw-semibold"><?= htmlspecialchars(humanCompareMode($compareMode)) ?></div>
</div>

</div>

<div class="col-lg-4">

<div class="p-3 rounded-3" style="background:#0f172a;border:1px solid #334155;">
<div class="subtle mb-1">Similarity</div>
<div class="fw-semibold"><?= number_format($compareSimilarity) ?>%</div>
</div>

</div>

<div class="col-lg-4">

<div class="p-3 rounded-3" style="background:#0f172a;border:1px solid #334155;">
<div class="subtle mb-1">Compare Keys</div>
<div class="badge-wrap">
<?php if(count($compareKeys)): ?>
    <?php foreach($compareKeys as $key): ?>
        <span class="badge bg-info text-dark"><?= htmlspecialchars($key) ?></span>
    <?php endforeach; ?>
<?php else: ?>
    <span class="text-secondary">Tidak ada compare key</span>
<?php endif; ?>
</div>
</div>

</div>

</div>

<div class="mt-3 p-3 rounded-3" style="background:#0f172a;border:1px solid #334155;">
<div class="subtle mb-2">Opsi Analisa</div>

<div class="badge-wrap">
<?php if(count($compareOptions)): ?>
    <?php foreach($compareOptions as $opt): ?>
        <span class="badge bg-secondary"><?= htmlspecialchars(optionLabel($opt)) ?></span>
    <?php endforeach; ?>
<?php else: ?>
    <span class="text-secondary">Tidak ada opsi aktif</span>
<?php endif; ?>
</div>

</div>

</div>

</div>

<div class="card card-dark">

<div class="card-header">

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2">

<div class="fw-semibold">

Daftar Temuan

</div>

<div class="subtle">

Duplicate internal, mismatch, dan not found.

</div>

</div>

</div>

<div class="card-body">

<div class="table-responsive">

<table class="table table-dark table-bordered table-hover table-dark-custom align-middle">

<thead>

<tr>

<th style="width:70px;">ID</th>
<th style="width:170px;">Type</th>
<th style="width:220px;">Compare Field</th>
<th>Match Value</th>
<th style="width:220px;">Master Row</th>
<th style="width:220px;">Target Row</th>
<th style="width:120px;">Target Status</th>
<th style="width:160px;">Action</th>

</tr>

</thead>

<tbody>

<?php if(mysqli_num_rows($query) == 0): ?>

<tr>
<td colspan="8" class="text-center text-secondary py-4">
Belum ada duplicate / temuan
</td>
</tr>

<?php endif; ?>

<?php while($row = mysqli_fetch_assoc($query)): ?>

<?php
$masterArr =
rowPreviewArray(
    $row['file_a_json'] ?? '{}'
);

$targetCurrentArr =
rowPreviewArray(
    $row['current_row_json'] ?? $row['file_b_json'] ?? '{}'
);

$previewA =
formatPreview(
    $masterArr
);

$previewB =
formatPreview(
    $targetCurrentArr
);
?>

<tr>

<td>
<?= (int)$row['id'] ?>
</td>

<td>
<?= badgeCompareType(
    $row['compare_type'] ?? null
) ?>
</td>

<td>
<?= htmlspecialchars(
    $row['duplicate_column'] ?? '-'
) ?>
</td>

<td>
<div class="fw-semibold">
<?= htmlspecialchars(
    $row['duplicate_value'] ?? '-'
) ?>
</div>
<div class="small-caption mt-1">
<?= htmlspecialchars(
    compareTypeLabel($row['compare_type'] ?? null)
) ?>
</div>
</td>

<td>
<div>
Row <?= (int)($row['file_a_row'] ?? 0) ?>
</div>
<div class="preview-box mt-1">
<?= htmlspecialchars($previewA) ?>
</div>
</td>

<td>
<div>
Row <?= (int)($row['file_b_row'] ?? 0) ?>
</div>
<div class="preview-box mt-1">
<?= htmlspecialchars($previewB) ?>
</div>
</td>

<td>
<?= badgeRowStatus(
    $row['row_status'] ?? 'normal'
) ?>
</td>

<td>
<div class="d-flex gap-2 flex-wrap">

<a
href="detail.php?id=<?= (int)$row['id'] ?>"
class="btn btn-info btn-sm">

Detail

</a>

<a
href="edit.php?id=<?= (int)$row['id'] ?>"
class="btn btn-warning btn-sm">

Edit

</a>

<a
href="delete.php?id=<?= (int)$row['id'] ?>"
class="btn btn-danger btn-sm"
onclick="return confirm('Hapus row ini dari hasil target?')">

Delete

</a>

</div>
</td>

</tr>

<?php endwhile; ?>

</tbody>

</table>

</div>

</div>

</div>

</div>

</body>
</html>