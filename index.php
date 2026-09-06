<?php

session_start();

require 'config/database.php';

$error = '';

if(
    isset($_POST['login'])
)
{

    $username =
    trim(
        $_POST['username']
    );

    $password =
    trim(
        $_POST['password']
    );

    $username =
    mysqli_real_escape_string(
        $conn,
        $username
    );

    $query =
    mysqli_query(
        $conn,
        "
        SELECT *
        FROM users
        WHERE username='$username'
        LIMIT 1
        "
    );

    if(
        mysqli_num_rows(
            $query
        )
    )
    {

        $user =
        mysqli_fetch_assoc(
            $query
        );

        if(
            password_verify(
                $password,
                $user['password']
            )
        )
        {

            $_SESSION['user_id']
            =
            $user['id'];

            $_SESSION['username']
            =
            $user['username'];

            header(
                'Location: dashboard/index.php'
            );

            exit;

        }

    }

    $error =
    'Username atau Password salah';

}
?>

<!DOCTYPE html>
<html>

<head>

<meta charset="utf-8">

<title>

Login Program2

</title>

<link
href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
rel="stylesheet">

</head>

<body
class="bg-dark">

<div
class="container">

<div
class="row justify-content-center mt-5">

<div
class="col-md-4">

<div
class="card shadow">

<div
class="card-header">

Login Program2

</div>

<div
class="card-body">

<?php if($error): ?>

<div
class="alert alert-danger">

<?= $error ?>

</div>

<?php endif; ?>

<form method="POST">

<input
type="text"
name="username"
class="form-control mb-3"
placeholder="Username"
required>

<input
type="password"
name="password"
class="form-control mb-3"
placeholder="Password"
required>

<button
type="submit"
name="login"
class="btn btn-primary w-100">

Login

</button>

</form>

</div>

</div>

</div>

</div>

</div>

</body>

</html>