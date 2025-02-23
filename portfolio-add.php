<?php
$host = 'localhost';
$user = 'root';
$pass = '';
$db = 'artists_and_creatives';

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'];

    if ($action == 'addPortfolio') {
        $name = $_POST['name'];
        $title = $_POST['title'];
        $description = $_POST['description'];

        $imageName = $_FILES['image']['name'];
        $imageTmp = $_FILES['image']['tmp_name'];
        $imageType = strtolower(pathinfo($imageName, PATHINFO_EXTENSION));
        $allowedTypes = ['jpg', 'jpeg', 'png'];

        if (in_array($imageType, $allowedTypes)) {
            $uploadDir = 'uploads/' . $imageName;
            move_uploaded_file($imageTmp, $uploadDir);

            $stmt = $conn->prepare("INSERT INTO portfolio_add (name, image, title, description) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $name, $uploadDir, $title, $description);
            $stmt->execute();

            echo json_encode(["status" => "success"]);
        } else {
            echo json_encode(["status" => "error", "message" => "Only JPG, JPEG, and PNG files are allowed."]);
        }
    } elseif ($action == 'deletePortfolio') {
        $id = $_POST['id'];
        $stmt = $conn->prepare("DELETE FROM portfolio_add WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();

        echo json_encode(["status" => "success"]);
    } elseif ($action == 'editPortfolio') {
        $id = $_POST['id'];
        $name = $_POST['name'];
        $title = $_POST['title'];
        $description = $_POST['description'];

        if (isset($_FILES['image']) && $_FILES['image']['name'] != '') {
            $imageName = $_FILES['image']['name'];
            $imageTmp = $_FILES['image']['tmp_name'];
            $imageType = strtolower(pathinfo($imageName, PATHINFO_EXTENSION));
            $allowedTypes = ['jpg', 'jpeg', 'png'];

            if (in_array($imageType, $allowedTypes)) {
                $uploadDir = 'uploads/' . $imageName;
                move_uploaded_file($imageTmp, $uploadDir);

                $stmt = $conn->prepare("UPDATE portfolio_add SET name = ?, image = ?, title = ?, description = ? WHERE id = ?");
                $stmt->bind_param("ssssi", $name, $uploadDir, $title, $description, $id);
            } else {
                echo json_encode(["status" => "error", "message" => "Only JPG, JPEG, and PNG files are allowed."]);
                exit();
            }
        } else {
            $stmt = $conn->prepare("UPDATE portfolio_add SET name = ?, title = ?, description = ? WHERE id = ?");
            $stmt->bind_param("sssi", $name, $title, $description, $id);
        }

        $stmt->execute();
        echo json_encode(["status" => "success"]);
    }
    exit();
}

$result = $conn->query("SELECT * FROM portfolio_add");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portfolio</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .table-container { width: 90%; margin: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ccc; padding: 10px; text-align: center; }
        th { background-color: #f4f4f4; }
        .add-btn { float: right; padding: 10px 20px; background-color: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer; }
        .add-btn:hover { background-color: #218838; }
        .popup { display: none; position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); background-color: white; padding: 20px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.2); z-index: 10; width: 400px; }
        .popup input, .popup textarea { width: 100%; padding: 10px; margin: 8px 0; border: 1px solid #ccc; border-radius: 4px; }
        .overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 5; }
        .error { color: red; text-align: center; }
    </style>
</head>
<body>

<div class="table-container">
    <button class="add-btn" onclick="openPopup()">Add</button>
    <h2>Portfolio List</h2>
    <table id="portfolioTable">
        <tr>
            <th>ID</th>
            <th>Name</th>
            <th>Image</th>
            <th>Title</th>
            <th>Description</th>
            <th>Edit</th>
            <th>Delete</th>
        </tr>
        <?php while($row = $result->fetch_assoc()): ?>
        <tr id="row-<?php echo $row['id']; ?>">
            <td><?php echo $row['id']; ?></td>
            <td><?php echo $row['name']; ?></td>
            <td><img src="<?php echo $row['image']; ?>" width="50"></td>
            <td><?php echo $row['title']; ?></td>
            <td><?php echo $row['description']; ?></td>
            <td><button onclick="editRow(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars($row['name'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($row['title'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($row['description'], ENT_QUOTES); ?>', '<?php echo $row['image']; ?>')">Edit</button></td>
            <td><button onclick="deleteRow(<?php echo $row['id']; ?>)">Delete</button></td>
        </tr>
        <?php endwhile; ?>
    </table>
</div>

<div class="overlay" id="overlay"></div>
<div class="popup" id="popup">
    <h3 id="popupTitle">Add Portfolio Item</h3>
    <form id="portfolioForm" enctype="multipart/form-data">
        <input type="hidden" name="id" id="portfolioId">
        <input type="text" name="name" id="name" placeholder="Name" required>
        <input type="file" name="image" id="image" accept=".jpg,.jpeg,.png">
        <input type="text" name="title" id="title" placeholder="Title" required>
        <textarea name="description" id="description" placeholder="Description" required></textarea>
        <input type="hidden" name="action" id="action" value="addPortfolio">
        <input type="submit" value="Save">
        <p class="error" id="errorMsg"></p>
    </form>
</div>

<script>
function openPopup() {
    document.getElementById('popupTitle').innerText = 'Add Portfolio Item';
    document.getElementById('action').value = 'addPortfolio';
    document.getElementById('portfolioForm').reset();
    document.getElementById('popup').style.display = 'block';
    document.getElementById('overlay').style.display = 'block';
}

function closePopup() {
    document.getElementById('popup').style.display = 'none';
    document.getElementById('overlay').style.display = 'none';
    document.getElementById('errorMsg').innerText = '';
}

document.getElementById('overlay').addEventListener('click', closePopup);

document.getElementById('portfolioForm').addEventListener('submit', function(e) {
    e.preventDefault();

    const formData = new FormData(this);

    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            location.reload();
        } else {
            document.getElementById('errorMsg').innerText = data.message;
        }
    });
});

function deleteRow(id) {
    if (confirm('Are you sure you want to delete this item?')) {
        fetch('', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=deletePortfolio&id=${id}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                document.getElementById(`row-${id}`).remove();
            }
        });
    }
}

function editRow(id, name, title, description, image) {
    document.getElementById('popupTitle').innerText = 'Edit Portfolio Item';
    document.getElementById('action').value = 'editPortfolio';
    document.getElementById('portfolioId').value = id;
    document.getElementById('name').value = name;
    document.getElementById('title').value = title;
    document.getElementById('description').value = description;
    document.getElementById('popup').style.display = 'block';
    document.getElementById('overlay').style.display = 'block';
}
</script>

</body>
</html>
