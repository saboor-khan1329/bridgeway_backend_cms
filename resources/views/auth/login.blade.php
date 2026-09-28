<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Login' }} | Bridgeway Digital CMS</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Lexend+Deca:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">

    <style>
        :root {
            --bd-primary: #FF763A;
            --bd-primary-hover: #e66228;
            --bd-dark: #121d28;
        }

        body {
            font-family: 'Lexend Deca', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif !important;
            background: radial-gradient(circle at top center, #1e3144 0%, #121d28 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            padding: 1.5rem;
        }

        .login-box {
            width: 100%;
            max-width: 440px;
        }

        .card {
            border: 1px solid rgba(255, 255, 255, 0.08) !important;
            border-radius: 16px !important;
            box-shadow: 0 20px 45px rgba(0, 0, 0, 0.35) !important;
            background: #ffffff;
            overflow: hidden;
        }

        .btn-primary {
            background-color: var(--bd-primary) !important;
            border-color: var(--bd-primary) !important;
            font-weight: 600;
            padding: 0.65rem 1rem;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(255, 118, 58, 0.3);
            transition: all 0.2s ease;
        }

        .btn-primary:hover {
            background-color: var(--bd-primary-hover) !important;
            border-color: var(--bd-primary-hover) !important;
            box-shadow: 0 6px 16px rgba(255, 118, 58, 0.4);
            transform: translateY(-1px);
        }

        .form-control:focus {
            border-color: var(--bd-primary) !important;
            box-shadow: 0 0 0 0.2rem rgba(255, 118, 58, 0.25) !important;
        }

        .input-group-text {
            background-color: #f8fafc;
            border-color: #ced4da;
            color: #64748b;
        }
    </style>
</head>
<body class="hold-transition login-page">
    <div class="login-box">
        <div class="text-center mb-4">
            <img src="{{ asset('images/white-logo.svg') }}" alt="Bridgeway Digital" style="max-height: 48px; width: auto;" onerror="this.onerror=null; this.style.display='none'; document.getElementById('logo-fallback').style.display='block';">
            <h2 id="logo-fallback" class="font-weight-bold text-white mb-0" style="display:none; letter-spacing: 0.5px;">Bridgeway Digital</h2>
            <p class="text-white-50 small mt-2">Content Management & Administration</p>
        </div>

        <div class="card">
            <div class="card-body p-4 p-sm-5">
                <h4 class="text-center font-weight-bold mb-1" style="color: #121d28;">Admin Sign In</h4>
                <p class="text-muted text-center small mb-4">Enter your credentials to access the CMS portal</p>

                @if ($errors->any())
                    <div class="alert alert-danger small border-0 py-2 px-3 mb-4" style="border-radius: 8px; background-color: #fee2e2; color: #991b1b;">
                        @foreach ($errors->all() as $err)
                            <div><i class="fas fa-exclamation-circle me-1"></i> {{ $err }}</div>
                        @endforeach
                    </div>
                @endif

                <form action="{{ route('login.attempt') }}" method="POST" novalidate>
                    @csrf
                    <div class="mb-3">
                        <label class="form-label small font-weight-medium">Email Address</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                            <input type="email" name="email" value="{{ old('email') }}" class="form-control" placeholder="admin@bridgewaydigital.test" required autofocus>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small font-weight-medium">Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                            <input type="password" name="password" class="form-control" placeholder="••••••••" required>
                        </div>
                    </div>
                    <div class="form-check mb-4">
                        <input type="checkbox" name="remember" id="remember" class="form-check-input" value="1">
                        <label for="remember" class="form-check-label small text-muted">Keep me signed in</label>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">
                        Sign In to Portal <i class="fas fa-arrow-right ms-1"></i>
                    </button>
                </form>
            </div>
        </div>

        <p class="text-center text-white-50 small mt-4 mb-0">
            &copy; {{ date('Y') }} Bridgeway Digital. Secure System Access.
        </p>
    </div>
</body>
</html>
