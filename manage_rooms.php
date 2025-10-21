<?php require 'header.php'; require 'auth.php'; require_admin(); ?>

<div class="container">
  <h1 class="h1">Manage Rooms</h1>
  <a class="btn primary" href="room_add.php">Add New Room</a>

  <table class="table" style="margin-top:20px">
    <thead>
      <tr>
        <th>ID</th>
        <th>Number</th>
        <th>Type</th>
        <th>Rate</th>
        <th>Max Guests</th>
        <th>Status</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php
      $rooms = $pdo->query("SELECT * FROM rooms ORDER BY number")->fetchAll();
      foreach ($rooms as $r): ?>
        <tr>
          <td><?= $r['id'] ?></td>
          <td><?= htmlspecialchars($r['number']) ?></td>
          <td><?= htmlspecialchars($r['type']) ?></td>
          <td>$<?= number_format($r['rate_cents']/100, 2) ?></td>
          <td><?= (int)$r['max_guests'] ?></td>
          <td><?= $r['is_active'] ? 'Active' : 'Inactive' ?></td>
          <td>
            <a class="btn small" href="room_edit.php?id=<?= $r['id'] ?>">Edit</a>
            <a class="btn small danger" href="room_delete.php?id=<?= $r['id'] ?>" onclick="return confirm('Delete this room?')">Delete</a>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php require 'footer.php'; ?>
