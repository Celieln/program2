<?php

session_start();

require '../../config/database.php';
require '../../functions/auth.php';

checkLogin();

if(
    !isset($_SESSION['compare_file_a'])
)
{
    header(
        'Location:upload_a.php'
    );

    exit;
}

$message = '';

if(isset($_POST['upload']))
{

    if(
        !isset($_FILES['file'])
        ||
        $_FILES['file']['error'] != 0
    )
    {

        $message =
        'Silahkan pilih file terlebih dahulu';

    }
    else
    {

        $ext =
        strtolower(
            pathinfo(
                $_FILES['file']['name'],
                PATHINFO_EXTENSION
            )
        );

        $allowed =
        [
            'xlsx',
            'xls',
            'csv'
        ];

        if(
            !in_array(
                $ext,
                $allowed
            )
        )
        {

            $message =
            'Format file harus XLSX, XLS atau CSV';

        }
        else
        {

            $uploadDir =
            '../../uploads/compare/file_b/';

            if(
                !is_dir($uploadDir)
            )
            {

                mkdir(
                    $uploadDir,
                    0777,
                    true
                );

            }

            $fileName =
            uniqid('TARGET_')
            .
            '.'
            .
            $ext;

            $destination =
            $uploadDir
            .
            $fileName;

            if(
                move_uploaded_file(
                    $_FILES['file']['tmp_name'],
                    $destination
                )
            )
            {

                $_SESSION['compare_file_b']
                =
                $destination;

                header(
                    'Location:mapping.php'
                );

                exit;

            }
            else
            {

                $message =
                'Gagal upload file';

            }

        }

    }

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

Upload Target File

</title>

<link
href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
rel="stylesheet">

<style>

body{
    background:#0f172a;
    color:#f8fafc;
}

.card-dark{
    background:#111827;
    border:1px solid #334155;
    border-radius:18px;
}

.upload-area{
    border:2px dashed #475569;
    border-radius:12px;
    padding:30px;
}

.step-badge{
    font-size:14px;
}

</style>

</head>

<body>

<div class="container mt-5">

<div class="row justify-content-center">

<div class="col-lg-8">

<div class="card card-dark shadow">

<div class="card-header bg-success text-white">

<h4 class="mb-0">

STEP 2
Upload Target File (File B)

</h4>

</div>

<div class="card-body">

<div class="alert alert-warning">

<b>File B = Target Data</b>

<br><br>

File ini akan diperiksa terhadap Master Data.

Sistem akan mendeteksi:

<ul class="mb-0 mt-2">

<li>Duplicate Internal</li>

<li>Data Tidak Ada Di Master</li>

<li>Nama Sama NIK Berbeda</li>

<li>NIK Sama Nama Berbeda</li>

<li>Mismatch Data</li>

<li>Data Yang Perlu Direview</li>

</ul>

</div>

<?php if(!empty($message)): ?>

<div class="alert alert-danger">

<?= htmlspecialchars($message) ?>

</div>

<?php endif; ?>

<form
method="POST"
enctype="multipart/form-data">

<div class="upload-area mb-3">

<label class="form-label">

Pilih File Target

</label>

<input
type="file"
name="file"
class="form-control"
accept=".xlsx,.xls,.csv"
required>

</div>

<div class="d-flex gap-2">

<button
type="submit"
name="upload"
class="btn btn-success">

Upload & Lanjut Mapping

</button>

<a
href="upload_a.php"
class="btn btn-secondary">

Kembali

</a>

</div>

</form>

<hr>

<div class="alert alert-info mb-0">

<b>Alur Sistem</b>

<br>

Upload Master →
Upload Target →
Mapping →
Pilih Kolom Compare →
Process →
Review →
Export Clean File

</div>

</div>

</div>

</div>

</div>

</div>

</body>

</html>