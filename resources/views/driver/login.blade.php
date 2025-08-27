<!doctype html>
<html lang="uk">
<head>
    <meta charset="utf-8">
    <title>Логін водія</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>body{font-family:sans-serif;max-width:420px;margin:40px auto}</style>
</head>
<body>
<h2>Логін водія</h2>
@if ($errors->any())
    <div style="color:#b00;margin-bottom:10px;">
        {{ $errors->first() }}
    </div>
@endif
<form method="post" action="{{ route('driver.login.post') }}">
    @csrf
    <div>
        <label>Email</label><br>
        <input name="email" type="email" value="{{ old('email') }}" required autofocus>
    </div>
    <div style="margin-top:8px">
        <label>Пароль</label><br>
        <input name="password" type="password" required>
    </div>
    <button style="margin-top:12px">Увійти</button>
</form>
</body>
</html>
