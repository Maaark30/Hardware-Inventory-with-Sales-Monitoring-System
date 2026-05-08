<?php
include 'project.php';

if (isset($_GET['sale_group_id'])) {
    $sale_group_id = intval($_GET['sale_group_id']);

    $sql = "
        SELECT s.sale_id, s.product_id, p.product_name, s.quantity, s.total_price, s.sale_date
        FROM sales s
        JOIN products p ON s.product_id = p.product_id
        WHERE s.sale_group_id = ?
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $sale_group_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0): ?>
        <div class="table-responsive">
          <table class="table table-bordered align-middle">
            <thead class="table-light">
              <tr>
                <th>Sale ID</th>
                <th>Product</th>
                <th>Quantity</th>
                <th>Total Price</th>
                <th>Date</th>
              </tr>
            </thead>
            <tbody>
              <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                  <td>#<?php echo $row['sale_id']; ?></td>
                  <td><?php echo htmlspecialchars($row['product_name']); ?></td>
                  <td><?php echo $row['quantity']; ?></td>
                  <td>₱<?php echo number_format($row['total_price'], 2); ?></td>
                  <td><?php echo date("M d, Y h:i A", strtotime($row['sale_date'])); ?></td>
                </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>
    <?php else: ?>
        <p class="text-center text-muted">No details found for this sale.</p>
    <?php endif;

    $stmt->close();
}
?>
