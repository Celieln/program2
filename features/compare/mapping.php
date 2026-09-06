<?php

session_start();

require '../../config/database.php';
require '../../functions/auth.php';
require '../../functions/mapping.php';
require '../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

checkLogin();

if(
    !isset($_SESSION['compare_file_a'])
    ||
    !isset($_SESSION['compare_file_b'])
)
{
    header('Location: upload_a.php');
    exit;
}

$fileA = $_SESSION['compare_file_a'];
$fileB = $_SESSION['compare_file_b'];

function compareGetHeaders(
    string $file
): array
{
    $spreadsheet = IOFactory::load($file);
    $sheet = $spreadsheet->getActiveSheet();

    $highestColumn = $sheet->getHighestColumn();

    $headerRow = $sheet->rangeToArray(
        'A1:' . $highestColumn . '1',
        null,
        true,
        true,
        false
    );

    $headers = $headerRow[0] ?? [];
    $cleanHeaders = [];

    foreach(
        $headers as $header
    )
    {
        $header = trim((string)$header);

        if(
            $header === ''
        )
        {
            continue;
        }

        $cleanHeaders[] = $header;
    }

    $spreadsheet->disconnectWorksheets();
    unset($spreadsheet);

    return $cleanHeaders;
}

$headersA = compareGetHeaders($fileA);
$headersB = compareGetHeaders($fileB);

$autoMappings = [];

foreach(
    $headersB as $headerB
)
{
    $headerB = trim((string)$headerB);

    if(
        $headerB === ''
    )
    {
        continue;
    }

    $autoMappings[$headerB] = autoMapHeader($headerB, $headersA);
}

$error = '';

$formMapping =
$_POST['mapping']
?? (
    $_SESSION['compare_mapping']
    ?? []
);

$formManualMapping =
$_POST['manual_mapping']
?? (
    $_SESSION['compare_manual_mapping']
    ?? []
);

$formCompareKeys =
$_POST['compare_keys']
?? (
    $_SESSION['compare_keys']
    ?? []
);

$formExportColumns =
$_POST['export_columns']
?? (
    $_SESSION['compare_export_columns']
    ?? []
);

if(
    empty($formExportColumns)
)
{
    $formExportColumns = $headersA;
}

$formCompareMode =
$_POST['compare_mode']
?? (
    $_SESSION['compare_mode']
    ?? 'exact'
);

$formSimilarity =
(int)(
    $_POST['similarity']
    ?? (
        $_SESSION['compare_similarity']
        ?? 90
    )
);

$formOptions =
$_POST['options']
?? (
    $_SESSION['compare_options']
    ?? [
        'duplicate_internal',
        'compare_master',
        'nik_mismatch',
        'name_mismatch',
        'not_found'
    ]
);

$formCustomMapping =
trim(
    $_POST['custom_mapping']
    ?? (
        $_SESSION['custom_mapping']
        ?? ''
    )
);

if(
    isset($_POST['save_mapping'])
)
{
    $finalMapping = [];

    foreach(
        $headersB as $headerB
    )
    {
        $headerB = trim((string)$headerB);

        if(
            $headerB === ''
        )
        {
            continue;
        }

        $chosen =
        trim(
            $formMapping[$headerB]
            ?? ''
        );

        $manual =
        trim(
            $formManualMapping[$headerB]
            ?? ''
        );

        if(
            $manual !== ''
        )
        {
            $resolved = '';

            foreach(
                $headersA as $headerA
            )
            {
                if(
                    mb_strtolower(trim((string)$headerA))
                    ===
                    mb_strtolower($manual)
                )
                {
                    $resolved = $headerA;
                    break;
                }
            }

            if(
                $resolved !== ''
            )
            {
                $finalMapping[$headerB] = $resolved;
            }
            elseif(
                $chosen !== ''
            )
            {
                $finalMapping[$headerB] = $chosen;
            }
        }
        elseif(
            $chosen !== ''
        )
        {
            $finalMapping[$headerB] = $chosen;
        }
    }

    if(
        empty($formCompareKeys)
    )
    {
        $error = 'Pilih minimal 1 kolom compare';
    }
    else
    {
        $_SESSION['compare_mapping'] = $finalMapping;
        $_SESSION['compare_manual_mapping'] = $formManualMapping;
        $_SESSION['compare_keys'] = $formCompareKeys;
        $_SESSION['compare_export_columns'] = $formExportColumns;
        $_SESSION['compare_mode'] = $formCompareMode;
        $_SESSION['compare_similarity'] = $formSimilarity;
        $_SESSION['compare_options'] = $formOptions;
        $_SESSION['custom_mapping'] = $formCustomMapping;

        header('Location: process.php');
        exit;
    }
}

