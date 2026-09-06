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
    mysqli_num_rows(
        $query
    ) == 0
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

$rowId =
(int)(
    $data['compare_row_id']
    ?? 0
);

if(
    $rowId <= 0
)
{
    die(
        'compare_row_id tidak ditemukan'
    );
}

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
    mysqli_num_rows(
        $rowQuery
    ) == 0
)
{
    die(
        'Row target tidak ditemukan'
    );
}

mysqli_query(
$conn,
"
UPDATE compare_rows
SET
    row_status='deleted'
WHERE id='$rowId'
"
);

mysqli_query(
$conn,
"
UPDATE compare_duplicates
SET
    status='fixed'
WHERE id='$id'
"
);

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
    'ROW ACTIVE',
    'ROW DELETED',
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

header(
    'Location:result.php'
);

exit;
?>
