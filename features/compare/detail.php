<?php

session_start();

require '../../config/database.php';
require '../../functions/auth.php';

checkLogin();

$id =
(int)(
    $_GET['id']
    ?? 0
);

if(
    $id <= 0
)
{
    die(
        'ID tidak valid'
    );
}

$query =
mysqli_query(
    $conn,
    "
    SELECT *
    FROM compare_duplicates
    WHERE id='$id'
    LIMIT 1
    "
);

if(
    !$query
    ||
    mysqli_num_rows($query) == 0
)
{
    die(
        'Data tidak ditemukan'
    );
}

$data =
mysqli_fetch_assoc(
    $query
);

$rowData = null;

$rowId =
(int)(
    $data['compare_row_id']
    ?? 0
);

if(
    $rowId > 0
)
{
    $rowQuery =
    mysqli_query(
        $conn,
        "
        SELECT *
        FROM compare_rows
        WHERE id='$rowId'
        LIMIT 1
        "
    );

    if(
        $rowQuery
        &&
        mysqli_num_rows($rowQuery) > 0
    )
    {
        $rowData =
        mysqli_fetch_assoc(
            $rowQuery
        );
    }
}

if(
    !$rowData
)
{
    $batchId =
    mysqli_real_escape_string(
        $conn,
        $data['batch_id']
        ?? ''
    );

    $fileBRow =
    (int)(
        $data['file_b_row']
        ?? 0
    );

    if(
        $batchId !== ''
        &&
        $fileBRow > 0
    )
    {
        $rowQuery =
        mysqli_query(
            $conn,
            "
            SELECT *
            FROM compare_rows
            WHERE batch_id='$batchId'
            AND source_row='$fileBRow'
            LIMIT 1
            "
        );

        if(
            $rowQuery
            &&
            mysqli_num_rows($rowQuery) > 0
        )
        {
            $rowData =
            mysqli_fetch_assoc(
                $rowQuery
            );
        }
    }
}

if(
    !$rowData
)
{
    die(
        'Row compare tidak ditemukan'
    );
}

$currentRow =
json_decode(
    $rowData['row_json'] ?? '{}',
    true
);

if(
    !is_array(
        $currentRow
    )
)
{
    $currentRow = [];
}

$masterRow =
json_decode(
    $data['file_a_json'] ?? '{}',
    true
);

if(
    !is_array(
        $masterRow
    )
)
{
    $masterRow = [];
}

$targetOriginal =
json_decode(
    $data['file_b_json'] ?? '{}',
    true
);

if(
    !is_array(
        $targetOriginal
    )
)
{
    $targetOriginal = [];
}

$history =
mysqli_query(
    $conn,
    "
    SELECT *
    FROM compare_edits
    WHERE duplicate_id='$id'
    ORDER BY id DESC
    "
);

function isDifferentValue(
    $a,
    $b
): bool
{
    return
    trim(
        (string)$a
    )
    !==
    trim(
        (string)$b
    );
}

