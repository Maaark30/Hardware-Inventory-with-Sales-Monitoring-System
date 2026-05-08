<?php
include 'project.php';

// Fetch products
$sql = "SELECT * FROM products ORDER BY created_at DESC";
$result = mysqli_query($conn, $sql);

if (mysqli_num_rows($result) > 0):
    while ($row = mysqli_fetch_assoc($result)): ?>
        <tr>
          <td><?php echo $row['product_id']; ?></td>
          <td><?php echo htmlspecialchars($row['product_name']); ?></td>
          <td>
            <?php
              $catId = $row['category_id'];
              $catRes = mysqli_query($conn, "SELECT category_name FROM categories WHERE category_id=$catId");
              $catRow = mysqli_fetch_assoc($catRes);
              echo htmlspecialchars($catRow['category_name'] ?? 'Unknown');
            ?>
          </td>
          <td>₱<?php echo number_format($row['price'], 2); ?></td>
          <td>
            <?php if ($row['stock'] < 10): ?>
              <span class="badge bg-danger"><?php echo $row['stock']; ?></span>
            <?php else: ?>
              <span class="badge bg-success"><?php echo $row['stock']; ?></span>
            <?php endif; ?>
          </td>
          <td>
            <?php if (!empty($row['image_path'])): ?>
              <img src="<?php echo $row['image_path']; ?>" alt="Product Image" width="50" height="50" class="rounded">
            <?php else: ?>
              <span class="text-muted">No Image</span>
            <?php endif; ?>
          </td>
          <td><?php echo htmlspecialchars($row['barcode']); ?></td>
          <td><?php echo date("M d, Y h:i A", strtotime($row['created_at'])); ?></td>
          <td>
            <a href="edit_product.php?id=<?php echo $row['product_id']; ?>" 
               class="btn btn-sm btn-warning"><i class="bi bi-pencil"></i></a>
            <a href="manage_product.php?id=<?php echo $row['product_id']; ?>" 
               class="btn btn-sm btn-danger" 
               onclick="return confirm('Are you sure you want to delete this product?');"><i class="bi bi-trash"></i></a>
          </td>
        </tr>
    <?php endwhile;
else: ?>
    <tr>
      <td colspan="9" class="text-center text-muted">No products found.</td>
    </tr>
<?php endif; ?>
