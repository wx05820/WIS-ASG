<?php
include "db.php";

if (!isset($_GET['id'])) {
    http_response_code(400);
    exit("No product ID.");
}

$id = (int) $_GET['id'];

$stmt = $conn->prepare("SELECT image1 FROM product WHERE prodID = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows === 0) {
    http_response_code(404);
    exit("Image not found.");
}

$stmt->bind_result($imageData);
$stmt->fetch();
$stmt->close();

// Detect mime
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime  = $finfo->buffer($imageData) ?: 'image/jpeg';

// Prevent browser from caching same blob
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

header("Content-Type: $mime");
echo $imageData;
