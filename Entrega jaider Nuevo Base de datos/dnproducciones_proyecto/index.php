<?php
session_start();
if (isset($_SESSION['id_usuario'])) {
    header("Location: admin.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - DN Producciones</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { 
            background-image: url('fondo.jpg'); 
            background-size: cover; 
            background-position: center; 
            background-repeat: no-repeat;
            background-attachment: fixed;
            display: flex; 
            align-items: center; 
            justify-content: center; 
            height: 100vh; 
            margin: 0;
        }
        .header-bar {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100px;
            background: rgba(0, 0, 0, 0.6);
            z-index: 1;
        }
        .login-card { width: 100%; max-width: 400px; padding: 30px; border-radius: 10px; box-shadow: 0 4px 8px rgba(0,0,0,0.5); background: #000000; color: #ffffff; z-index: 3; position: relative; }
    </style>
    <link rel="icon" href="logo_app.png" type="image/png">
</head>
<body>
<!-- Loader Overlay -->
<div id="page-loader" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: #212529; z-index: 9999; display: flex; flex-direction: column; justify-content: center; align-items: center; transition: opacity 0.4s ease; color: white;">
    <div class="spinner-border text-primary" style="width: 4rem; height: 4rem; border-width: 0.4em;" role="status">
        <span class="visually-hidden">Cargando...</span>
    </div>
    <h4 class="mt-4 fw-bold" style="letter-spacing: 2px;">DN PRODUCCIONES</h4>
    <p id="loader-text" class="text-muted mt-2" style="font-size: 1.1em;">Cargando datos...</p>
</div>

<script>
    var hasSuccessMessage = <?php echo isset($_SESSION['mensaje']) ? 'true' : 'false'; ?>;
    
    window.addEventListener('load', function() {
        var loader = document.getElementById('page-loader');
        var loaderText = document.getElementById('loader-text');
        
        if(loader) {
            // Spinner por 1 segundo
            setTimeout(function() {
                if (loaderText) {
                    if (hasSuccessMessage) {
                        loaderText.innerText = "Cargado con éxito";
                        loaderText.className = "text-success fw-bold mt-2";
                    } else {
                        loaderText.innerText = "Finalizado";
                        loaderText.className = "text-info fw-bold mt-2";
                    }
                }
                
                // Mensaje de finalizado por 0.8 segundos (Total 1.8s)
                setTimeout(function() {
                    loader.style.opacity = '0';
                    setTimeout(function() {
                        loader.style.display = 'none';
                    }, 400); 
                }, 800);
            }, 1000);
        }
    });

    document.addEventListener('DOMContentLoaded', function() {
        var links = document.querySelectorAll('a.nav-link, a.navbar-brand, a.btn');
        links.forEach(function(link) {
            link.addEventListener('click', function(e) {
                if (link.getAttribute('href') !== '#' && !link.hasAttribute('data-bs-toggle') && !link.hasAttribute('onclick')) {
                    showLoader("Cargando datos...");
                }
            });
        });

        var forms = document.querySelectorAll('form');
        forms.forEach(function(form) {
            form.addEventListener('submit', function(e) {
                showLoader("Cargando archivo...");
            });
        });
        
        function showLoader(text) {
            var loader = document.getElementById('page-loader');
            var loaderText = document.getElementById('loader-text');
            if(loader) {
                if (loaderText && text) {
                    loaderText.innerText = text;
                    loaderText.className = "text-muted mt-2";
                }
                loader.style.display = 'flex';
                setTimeout(function() {
                    loader.style.opacity = '1';
                }, 10);
            }
        }
    });
</script>



    <div class="header-bar"></div>
    <img src="logo_dn_transparente.png" alt="Logo DN Producciones" style="position: absolute; top: 10px; left: 20px; max-height: 80px; z-index: 2;">
    <div class="login-card">
        <h3 class="text-center mb-4">DN Producciones</h3>
        <?php
        if (isset($_SESSION['error'])) {
            echo '<div class="alert alert-danger">'.$_SESSION['error'].'</div>';
            unset($_SESSION['error']);
        }
        ?>
        <form action="login.php" method="POST">
            <div class="mb-3">
                <label for="email" class="form-label">Correo Electrónico</label>
                <input type="email" class="form-control" id="email" name="email" required placeholder="ejemplo@mail.com">
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">Contraseña</label>
                <input type="password" class="form-control" id="password" name="password" required>
            </div>
            <button type="submit" class="btn btn-info w-100 fw-bold" style="color: white; background-color: #0dcaf0;">Iniciar Sesión</button>
        </form>
    </div>
</body>
</html>
