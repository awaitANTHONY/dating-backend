<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Deletion Request</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .form-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            padding: 40px;
            max-width: 600px;
            margin: 20px auto;
        }
        .form-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .form-header h2 {
            color: #667eea;
            font-weight: 700;
            margin-bottom: 10px;
        }
        .form-header p {
            color: #6c757d;
            font-size: 14px;
        }
        .btn-submit {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            padding: 12px 30px;
            font-weight: 600;
            transition: all 0.3s;
        }
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }
        .custom-radio .custom-control-label {
            cursor: pointer;
            padding: 10px 15px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            transition: all 0.3s;
            display: block;
        }
        .custom-radio .custom-control-input:checked ~ .custom-control-label {
            background: #f0f3ff;
            border-color: #667eea;
            color: #667eea;
            font-weight: 600;
        }
        .alert {
            border-radius: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="form-container">
            <div class="form-header">
                <h2>Account Deletion Request</h2>
                <p>We're sorry to see you go. Please fill out this form to request account deletion.</p>
            </div>

            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <strong>Success!</strong> {{ session('success') }}
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            @endif

            @if($errors->any())
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <strong>Error!</strong>
                    <ul class="mb-0">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            @endif

            <form action="{{ route('account-delete-request.submit') }}" method="POST">
                @csrf
                
                <div class="form-group">
                    <label for="email" class="font-weight-bold">Email Address <span class="text-danger">*</span></label>
                    <input type="email" 
                           class="form-control @error('email') is-invalid @enderror" 
                           id="email" 
                           name="email" 
                           placeholder="Enter your registered email"
                           value="{{ old('email') }}"
                           required>
                    @error('email')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <small class="form-text text-muted">Please use the email address associated with your account.</small>
                </div>

                <div class="form-group">
                    <label class="font-weight-bold d-block mb-3">Request Type <span class="text-danger">*</span></label>
                    
                    <div class="custom-control custom-radio mb-3">
                        <input type="radio" 
                               id="type1" 
                               name="type" 
                               class="custom-control-input" 
                               value="1" 
                               {{ old('type') == '1' ? 'checked' : '' }}
                               required>
                        <label class="custom-control-label" for="type1">
                            <strong>Clear My Data</strong>
                            <br>
                            <small class="text-muted">Remove all my personal information but keep the account active</small>
                        </label>
                    </div>

                    <div class="custom-control custom-radio">
                        <input type="radio" 
                               id="type2" 
                               name="type" 
                               class="custom-control-input" 
                               value="2"
                               {{ old('type') == '2' ? 'checked' : '' }}>
                        <label class="custom-control-label" for="type2">
                            <strong>Delete My Account & Data</strong>
                            <br>
                            <small class="text-muted">Permanently delete my account and all associated data</small>
                        </label>
                    </div>
                    
                    @error('type')
                        <div class="text-danger mt-2"><small>{{ $message }}</small></div>
                    @enderror
                </div>

                <div class="alert alert-warning mt-4">
                    <strong>⚠️ Important:</strong> This action may be irreversible. Please make sure you want to proceed.
                </div>

                <div class="text-center mt-4">
                    <button type="submit" class="btn btn-submit btn-primary btn-lg btn-block">
                        Submit Request
                    </button>
                </div>
            </form>

            <div class="text-center mt-4">
                <small class="text-muted">
                    Having second thoughts? <a href="#" class="text-primary">Contact Support</a>
                </small>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
