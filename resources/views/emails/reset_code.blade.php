<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>{{ __('Password Reset Code') }}</title>
</head>
<body>
    <p>{{ __('Use the following code to reset your password:') }}</p>
    <h2 style="letter-spacing:4px;">{{ $code }}</h2>
    <p>{{ __('This code will expire in 10 minutes.') }}</p>
</body>
</html>
