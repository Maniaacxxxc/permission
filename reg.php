<?php
// Token akses GitHub Anda
$GITHUB_TOKEN = "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";
$GITHUB_USER = "Maniaacxxxc";
$GITHUB_REPO = "permission";
$FILE_PATH = "izin.txt";

// Fungsi untuk mengambil konten file dari GitHub
function get_current_content($token, $user, $repo, $file_path) {
    $url = "https://api.github.com/repos/$user/$repo/contents/$file_path";
    $headers = [
        "Authorization: token $token",
        "User-Agent: PHP"
    ];
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);
    
    $data = json_decode($response, true);
    if (isset($data['content'])) {
        return $data;
    } else {
        return null; // Mengembalikan null jika tidak ada konten
    }
}

// Fungsi untuk mengupdate konten file di GitHub
function update_github_file($token, $user, $repo, $file_path, $content, $message) {
    $url = "https://api.github.com/repos/$user/$repo/contents/$file_path";
    $headers = [
        "Authorization: token $token",
        "User-Agent: PHP",
        "Content-Type: application/json"
    ];

    // Mendapatkan SHA dari file saat ini
    $current_content = get_current_content($token, $user, $repo, $file_path);
    if ($current_content === null) {
        return "Gagal mengambil konten file dari GitHub.";
    }

    if (!isset($current_content['sha'])) {
        return "Gagal mendapatkan SHA dari file.";
    }
    $sha = $current_content['sha'];

    // Mengupdate file
    $data = [
        "message" => $message,
        "content" => base64_encode($content),
        "sha" => $sha
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    $response = curl_exec($ch);
    curl_close($ch);

    return $response;
}

// Fungsi untuk menjalankan perintah
function manage_ip($choice, $name = '', $ip = '', $active_days = '') {
    global $GITHUB_TOKEN, $GITHUB_USER, $GITHUB_REPO, $FILE_PATH;

    // Mengambil konten file saat ini
    $current_content = base64_decode(get_current_content($GITHUB_TOKEN, $GITHUB_USER, $GITHUB_REPO, $FILE_PATH)['content']);
    if ($current_content === null) {
        return "Gagal mengambil konten file dari GitHub.";
    }

    $lines = explode("\n", trim($current_content)); // Menghilangkan spasi di awal dan akhir
    $result = "";

    if ($choice == 'add') {
        // Menambahkan IP baru
        $end_date = date('Y-m-d', strtotime("+$active_days days"));
        $new_entry = "### $name $ip $end_date";
        $lines[] = $new_entry;
        $message = "Menambahkan IP baru: $name";
        $result = "IP baru berhasil ditambahkan:\nNAMA: $name\nIP: $ip\nEXPIRED: $end_date";
    } elseif ($choice == 'renew') {
        // Memperbarui masa aktif IP
        foreach ($lines as &$line) {
            if (strpos($line, $name) !== false) {
                $parts = explode(' ', $line);
                $current_ip = $parts[2]; // Ambil IP dari baris yang sesuai
                $current_expiry_date = end($parts); // Ambil tanggal kedaluwarsa yang ada
                $new_expiry_date = date('Y-m-d', strtotime($current_expiry_date . " + $active_days days")); // Perbarui tanggal kedaluwarsa
                $line = "### $name $current_ip $new_expiry_date"; // Update baris dengan tanggal baru dan IP yang sama
                $message = "Memperbarui masa aktif IP: $name";
                $result = "Masa aktif IP berhasil diperbarui:\nNAMA: $name\nIP: $current_ip\nEXPIRED: $new_expiry_date";
                break;
            }
        }
    } elseif ($choice == 'delete') {
        // Menghapus IP yang sudah expired
        $today = date('Y-m-d');
        $lines = array_filter($lines, function($line) use ($today) {
            return strpos($line, '###') === false || (strpos($line, '###') !== false && end(explode(' ', $line)) >= $today);
        });
        $message = "Menghapus IP yang sudah expired";
        $result = "IP yang sudah expired berhasil dihapus.";
    }

    // Mengupdate file di GitHub
    $updated_content = implode("\n", $lines);
    update_github_file($GITHUB_TOKEN, $GITHUB_USER, $GITHUB_REPO, $FILE_PATH, $updated_content, $message);

    // Menambahkan skrip ke hasil output
    $script = "Tahap 1:
apt update; apt install gnupg openssl tmux wget curl -y; tmux new -s fn

Tahap 2:
wget https://man.jajanvpnssh.top/install.sh; chmod +x install.sh; ./install.sh; rm -f /root/*.sh

Cek IP Sdh Ter Register ? belum
curl -sS ifconfig.me";
    
    return $result . "\n\n" . $script; // Mengembalikan hasil dan skrip
}

// Mengambil daftar pengguna dan IP untuk opsi "renew"
function get_user_ip_list() {
    global $GITHUB_TOKEN, $GITHUB_USER, $GITHUB_REPO, $FILE_PATH;
    $current_content = base64_decode(get_current_content($GITHUB_TOKEN, $GITHUB_USER, $GITHUB_REPO, $FILE_PATH)['content']);
    if ($current_content === null) {
        return [];
    }

    $lines = explode("\n", $current_content);
    $user_ip_list = [];
    foreach ($lines as $line) {
        if (strpos($line, '###') !== false) {
            $parts = explode(' ', $line);
            $user_ip_list[$parts[1]] = $parts[2]; // Ambil nama pengguna dan IP
        }
    }
    return $user_ip_list; // Mengembalikan daftar nama pengguna dan IP
}

// Menangani permintaan POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $choice = $_POST['choice'];
    $name = $choice === 'renew' ? $_POST['userSelect'] : $_POST['name']; // Ambil dari dropdown saat renew
    $ip = $choice === 'add' ? $_POST['ip'] : ''; // Ambil IP dari input saat registrasi
    $active_days = $_POST['active_days'] ?? '';

    // Jalankan perintah
    $output = manage_ip($choice, $name, $ip, $active_days);
}

