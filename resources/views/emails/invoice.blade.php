<!DOCTYPE html><x-mail::message>

<html lang="en"># 💖 Subscription Invoice

<head>

    <meta charset="UTF-8">Hello **{{ $user->name }}**,

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Subscription Invoice</title>Thank you for subscribing to **{{ $payment->subscription->name }}**! Your subscription has been successfully activated.

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>---

        body {

            background-color: #f8f9fa;## 📄 Invoice Details

            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;

        }**Invoice Number:** {{ 'INV-' . str_pad($payment->id, 6, '0', STR_PAD_LEFT) }}  

        .invoice-container {**Date:** {{ $payment->created_at->format('F j, Y') }}  

            max-width: 800px;**Customer:** {{ $user->name }}  

            margin: 0 auto;**Email:** {{ $user->email }}

            background: white;

            box-shadow: 0 0 20px rgba(0,0,0,0.1);---

        }

        .header-section {## 📦 Subscription Details

            background: linear-gradient(135deg, #e91e63, #f06292);

            color: white;**Plan:** {{ $payment->subscription->name }}  

            padding: 2rem;**Duration:** {{ $payment->subscription->duration }} {{ ucfirst($payment->subscription->duration_type) }}{{ $payment->subscription->duration > 1 ? 's' : '' }}  

            text-align: center;**Platform:** {{ ucfirst($payment->platform) }}  

        }@if($payment->amount)

        .feature-badge {**Amount:** ${{ number_format($payment->amount, 2) }}  

            background: linear-gradient(135deg, #e91e63, #f06292);@endif

            color: white;@if($payment->transaction_id)

            padding: 0.25rem 0.75rem;**Transaction ID:** {{ $payment->transaction_id }}  

            border-radius: 50px;@endif

            font-size: 0.875rem;

            display: inline-block;---

            margin: 0.25rem;

        }## ✨ Your Premium Features Include:

        .btn-primary-custom {

            background: linear-gradient(135deg, #e91e63, #f06292);@if($payment->subscription->filter_include ?? false)

            border: none;• 🔍 **Advanced Filters** - Find your perfect match with detailed search options  

            padding: 12px 30px;@endif

            border-radius: 50px;@if($payment->subscription->audio_video ?? false)

            text-decoration: none;• 🎥 **Audio & Video Calls** - Connect with voice and video conversations  

            color: white;@endif

            display: inline-block;@if($payment->subscription->direct_chat ?? false)

            font-weight: 600;• 💬 **Direct Messaging** - Send unlimited messages to your matches  

        }@endif

        .invoice-details {@if($payment->subscription->chat ?? false)

            background: #f8f9fa;• 💭 **Enhanced Chat Features** - Rich messaging experience with multimedia support  

            border-left: 4px solid #e91e63;@endif

        }@if($payment->subscription->like_menu ?? false)

        .footer-section {• ❤️ **Priority Likes** - Get noticed faster with priority visibility  

            background: #2c3e50;@endif

            color: white;

        }---

    </style>

</head>## 🎉 What's Next?

<body>

    <div class="container-fluid p-0">Your subscription is now active and you can start enjoying all the premium features immediately! 

        <div class="invoice-container">

            <!-- Header --><x-mail::button :url="config('app.url', '#')" color="primary">

            <div class="header-section">Open {{ get_option('app_name') }}

                <h1 class="mb-3">💖 Subscription Invoice</h1></x-mail::button>

                <p class="lead mb-0">Thank you for choosing {{ get_option('app_name') }}!</p>

            </div>---



            <!-- Content -->**Invoice Summary:**  

            <div class="p-4">Plan: {{ $payment->subscription->name }}  

                <!-- Greeting -->Duration: {{ $payment->subscription->duration }} {{ ucfirst($payment->subscription->duration_type) }}{{ $payment->subscription->duration > 1 ? 's' : '' }}  

                <div class="mb-4">@if($payment->amount)

                    <h4>Hello <strong>{{ $user->name }}</strong>,</h4>Amount: ${{ $payment->amount }}  

                    <p class="text-muted">Thank you for subscribing to <strong>{{ $payment->subscription->name }}</strong>! Your subscription has been successfully activated.</p>@endif

                </div>Date: {{ $invoiceDate->format('M j, Y \a\t g:i A') }}



                <!-- Invoice Details -->Thank you for choosing {{ get_option('app_name') }}! ❤️

                <div class="row mb-4">

                    <div class="col-md-6">Best regards,  

                        <div class="card invoice-details h-100">**{{ get_option('app_name') }} Team**

                            <div class="card-body"></x-mail::message>

                                <h5 class="card-title text-primary">📄 Invoice Details</h5>
                                <table class="table table-borderless">
                                    <tr>
                                        <td><strong>Invoice Number:</strong></td>
                                        <td>{{ $invoiceNumber }}</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Date:</strong></td>
                                        <td>{{ $invoiceDate->format('F j, Y') }}</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Customer:</strong></td>
                                        <td>{{ $user->name }}</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Email:</strong></td>
                                        <td>{{ $user->email }}</td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-body">
                                <h5 class="card-title text-primary">📦 Subscription Details</h5>
                                <table class="table table-borderless">
                                    <tr>
                                        <td><strong>Plan:</strong></td>
                                        <td>{{ $payment->subscription->name }}</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Duration:</strong></td>
                                        <td>{{ $payment->subscription->duration }} {{ ucfirst($payment->subscription->duration_type) }}{{ $payment->subscription->duration > 1 ? 's' : '' }}</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Platform:</strong></td>
                                        <td><span class="badge bg-secondary">{{ ucfirst($payment->platform) }}</span></td>
                                    </tr>
                                    @if($payment->amount)
                                    <tr>
                                        <td><strong>Amount:</strong></td>
                                        <td><span class="badge bg-success">${{ number_format($payment->amount, 2) }}</span></td>
                                    </tr>
                                    @endif
                                    @if($payment->transaction_id)
                                    <tr>
                                        <td><strong>Transaction ID:</strong></td>
                                        <td><small>{{ $payment->transaction_id }}</small></td>
                                    </tr>
                                    @endif
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Premium Features -->
                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="card-title text-primary">✨ Your Premium Features Include:</h5>
                        <div class="mt-3">
                            @if($payment->subscription->filter_include ?? false)
                            <div class="feature-badge">🔍 Advanced Filters</div>
                            @endif
                            @if($payment->subscription->audio_video ?? false)
                            <div class="feature-badge">🎥 Audio & Video Calls</div>
                            @endif
                            @if($payment->subscription->direct_chat ?? false)
                            <div class="feature-badge">💬 Direct Messaging</div>
                            @endif
                            @if($payment->subscription->chat ?? false)
                            <div class="feature-badge">💭 Enhanced Chat Features</div>
                            @endif
                            @if($payment->subscription->like_menu ?? false)
                            <div class="feature-badge">❤️ Priority Likes</div>
                            @endif
                        </div>
                        <div class="mt-3">
                            <ul class="list-unstyled">
                                @if($payment->subscription->filter_include ?? false)
                                <li class="mb-2">🔍 <strong>Advanced Filters</strong> - Find your perfect match with detailed search options</li>
                                @endif
                                @if($payment->subscription->audio_video ?? false)
                                <li class="mb-2">🎥 <strong>Audio & Video Calls</strong> - Connect with voice and video conversations</li>
                                @endif
                                @if($payment->subscription->direct_chat ?? false)
                                <li class="mb-2">💬 <strong>Direct Messaging</strong> - Send unlimited messages to your matches</li>
                                @endif
                                @if($payment->subscription->chat ?? false)
                                <li class="mb-2">💭 <strong>Enhanced Chat Features</strong> - Rich messaging experience with multimedia support</li>
                                @endif
                                @if($payment->subscription->like_menu ?? false)
                                <li class="mb-2">❤️ <strong>Priority Likes</strong> - Get noticed faster with priority visibility</li>
                                @endif
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Call to Action -->
                <div class="card mb-4">
                    <div class="card-body text-center">
                        <h5 class="card-title">🎉 What's Next?</h5>
                        <p class="card-text">Your subscription is now active and you can start enjoying all the premium features immediately!</p>
                        <a href="{{ config('app.url', '#') }}" class="btn-primary-custom">Open {{ get_option('app_name') }}</a>
                    </div>
                </div>

                <!-- Invoice Summary -->
                <div class="alert alert-info">
                    <h6><strong>Invoice Summary:</strong></h6>
                    <p class="mb-1"><strong>Plan:</strong> {{ $payment->subscription->name }}</p>
                    <p class="mb-1"><strong>Duration:</strong> {{ $payment->subscription->duration }} {{ ucfirst($payment->subscription->duration_type) }}{{ $payment->subscription->duration > 1 ? 's' : '' }}</p>
                    @if($payment->amount)
                    <p class="mb-1"><strong>Amount:</strong> ${{ number_format($payment->amount, 2) }}</p>
                    @endif
                    <p class="mb-0"><strong>Date:</strong> {{ $payment->created_at->format('M j, Y \a\t g:i A') }}</p>
                </div>
            </div>

            <!-- Footer -->
            <div class="footer-section p-4 text-center">
                <p class="mb-2">Thank you for choosing {{ get_option('app_name') }}! ❤️</p>
                <p class="mb-0"><strong>{{ get_option('app_name') }} Team</strong></p>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>