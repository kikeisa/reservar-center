{{-- resources/views/welcome.blade.php --}}

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('app.name', 'Reservar Center') }}</title>

    {{-- Bootstrap --}}
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    {{-- FontAwesome --}}
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css"/>

    <style>
        body{
            background: #f5f7fb;
            font-family: 'Segoe UI', sans-serif;
        }

        .navbar{
            background: #ffffff;
            box-shadow: 0 2px 10px rgba(0,0,0,.05);
        }

        .hero{
            min-height: 90vh;
            display: flex;
            align-items: center;
        }

        .hero-title{
            font-size: 60px;
            font-weight: 800;
            color: #1f2937;
        }

        .hero-text{
            color: #6b7280;
            font-size: 18px;
        }

        .btn-main{
            background: #ffc107;
            border: none;
            color: #000;
            padding: 14px 30px;
            border-radius: 14px;
            font-weight: 600;
        }

        .btn-main:hover{
            background: #ffca2c;
        }

        .card-feature{
            border: none;
            border-radius: 20px;
            padding: 30px;
            background: white;
            box-shadow: 0 5px 20px rgba(0,0,0,.05);
            transition: .3s;
        }

        .card-feature:hover{
            transform: translateY(-5px);
        }

        .icon-box{
            width: 70px;
            height: 70px;
            background: #fff3cd;
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
        }

        footer{
            background: #111827;
            color: white;
            padding: 25px 0;
        }
    </style>
</head>
<body>

    {{-- NAVBAR --}}
    <nav class="navbar navbar-expand-lg py-3">
        <div class="container">

            <a class="navbar-brand fw-bold fs-3" href="#">
                <i class="fa-solid fa-calendar-check text-warning"></i>
                Reservar Center
            </a>

            <button class="navbar-toggler" data-bs-toggle="collapse" data-bs-target="#menu">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="menu">

                <ul class="navbar-nav ms-auto align-items-lg-center">

                    <li class="nav-item me-3">
                        <a href="#" class="nav-link">Inicio</a>
                    </li>

                    <li class="nav-item me-3">
                        <a href="#" class="nav-link">Servicios</a>
                    </li>

                    <li class="nav-item me-3">
                        <a href="#" class="nav-link">Reservas</a>
                    </li>

                    @guest
                        <li class="nav-item me-2">
                            <a href="{{ route('login') }}" class="btn btn-outline-dark rounded-pill px-4">
                                Ingresar
                            </a>
                        </li>

                        <li class="nav-item">
                            <a href="{{ route('register') }}" class="btn btn-warning rounded-pill px-4">
                                Registro
                            </a>
                        </li>
                    @else
                        <li class="nav-item">
                            <a href="{{ url('/home') }}" class="btn btn-dark rounded-pill px-4">
                                Panel
                            </a>
                        </li>
                    @endguest

                </ul>

            </div>

        </div>
    </nav>

    {{-- HERO --}}
    <section class="hero">
        <div class="container">

            <div class="row align-items-center">

                <div class="col-lg-6">

                    <h1 class="hero-title mb-4">
                        Sistema de Reservas Inteligente
                    </h1>

                    <p class="hero-text mb-4">
                        Administra reservas, clientes y horarios de manera rápida y moderna con Reservar Center.
                    </p>

                    <a href="#" class="btn btn-main">
                        <i class="fa-solid fa-calendar-plus"></i>
                        Crear Reserva
                    </a>

                </div>

                <div class="col-lg-6 text-center">

                    <img 
                        src="https://images.unsplash.com/photo-1522199710521-72d69614c702?q=80&w=1200&auto=format&fit=crop"
                        class="img-fluid rounded-5 shadow-lg"
                    >

                </div>

            </div>

        </div>
    </section>

    {{-- FEATURES --}}
    <section class="py-5">
        <div class="container">

            <div class="row g-4">

                <div class="col-lg-4">

                    <div class="card-feature h-100">

                        <div class="icon-box">
                            <i class="fa-solid fa-calendar-days fa-2x text-warning"></i>
                        </div>

                        <h4 class="fw-bold">
                            Reservas Online
                        </h4>

                        <p class="text-muted">
                            Gestiona citas y reservas desde cualquier dispositivo.
                        </p>

                    </div>

                </div>

                <div class="col-lg-4">

                    <div class="card-feature h-100">

                        <div class="icon-box">
                            <i class="fa-solid fa-users fa-2x text-warning"></i>
                        </div>

                        <h4 class="fw-bold">
                            Gestión de Clientes
                        </h4>

                        <p class="text-muted">
                            Administra toda la información de tus clientes fácilmente.
                        </p>

                    </div>

                </div>

                <div class="col-lg-4">

                    <div class="card-feature h-100">

                        <div class="icon-box">
                            <i class="fa-solid fa-chart-line fa-2x text-warning"></i>
                        </div>

                        <h4 class="fw-bold">
                            Reportes
                        </h4>

                        <p class="text-muted">
                            Visualiza estadísticas y crecimiento de reservas.
                        </p>

                    </div>

                </div>

            </div>

        </div>
    </section>

    {{-- FOOTER --}}
    <footer>
        <div class="container text-center">

            © {{ date('Y') }} Reservar Center - Todos los derechos reservados

        </div>
    </footer>

    {{-- JQuery --}}
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

    {{-- Bootstrap --}}
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        $(document).ready(function(){

            console.log('Reservar Center iniciado');

        });
    </script>

</body>
</html>