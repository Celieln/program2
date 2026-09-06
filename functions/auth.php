<?php

if (!function_exists('checkLogin')) {

    function checkLogin(): void
    {

        if (
            !isset($_SESSION['user_id']) ||
            empty($_SESSION['user_id'])
        ) {

            header('Location: ../index.php');
            exit;

        }

    }

}