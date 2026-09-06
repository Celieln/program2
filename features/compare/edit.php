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
    mysqli_num_rows($query) == 0
)
{
    die(
        'Data tidak ditemukan'
    );
}

$duplicate =
mysqli_fetch_assoc(
    $query
);

$rowData = null;

$rowId =
(int)(
    $duplicate['compare_row_id']
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
        $duplicate['batch_id']
        ?? ''
    );

    $fileBRow =
    (int)(
        $duplicate['file_b_row']
        ?? 0
    );

    if(
        $batchId !== '' &&
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

$rowId =
(int)$rowData['id'];

$json =
json_decode(
    $rowData['row_json'],
    true
);

if(
    !is_array(
        $json
    )
)
{
    die(
        'JSON tidak valid'
    );
}

$masterJson =
json_decode(
    $duplicate['file_a_json']
    ?? '[]',
    true
);

if(
    !is_array(
        $masterJson
    )
)
{
    $masterJson = [];
}

$error = '';

if(
    isset($_POST['save'])
)
{
    $newJson = [];

    foreach(
        $json
        as
        $field
        =>
        $value
    )
    {
        $newJson[$field] =
        trim(
            $_POST['field'][$field]
            ?? ''
        );
    }

    $encodedNewJson =
    json_encode(
        $newJson,
        JSON_UNESCAPED_UNICODE
    );

    $updateRow =
    mysqli_query(
        $conn,
        "
        UPDATE compare_rows
        SET
            row_json='"
            .
            mysqli_real_escape_string(
                $conn,
                $encodedNewJson
            )
            .
            "',
            row_status='edited'
        WHERE id='$rowId'
        "
    );

    if(
        !$updateRow
    )
    {
        $error =
        mysqli_error($conn);
    }
    else
    {
        $updateDuplicate =
        mysqli_query(
            $conn,
            "
            UPDATE compare_duplicates
            SET
                status='fixed',
                edited_value='"
                .
                mysqli_real_escape_string(
                    $conn,
                    $encodedNewJson
                )
                .
                "'
            WHERE id='$id'
            "
        );

        if(
            !$updateDuplicate
        )
        {
            $error =
            mysqli_error($conn);
        }
        else
        {
            $insertEdit =
            mysqli_query(
                $conn,
                "
                INSERT INTO compare_edits
                (
                    duplicate_id,
                    old_value,
                    new_value,
                    edited_by
                )
                VALUES
                (
                    '$id',
                    '"
                    .
                    mysqli_real_escape_string(
                        $conn,
                        json_encode(
                            $json,
                            JSON_UNESCAPED_UNICODE
                        )
                    )
                    .
                    "',
                    '"
                    .
                    mysqli_real_escape_string(
                        $conn,
                        $encodedNewJson
                    )
                    .
                    "',
                    '"
                    .
                    mysqli_real_escape_string(
                        $conn,
                        $_SESSION['username']
                    )
                    .
                    "'
                )
                "
            );

            if(
                !$insertEdit
            )
            {
                $error =
                mysqli_error($conn);
            }
            else
            {
                header(
                    'Location: result.php'
                );
                exit;
            }
        }
    }
}

function isDifferentValue(
    $a,
    $b
)
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

?>

<!DOCTYPE html>
<html>

<head>

<meta charset="utf-8">

<meta
name="viewport"
content="width=device-width, initial-scale=1">

<title>

Edit Data Target

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

.form-control{
    background:#1e293b;
    color:#fff;
    border:1px solid #334155;
}

.form-control:focus{
    background:#1e293b;
    color:#fff;
    border:1px solid #60a5fa;
    box-shadow:none;
}

.form-control[readonly]{
    background:#0f172a;
    color:#ffffff;
}

.border-warning-soft{
    border:2px solid #f59e0b !important;
}

.border-danger-soft{
    border:2px solid #ef4444 !important;
}

.preview-box{
    color:#ffffff;
    white-space:pre-wrap;
}

.badge-soft{
    background:#334155;
    color:#ffffff;
    border:1px solid #475569;
}

.label-title{
    font-size:14px;
    font-weight:600;
    margin-bottom:.4rem;
}

</style>

</head>

<body>

<div class="container-fluid py-4">

<div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">

<div>

<h2 class="mb-1">

Edit Data Target

</h2>

<div class="text-white">

Ubah row asli dari File B, lalu simpan agar hasil export ikut berubah.

</div>

</div>

<div class="d-flex gap-2 flex-wrap">

<a
href="result.php"
class="btn btn-secondary">

Kembali

</a>

<a
href="detail.php?id=<?= (int)$id ?>"
class="btn btn-info">

Detail

</a>

</div>

</div>

<?php if($error !== ''): ?>

<div class="alert alert-danger">

<?= htmlspecialchars($error) ?>

</div>

<?php endif; ?>

<div class="row g-3">

<div class="col-lg-4">

<div class="card card-dark h-100">

<div class="card-header">

Informasi Duplicate

</div>

<div class="card-body">

<p class="mb-2">

<b>ID :</b>
<?= (int)$duplicate['id'] ?>

</p>

<p class="mb-2">

<b>Type :</b>

<?php if(($duplicate['compare_type'] ?? '') === 'duplicate_master'): ?>

<span class="badge bg-danger">Duplicate Master</span>

<?php elseif(($duplicate['compare_type'] ?? '') === 'duplicate_internal'): ?>

<span class="badge bg-warning text-white">Duplicate Internal</span>

<?php else: ?>

<span class="badge badge-soft">Unknown</span>

<?php endif; ?>

</p>

<p class="mb-2">

<b>Compare Field :</b>
<?= htmlspecialchars($duplicate['duplicate_column'] ?? '-') ?>

</p>

<p class="mb-2">

<b>Match Value :</b>
<?= htmlspecialchars($duplicate['duplicate_value'] ?? '-') ?>

</p>

<p class="mb-2">

<b>Master Row :</b>
<?= (int)($duplicate['file_a_row'] ?? 0) ?>

</p>

<p class="mb-2">

<b>Target Row :</b>
<?= (int)($duplicate['file_b_row'] ?? 0) ?>

</p>

<p class="mb-0">

<b>Status :</b>

<?php if(($duplicate['status'] ?? '') === 'fixed'): ?>

<span class="badge bg-success">FIXED</span>

<?php else: ?>

<span class="badge bg-warning text-white">PENDING</span>

<?php endif; ?>

</p>

</div>

</div>

</div>

<div class="col-lg-8">

<div class="card card-dark h-100">

<div class="card-header">

Bandingkan Master vs Target

</div>

<div class="card-body">

<form method="POST">

<div class="row g-3 mb-4">

<div class="col-md-6">

<div class="label-title">
Master Data
</div>

<div class="p-3 rounded-3" style="background:#0f172a;border:1px solid #334155;">

<?php foreach($masterJson as $field => $value): ?>

<?php
$targetValue =
$json[$field]
?? '';

$isDiff =
isDifferentValue(
    $value,
    $targetValue
);
?>

<div class="mb-3">

<label class="form-label d-block mb-1">

<?= htmlspecialchars($field) ?>

</label>

<input
type="text"
class="form-control <?= $isDiff ? 'border-warning-soft' : '' ?>"
value="<?= htmlspecialchars((string)$value) ?>"
readonly>

</div>

<?php endforeach; ?>

</div>

</div>

<div class="col-md-6">

<div class="label-title">
Target Data
</div>

<div class="p-3 rounded-3" style="background:#0f172a;border:1px solid #334155;">

<?php foreach($json as $field => $value): ?>

<?php
$masterValue =
$masterJson[$field]
?? '';

$isDiff =
isDifferentValue(
    $masterValue,
    $value
);
?>

<div class="mb-3">

<label class="form-label d-block mb-1">

<?= htmlspecialchars($field) ?>

</label>

<input
type="text"
name="field[<?= htmlspecialchars($field) ?>]"
class="form-control <?= $isDiff ? 'border-danger-soft' : '' ?>"
value="<?= htmlspecialchars((string)$value) ?>">

</div>

<?php endforeach; ?>

</div>

</div>

</div>

<div class="alert alert-warning">

Anda sedang mengedit row asli dari File Target. Setelah disimpan, row ini akan ikut saat export.

</div>

<div class="d-flex gap-2 flex-wrap">

<button
type="submit"
name="save"
class="btn btn-success">

Simpan Perubahan

</button>

<a
href="result.php"
class="btn btn-secondary">

Batal

</a>

</div>

</form>

</div>

</div>

</div>

</div>

<div class="row g-3 mt-1">

<div class="col-lg-6">

<div class="card card-dark">

<div class="card-header">

Preview Master JSON

</div>

<div class="card-body">

<pre class="preview-box mb-0"><?= htmlspecialchars(
    json_encode(
        $masterJson,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
    )
) ?></pre>

</div>

</div>

</div>

<div class="col-lg-6">

<div class="card card-dark">

<div class="card-header">

Preview Target JSON

</div>

<div class="card-body">

<pre class="preview-box mb-0"><?= htmlspecialchars(
    json_encode(
        $json,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
    )
) ?></pre>

</div>

</div>

</div>

</div>

</div>

</body>
</html>