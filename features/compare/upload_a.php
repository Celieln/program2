<?php

session_start();

require '../../config/database.php';
require '../../functions/auth.php';

checkLogin();

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
            '../../uploads/compare/file_a/';

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
            uniqid('MASTER_')
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

                $_SESSION['compare_file_a']
                =
                $destination;

                header(
                    'Location:upload_b.php'
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

Upload Master File

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

</style>

</head>

<body>

<div class="container mt-5">

<div class="row justify-content-center">

<div class="col-lg-8">

<div class="card card-dark shadow">

<div class="card-header bg-primary text-white">

<h4 class="mb-0">

STEP 1
Upload Master File (File A)

</h4>

</div>

<div class="card-body">

<div class="alert alert-info">

<b>File A = Master Data</b>

<br><br>

Contoh kolom:

<ul class="mb-0">

<li>Nama</li>
<li>NIK</li>
<li>Alamat</li>
<li>Status</li>

</ul>

<br>

File ini akan dijadikan acuan utama saat proses compare.

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

Pilih File Master

</label>

<input
type="file"
name="file"
class="form-control"
accept=".xlsx,.xls,.csv"
required>

</div>

<button
type="submit"
name="upload"
class="btn btn-success">

Upload & Lanjut

</button>

<a
href="../../dashboard/index.php"
class="btn btn-secondary">

Dashboard

</a>

</form>

</div>

</div>

</div>

</div>

</div>

</body>

</html>