<?php
include('db.php');

// 檢查資料庫連接是否成功
if (!$conn) {
    die("資料庫連接失敗: " . mysqli_connect_error());
}

// 從資料庫讀取所有座位的狀態
$sql = "SELECT * FROM seats";
$result = $conn->query($sql);

// 將座位資料儲存在陣列中
$seats = [];
while ($row = $result->fetch_assoc()) {
    $seats[] = $row;
}

// 處理預約請求
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['seat_id']) && isset($_POST['user_name'])) {
    $seat_id = $_POST['seat_id'];
    $user_name = $_POST['user_name'];

    // 查詢座位是否已經預約
    $check_sql = "SELECT * FROM seats WHERE seat_id = ? AND is_reserved = 1";
    $stmt = $conn->prepare($check_sql);
    $stmt->bind_param('i', $seat_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => '該座位已經被預約']);
        exit();
    }

    // 更新該座位的預約資料
    $update_sql = "UPDATE seats SET is_reserved = 1, reserved_by = ? WHERE seat_id = ?";
    $stmt = $conn->prepare($update_sql);
    $stmt->bind_param('si', $user_name, $seat_id);
    $stmt->execute();
    echo json_encode(['success' => true]);
    exit();
}

// 處理取消預約請求
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['cancel_seat_id'])) {
    $cancel_seat_id = $_POST['cancel_seat_id'];

    // 取消該座位的預約
    $cancel_sql = "UPDATE seats SET is_reserved = 0, reserved_by = NULL WHERE seat_id = ?";
    $stmt = $conn->prepare($cancel_sql);
    $stmt->bind_param('i', $cancel_seat_id);
    $stmt->execute();
    echo json_encode(['success' => true]);
    exit();
}
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>教室座位預約系統</title>
    <style>
        body {
            font-family: 'Arial', sans-serif;
            background-color: #f0f0f0;
            margin: 0;
            padding: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100vh;
        }

        .classroom {
            text-align: center;
            margin-top: 20px;
            background-color: white;
            padding: 20px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            border-radius: 10px;
            width: 80%;
            max-width: 800px;
        }

        #date-time {
            font-size: 32px;
            color: #333;
            margin-bottom: 15px;
            padding: 10px;
            background-color: #f9f9f9;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .teacher-desk {
            width: 180px;
            height: 60px;
            background-color: #2a9d8f;
            margin: 20px auto;
            text-align: center;
            line-height: 60px;
            font-weight: bold;
            color: white;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .columns {
            display: flex;
            justify-content: center;
            flex-direction: row-reverse;
        }

        .column {
            margin: 0 10px;
        }

        .seat {
            width: 50px;
            height: 50px;
            margin: 5px 0;
            background-color: #6abf69;
            display: block;
            line-height: 50px;
            text-align: center;
            color: white;
            font-weight: bold;
            cursor: pointer;
            border-radius: 5px;
            transition: background-color 0.3s;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .seat:hover {
            background-color:rgb(138, 218, 135);
        }

        .seat.occupied {
            background-color:rgb(209, 45, 58);
        }

        .seat.unavailable {
            background-color: grey;
            cursor: not-allowed;
        }
    </style>
</head>
<body>
    <div class="classroom">
        <div id="date-time"></div>

        <div class="teacher-desk"><h2>605教室</h2></div>

        <div class="columns" id="columns">
            <?php
            $columns = array_chunk($seats, 10);
            foreach ($columns as $index => $column) {
                echo "<div class='column'>";
                echo "<strong>第 " . ($index + 1) . " 排</strong>";
                foreach ($column as $seat) {
                    $seat_class = $seat['is_reserved'] ? 'seat occupied' : 'seat';
                    $seat_text = $seat['is_reserved'] ? $seat['reserved_by'] : $seat['seat_number'];
                    echo "<div class='$seat_class' data-seat='{$seat['seat_column']}-{$seat['seat_number']}' data-seat-id='{$seat['seat_id']}'>$seat_text</div>";
                }
                echo "</div>";
            }
            ?>
        </div>
    </div>

    <script>
        document.querySelectorAll('.seat').forEach(seat => {
            seat.addEventListener('click', function() {
                const seatId = seat.getAttribute('data-seat-id');
                const seatText = seat.textContent;

                // 如果座位已經被預約
                if (seat.classList.contains('occupied')) {
                    const currentUser = seatText;  // 預約者的名字
                    const cancelAction = confirm(`該座位已經被 ${currentUser} 預約，是否取消預約？`);

                    // 如果用戶確認取消預約
                    if (cancelAction) {
                        // 發送取消預約請求
                        const formData = new FormData();
                        formData.append('cancel_seat_id', seatId);

                        fetch('index.php', {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                seat.classList.remove('occupied');
                                seat.textContent = seat.getAttribute('data-seat').split('-')[1];  // 恢復座位號
                            }
                        });
                    }
                } else {
                    const userName = prompt('請輸入您的名字');

                    if (userName) {
                        // 發送預約請求
                        const formData = new FormData();
                        formData.append('seat_id', seatId);
                        formData.append('user_name', userName);

                        fetch('index.php', {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                seat.classList.add('occupied');
                                seat.textContent = userName;
                            } else {
                                alert(data.message);  // 顯示預約失敗的訊息
                            }
                        });
                    }
                }
            });
        });

        function updateDateTime() {
            const now = new Date();
            const date = now.toLocaleDateString();
            const time = now.toLocaleTimeString();
            document.getElementById('date-time').textContent = `${date} | ${time}`;
        }

        updateDateTime();
        setInterval(updateDateTime, 1000);
    </script>
</body>
</html>