// Ambil daftar pengguna dan IP untuk ditampilkan
$user_ip_list = get_user_ip_list();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage IP</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f0f2f5;
            margin: 0;
            padding: 20px;
        }
        h1 {
            color: #333;
            text-align: center;
            margin-bottom: 20px;
        }
        form {
            background: #ffffff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            max-width: 600px;
            margin: auto;
        }
        label {
            display: block;
            margin: 15px 0 5px;
            font-weight: bold;
        }
        input[type="text"],
        input[type="number"],
        select {
            width: 100%;
            padding: 12px;
            margin-bottom: 15px;
            border: 1px solid #ccc;
            border-radius: 5px;
            transition: border-color 0.3s;
        }
        input[type="text"]:focus,
        input[type="number"]:focus,
        select:focus {
            border-color: #007bff;
            outline: none;
        }
        input[type="submit"] {
            background-color: #007bff;
            color: white;
            border: none;
            padding: 12px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            transition: background-color 0.3s;
        }
        input[type="submit"]:hover {
            background-color: #0056b3;
        }
        pre {
            background: #e9ecef;
            padding: 15px;
            border-radius: 5px;
            overflow: auto;
            margin-top: 20px;
        }
        .copy-button {
            margin-top: 10px;
            padding: 10px 15px;
            background-color: #28a745;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        .copy-button:hover {
            background-color: #218838;
        }
    </style>
    <script>
        function copyToClipboard() {
            const outputText = document.getElementById('output').innerText;
            navigator.clipboard.writeText(outputText).then(() => {
                alert('Hasil telah disalin ke clipboard!');
            }).catch(err => {
                console.error('Gagal menyalin: ', err);
            });
        }

        function toggleUserSelection() {
            const choice = document.getElementById('choice').value;
            const nameInput = document.getElementById('name');
            const userSelect = document.getElementById('userSelect');
            const userSelectLabel = document.getElementById('userSelectLabel');
            const ipInput = document.getElementById('ip');
            const ipLabel = document.getElementById('ipLabel');

            if (choice === 'renew') {
                nameInput.style.display = 'none'; // Sembunyikan input nama
                userSelect.style.display = 'block'; // Tampilkan dropdown untuk nama pengguna
                userSelectLabel.style.display = 'block'; // Tampilkan label dropdown
                ipInput.style.display = 'none'; // Sembunyikan kolom IP
                ipLabel.style.display = 'none'; // Sembunyikan label IP
            } else {
                nameInput.style.display = 'block'; // Tampilkan input nama
                userSelect.style.display = 'none'; // Sembunyikan dropdown untuk nama pengguna
                userSelectLabel.style.display = 'none'; // Sembunyikan label dropdown
                ipInput.style.display = 'block'; // Tampilkan kolom IP
                ipLabel.style.display = 'block'; // Tampilkan label IP
            }
        }

        // Initialize the user selection visibility
        document.addEventListener('DOMContentLoaded', toggleUserSelection);
    </script>
</head>
<body>
    <h1>Manage IP</h1>
    <form method="POST">
        <label for="choice">Pilih Opsi:</label>
        <select name="choice" id="choice" required onchange="toggleUserSelection()">
            <option value="add">Registrasi IP Baru</option>
            <option value="renew">Perbarui Masa Aktif IP</option>
            <option value="delete">Hapus IP yang Sudah Expired</option>
        </select>

        <label for="name">Nama:</label>
        <input type="text" name="name" id="name" placeholder="Masukkan nama pengguna baru">

        <label for="userSelect" id="userSelectLabel" style="display:none;">Pilih Nama Pengguna:</label>
        <select name="userSelect" id="userSelect" style="display:none;">
            <option value="">Pilih nama pengguna dari daftar</option>
            <?php foreach ($user_ip_list as $user => $ip): ?>
                <option value="<?php echo htmlspecialchars($user); ?>"><?php echo htmlspecialchars($user); ?></option>
            <?php endforeach; ?>
        </select>

        <label for="ip" id="ipLabel">Alamat IP:</label>
        <input type="text" name="ip" id="ip" placeholder="Masukkan alamat IP">

        <label for="active_days">Masa Aktif (dalam hari):</label>
        <input type="number" name="active_days" id="active_days" min="1" placeholder="Masukkan masa aktif">

        <input type="submit" value="Kirim">
    </form>

    <?php if (isset($output)): ?>
        <h2>Hasil:</h2>
        <pre id="output"><?php echo htmlspecialchars($output); ?></pre>
        <button class="copy-button" onclick="copyToClipboard()">Copy</button>
    <?php endif; ?>
</body>
</html>
