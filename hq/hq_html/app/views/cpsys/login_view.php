<!DOCTYPE html>
<html lang="zh-CN" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TopTea HQ - 登录</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="css/login.css?v=<?php echo time(); ?>">
</head>
<body>
    <div class="login-container">
        <div class="login-box">
            <h2 class="text-center mb-4">TopTea HQ</h2>
            <h5 class="text-center text-white-50 mb-4">后台管理系统</h5>

            <?php if (isset($_GET['error'])): ?>
                <div class="alert alert-danger" role="alert">
                    <?php 
                        $errors = [
                            '1' => '无效的用户名或密码。', '2' => '请输入所有必填项。',
                            '3' => '发生未知错误。', '4' => '访问被拒绝，请先登录。',
                            '5' => '验证码不正确。'
                        ];
                        echo $errors[$_GET['error']] ?? '发生未知错误。';
                    ?>
                </div>
            <?php endif; ?>

            <form action="api/login_handler.php" method="POST">
                <div class="form-floating mb-3">
                    <input type="text" class="form-control" id="username" name="username" placeholder="用户名" required>
                    <label for="username">用户名</label>
                </div>
                <div class="form-floating mb-3">
                    <input type="password" class="form-control" id="password" name="password" placeholder="密码" required>
                    <label for="password">密码</label>
                </div>
                
                <div class="input-group mb-4">
                    <div class="form-floating">
                        <input type="text" class="form-control" id="captcha" name="captcha" placeholder="验证码" required autocomplete="off">
                        <label for="captcha">验证码</label>
                    </div>
                    <img src="api/captcha_generator.php" alt="验证码" id="captcha-image" title="点击刷新" style="cursor: pointer; border-radius: 0 0.375rem 0.375rem 0;">
                </div>

                <div class="d-grid">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="bi bi-box-arrow-in-right me-2"></i>登 录
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script>
        // Simple script to refresh the CAPTCHA image on click
        $('#captcha-image').on('click', function() {
            $(this).attr('src', 'api/captcha_generator.php?' + new Date().getTime());
        });
    </script>
</body>
</html>