<?php
require_once '../_base.php';
include '../database/db.php';
$sql = "SELECT prodID, name, description, price, qty, color, image1 FROM product";
$result = $conn->query($sql);

if(!$result){
    die("Query failed: ".$conn->error);
}

$user_id = $_SESSION['user_id'];
$current_user = getCurrentUser();

include '../header.php';?>

<script src="/js/cart.js" defer></script>
<link rel="stylesheet" href="../css/index.css">

<main class="container-product">
    <h1 class="prod-title">Product List</h1>
    <div class="products">
        <?php while($row = $result->fetch_assoc()): ?>
            <div class="product">
                <?php
                //demo only
                $img='';
                if(!empty($row['image1'])){
                    $img = 'data:image/jpeg;base64,'.base64_encode($row['image1']);
                }?>

                <img src="/database/img.php?id=<?= urlencode($row['prodID'])?>" alt="<?= htmlspecialchars($row['name']) ?>">
                <h3><?= htmlspecialchars($row['name']) ?></h3>
                <p><?= htmlspecialchars($row['description']) ?></p>
                <p><strong><?= money($row['price']) ?></strong></p>
                <button onclick="addToCart('<?= $row['prodID']?>')">Add To Cart</button>
            </div>
        <?php endwhile ?>
    </div>
    <a href="/order/cart_page.php">Go to cart</a>
</main>

<?php include '../footer.php';
