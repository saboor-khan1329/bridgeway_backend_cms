<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>500 | Server Error</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-12 col-lg-8 col-xl-6">
                <div class="card shadow-sm border-0">
                    <div class="card-body p-4 p-lg-5 text-center">
                        <div class="display-4 fw-bold text-danger mb-3">500</div>
                        <h1 class="h3 mb-3">Unexpected server error</h1>
                        <p class="text-muted mb-3">
                            Something went wrong while processing the request. The issue has been logged.
                        </p>
                        @if (!empty($reference))
                            <p class="small text-muted mb-4">Reference: {{ $reference }}</p>
                        @endif
                        <div class="d-flex flex-column flex-sm-row justify-content-center gap-2">
                            @auth
                                <a href="{{ route('admin.dashboard') }}" class="btn btn-primary">Go to dashboard</a>
                            @endauth
                            <a href="{{ url()->previous() }}" class="btn btn-outline-secondary">Go back</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>

</html>