function formatRowPreview(
    array $row,
    int $limit = 6
): string
{
    $parts = [];

    foreach(
        array_slice(
            $row,
            0,
            $limit,
            true
        )
        as
        $key
        =>
        $value
    )
    {
        if(
            is_array(
                $value
            )
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
        $parts
    );
}

function compareTypeBadge(
    string $type
): string
{
    if(
        $type === 'duplicate_master'
    )
    {
        return '<span class="badge bg-danger">Duplicate Master</span>';
    }

    if(
        $type === 'duplicate_internal'
    )
    {
        return '<span class="badge bg-warning text-white">Duplicate Internal</span>';

    }

    return '<span class="badge bg-secondary">Unknown</span>';
}

function rowStatusBadge(
    string $status
): string
{
    if(
        $status === 'edited'
    )
    {
        return '<span class="badge bg-success">EDITED</span>';
    }

    if(
        $status === 'deleted'
    )
    {
        return '<span class="badge bg-dark">DELETED</span>';
    }

    if(
        $status === 'duplicate'
    )
    {
        return '<span class="badge bg-warning text-white">DUPLICATE</span>';

    }

    return '<span class="badge bg-secondary">NORMAL</span>';
}

?>

<!DOCTYPE html>
<html>

<head>

<meta charset="utf-8">

<meta
name="viewport"
content="width=device-width, initial-scale=1">

<title>

Detail Compare

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

.card-header{
    background:#111827;
    border-bottom:1px solid #334155;
    color:#ffffff;
    font-weight:600;
}

.table-dark-custom td,
.table-dark-custom th{
    border-color:#334155;
    vertical-align:top;
}

.form-control{
    background:#1e293b;
    color:#ffffff;
    border:1px solid #334155;
}

.form-control[readonly]{
    background:#0f172a;
    color:#ffffff;
}

.form-control:focus{
    background:#1e293b;
    color:#ffffff;
    border-color:#60a5fa;
    box-shadow:none;
}

.preview-box{
    white-space:pre-wrap;
    color:#ffffff;
    font-size:13px;
}

.small-label{
    color:#ffffff;
    font-size:13px;
    margin-bottom:4px;
}

.diff-row{
    background:rgba(245, 158, 11, 0.08);
}

.badge-big{
    font-size:14px;
    padding:8px 12px;
}

</style>

</head>

<body>

<div class="container-fluid py-4">

<div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">

<div>

<h2 class="mb-1">

Detail Compare

</h2>

<div class="text-white">

Informasi lengkap duplicate dan perubahan row target.

</div>

</div>

<div class="d-flex gap-2 flex-wrap">

<a
href="result.php"
class="btn btn-secondary">

Kembali

</a>

<a
href="edit.php?id=<?= (int)$data['id'] ?>"
class="btn btn-warning">

Edit Row

</a>

</div>

</div>

<div class="row g-3">

<div class="col-lg-4">

<div class="card card-dark h-100">

<div class="card-header">

Informasi Duplicate

</div>

<div class="card-body">

<p class="mb-2">

<b>ID :</b>
<?= (int)$data['id'] ?>

</p>

<p class="mb-2">

<b>Type :</b>
<?= compareTypeBadge(
    $data['compare_type'] ?? ''
) ?>

</p>

<p class="mb-2">

<b>Compare Field :</b>
<?= htmlspecialchars(
    $data['duplicate_column'] ?? '-'
) ?>

</p>

<p class="mb-2">

<b>Match Value :</b>
<?= htmlspecialchars(
    $data['duplicate_value'] ?? '-'
) ?>

</p>

<p class="mb-2">

<b>Master Row :</b>
<?= (int)($data['file_a_row'] ?? 0) ?>

</p>

<p class="mb-2">

<b>Target Row :</b>
<?= (int)($data['file_b_row'] ?? 0) ?>

</p>

<p class="mb-2">

<b>Status :</b>
<?= rowStatusBadge(
    $rowData['row_status'] ?? 'normal'
) ?>

</p>

<p class="mb-0">

<b>Compare Row ID :</b>
<?= (int)($data['compare_row_id'] ?? 0) ?>

</p>

</div>

</div>

</div>

<div class="col-lg-8">

<div class="card card-dark h-100">

<div class="card-header">

Riwayat Perubahan

</div>

<div class="card-body">

<?php if(
    $history
    &&
    mysqli_num_rows($history) > 0
): ?>

<div class="table-responsive">

<table class="table table-dark table-bordered table-hover table-dark-custom mb-0">

<thead>

<tr>

<th style="width:70px;">ID</th>
<th>User</th>
<th>Tanggal</th>

</tr>

</thead>

<tbody>

<?php while($edit = mysqli_fetch_assoc($history)): ?>

<tr>

<td><?= (int)$edit['id'] ?></td>

<td><?= htmlspecialchars($edit['edited_by'] ?? '-') ?></td>

<td><?= htmlspecialchars($edit['created_at'] ?? '-') ?></td>

</tr>

<?php endwhile; ?>

</tbody>

</table>

</div>

<?php else: ?>

<div class="alert alert-secondary mb-0">

Belum ada perubahan

</div>

<?php endif; ?>

</div>

</div>

</div>

</div>

<hr class="border-secondary my-4">

<div class="row g-3">

<div class="col-lg-4">

<div class="card card-dark h-100">

<div class="card-header">

Master Data

</div>

<div class="card-body">

<div class="small-label">Preview</div>

<div class="preview-box mb-3">
<?= htmlspecialchars(
    formatRowPreview(
        $masterRow,
        6
    )
) ?>
</div>

<pre class="preview-box mb-0"><?= htmlspecialchars(
    json_encode(
        $masterRow,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
    )
) ?></pre>

</div>

</div>

</div>

<div class="col-lg-4">

<div class="card card-dark h-100">

<div class="card-header">

Target Data Awal

</div>

<div class="card-body">

<div class="small-label">Preview</div>

<div class="preview-box mb-3">
<?= htmlspecialchars(
    formatRowPreview(
        $targetOriginal,
        6
    )
) ?>
</div>

<pre class="preview-box mb-0"><?= htmlspecialchars(
    json_encode(
        $targetOriginal,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
    )
) ?></pre>

</div>

</div>

</div>

<div class="col-lg-4">

<div class="card card-dark h-100">

<div class="card-header">

Target Data Saat Ini

</div>

<div class="card-body">

<div class="small-label">Preview</div>

<div class="preview-box mb-3">
<?= htmlspecialchars(
    formatRowPreview(
        $currentRow,
        6
    )
) ?>
</div>

<pre class="preview-box mb-0"><?= htmlspecialchars(
    json_encode(
        $currentRow,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
    )
) ?></pre>

</div>

</div>

</div>

</div>

<hr class="border-secondary my-4">

<div class="card card-dark">

<div class="card-header">

Perbandingan Field

</div>

<div class="card-body">

<div class="table-responsive">

<table class="table table-dark table-bordered table-hover table-dark-custom mb-0">

<thead>

<tr>

<th>Field</th>
<th>Master</th>
<th>Target Awal</th>
<th>Target Saat Ini</th>
<th>Status</th>

</tr>

</thead>

<tbody>

<?php
$allFields = array_unique(
    array_merge(
        array_keys($masterRow),
        array_keys($targetOriginal),
        array_keys($currentRow)
    )
);
?>

<?php foreach($allFields as $field): ?>

<?php
$masterValue =
$masterRow[$field] ?? '';

$targetOriginalValue =
$targetOriginal[$field] ?? '';

$currentValue =
$currentRow[$field] ?? '';

$isDiff =
isDifferentValue(
    $masterValue,
    $currentValue
);
?>

<tr class="<?= $isDiff ? 'diff-row' : '' ?>">

<td class="fw-semibold">
<?= htmlspecialchars($field) ?>
</td>

<td><?= htmlspecialchars((string)$masterValue) ?></td>

<td><?= htmlspecialchars((string)$targetOriginalValue) ?></td>

<td><?= htmlspecialchars((string)$currentValue) ?></td>

<td>
<?php if($isDiff): ?>
<span class="badge bg-warning text-white">DIFFERENT</span>
<?php else: ?>
<span class="badge bg-success">MATCH</span>
<?php endif; ?>
</td>

</tr>

<?php endforeach; ?>

</tbody>

</table>

</div>

</div>

</div>

</div>

</body>
</html>