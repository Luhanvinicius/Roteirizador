<?php
session_start();
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $login = $_POST['login'];
    $senha = $_POST['senha'];

    // Conexão ao banco de dados
    $conn = new mysqli("localhost", "root", "", "roteirizador");

    // Consulta para verificar o login e senha
    $sql = "SELECT id FROM motoristas WHERE login = ? AND senha = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $login, $senha);
    $stmt->execute();
    $stmt->bind_result($motorista_id);

    if ($stmt->fetch()) {
        $_SESSION['motorista_id'] = $motorista_id;
        header("Location: rota.php");
    } else {
        echo "Login ou senha incorretos";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Login</title>
</head>
<body>
    <form method="POST" action="">
        <label>Login:</label>
        <input type="text" name="login" required><br>
        <label>Senha:</label>
        <input type="password" name="senha" required><br>
        <button type="submit">Login</button>
    </form>
</body>
</html>