function is_checked_value(
    array $values,
    string $needle
): bool
{
    return in_array($needle, $values, true);
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
Mapping Compare
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

.table-dark-custom{
    color:#ffffff;
}

.table-dark-custom td,
.table-dark-custom th{
    border-color:#334155;
    vertical-align:middle;
}

.form-check{
    margin-bottom:.55rem;
}

.badge-soft{
    background:#334155;
    color:#e2e8f0;
    border:1px solid #475569;
}

.small-note{
    color:#cbd5e1;
    font-size:.92rem;
}

.mapping-input{
    min-width: 220px;
}

</style>

</head>

<body>

<datalist id="headersAList">
<?php foreach($headersA as $header): ?>
<option value="<?= htmlspecialchars($header) ?>"></option>
<?php endforeach; ?>
</datalist>

<div class="container-fluid mt-4">

<div class="card card-dark shadow-sm">

<div class="card-header py-3">

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2">

<div>
<h4 class="mb-0">
Mapping Compare Data
</h4>
<div class="small-note">
File A = Master, File B = Target. Semua header dibaca otomatis lalu user tetap bisa override manual.
</div>
</div>

<div class="d-flex gap-2 flex-wrap">
<span class="badge badge-soft px-3 py-2">
STEP 3
</span>
<span class="badge bg-primary px-3 py-2">
Mapping
</span>
<span class="badge bg-success px-3 py-2">
Compare Config
</span>
</div>

</div>

</div>

<div class="card-body">

<?php if($error !== ''): ?>
<div class="alert alert-danger">
<?= htmlspecialchars($error) ?>
</div>
<?php endif; ?>

<div class="alert alert-info">
Periksa mapping, pilih kolom compare, pilih mode compare, lalu lanjutkan proses.
</div>

<div class="row g-3 mb-4">

<div class="col-lg-6">

<div class="card card-dark h-100">

<div class="card-header">
<h6 class="mb-0">
Header File A (Master)
</h6>
</div>

<div class="card-body">

<div class="d-flex flex-wrap gap-2">
<?php foreach($headersA as $header): ?>
<span class="badge bg-success">
<?= htmlspecialchars($header) ?>
</span>
<?php endforeach; ?>
</div>

</div>

</div>

</div>

<div class="col-lg-6">

<div class="card card-dark h-100">

<div class="card-header">
<h6 class="mb-0">
Header File B (Target)
</h6>
</div>

<div class="card-body">

<div class="d-flex flex-wrap gap-2">
<?php foreach($headersB as $header): ?>
<span class="badge bg-warning text-dark">
<?= htmlspecialchars($header) ?>
</span>
<?php endforeach; ?>
</div>

</div>

</div>

</div>

</div>

<form method="POST">

<div class="card card-dark mb-4">

<div class="card-header">
<h6 class="mb-0">
Pemetaan Kolom
</h6>
</div>

<div class="card-body p-0">

<div class="table-responsive">

<table class="table table-dark table-hover table-bordered table-dark-custom mb-0">

<thead>
<tr>
<th width="24%">Header File B</th>
<th width="26%">Auto Mapping</th>
<th width="26%">Manual Override</th>
<th width="12%">Status</th>
<th width="12%">Pakai?</th>
</tr>
</thead>

<tbody>

<?php foreach($headersB as $headerB): ?>

<?php
$headerB = trim((string)$headerB);
if($headerB === ''){ continue; }

$autoMatch =
$autoMappings[$headerB]
?? '';

$savedMapping =
$formMapping[$headerB]
?? $autoMatch;

$savedManual =
$formManualMapping[$headerB]
?? '';
?>

<tr>

<td>
<div class="fw-semibold">
<?= htmlspecialchars($headerB) ?>
</div>
</td>

<td>
<select
name="mapping[<?= htmlspecialchars($headerB) ?>]"
class="form-select form-select-sm">

<option value="">
Tidak Digunakan
</option>

<?php foreach($headersA as $headerA): ?>
<option
value="<?= htmlspecialchars($headerA) ?>"
<?= ($savedMapping === $headerA) ? 'selected' : '' ?>>
<?= htmlspecialchars($headerA) ?>
</option>
<?php endforeach; ?>

</select>
</td>

<td>
<input
type="text"
name="manual_mapping[<?= htmlspecialchars($headerB) ?>]"
class="form-control form-control-sm mapping-input"
value="<?= htmlspecialchars($savedManual) ?>"
list="headersAList"
placeholder="Ketik header master jika ingin override">
</td>

<td>
<?php if($autoMatch !== ''): ?>
<span class="badge bg-success">
Auto
</span>
<?php else: ?>
<span class="badge bg-danger">
Manual
</span>
<?php endif; ?>
</td>

<td class="text-center">
<input
type="checkbox"
class="form-check-input"
checked
disabled>
</td>

</tr>

<?php endforeach; ?>

</tbody>

</table>

</div>

</div>

</div>

<div class="row g-3 mb-4">

<div class="col-lg-6">

<div class="card card-dark h-100">

<div class="card-header">
<h6 class="mb-0">
Kolom Untuk Compare
</h6>
</div>

<div class="card-body">

<div class="small-note mb-3">
Pilih minimal 1 kolom yang dipakai untuk mendeteksi duplicate / mismatch.
</div>

<div class="row">

<?php foreach($headersA as $header): ?>

<div class="col-md-4 col-sm-6">
<div class="form-check">
<input
type="checkbox"
class="form-check-input"
name="compare_keys[]"
value="<?= htmlspecialchars($header) ?>"
<?= is_checked_value($formCompareKeys, $header) ? 'checked' : '' ?>>
<label class="form-check-label">
<?= htmlspecialchars($header) ?>
</label>
</div>
</div>

<?php endforeach; ?>

</div>

</div>

</div>

</div>

<div class="col-lg-6">

<div class="card card-dark h-100">

<div class="card-header">
<h6 class="mb-0">
Kolom Yang Akan Diexport
</h6>
</div>

<div class="card-body">

<div class="small-note mb-3">
Kolom ini akan dipertahankan pada hasil export akhir.
</div>

<div class="row">

<?php foreach($headersA as $header): ?>

<div class="col-md-4 col-sm-6">
<div class="form-check">
<input
type="checkbox"
class="form-check-input"
name="export_columns[]"
value="<?= htmlspecialchars($header) ?>"
<?= is_checked_value($formExportColumns, $header) ? 'checked' : '' ?>>
<label class="form-check-label">
<?= htmlspecialchars($header) ?>
</label>
</div>
</div>

<?php endforeach; ?>

</div>

</div>

</div>

</div>

</div>

<div class="row g-3 mb-4">

<div class="col-lg-6">

<div class="card card-dark h-100">

<div class="card-header">
<h6 class="mb-0">
Mode Compare
</h6>
</div>

<div class="card-body">

<div class="form-check">
<input
class="form-check-input"
type="radio"
name="compare_mode"
value="exact"
<?= $formCompareMode === 'exact' ? 'checked' : '' ?>>
<label class="form-check-label">
Exact Match
</label>
</div>

<div class="form-check">
<input
class="form-check-input"
type="radio"
name="compare_mode"
value="similar"
<?= $formCompareMode === 'similar' ? 'checked' : '' ?>>
<label class="form-check-label">
Similar Match
</label>
</div>

<div class="mt-3">
<label class="form-label">
Similarity %
</label>
<input
type="number"
name="similarity"
class="form-control"
value="<?= (int)$formSimilarity ?>"
min="50"
max="100">
</div>

</div>

</div>

</div>

<div class="col-lg-6">

<div class="card card-dark h-100">

<div class="card-header">
<h6 class="mb-0">
Jenis Analisa
</h6>
</div>

<div class="card-body">

<?php
$optionLabels = [
    'duplicate_internal' => 'Duplicate Internal File B',
    'compare_master' => 'Compare Dengan Master',
    'nik_mismatch' => 'Nama Sama NIK Berbeda',
    'name_mismatch' => 'NIK Sama Nama Berbeda',
    'not_found' => 'Data Tidak Ada Di Master',
];
?>

<?php foreach($optionLabels as $key => $label): ?>

<div class="form-check">
<input
type="checkbox"
class="form-check-input"
name="options[]"
value="<?= htmlspecialchars($key) ?>"
<?= in_array($key, $formOptions, true) ? 'checked' : '' ?>>
<label class="form-check-label">
<?= htmlspecialchars($label) ?>
</label>
</div>

<?php endforeach; ?>

</div>

</div>

</div>

</div>

<div class="card card-dark mb-4">

<div class="card-header">
<h6 class="mb-0">
Custom Mapping Formula
</h6>
</div>

<div class="card-body">

<div class="small-note mb-2">
Opsional, untuk mencatat formula manual jika header tidak cocok 100%.
</div>

<textarea
name="custom_mapping"
class="form-control"
rows="5"
placeholder="Contoh:

Nama Lengkap = First Name + Last Name
NIK = No KTP
Alamat = Domisili"><?= htmlspecialchars($formCustomMapping) ?></textarea>

</div>

</div>

<div class="d-flex gap-2 flex-wrap">

<button
type="submit"
name="save_mapping"
class="btn btn-primary px-4">

Lanjut Process Compare

</button>

<a
href="upload_b.php"
class="btn btn-secondary px-4">

Kembali

</a>

</div>

</form>

</div>

</div>

</div>

</body>
</html>