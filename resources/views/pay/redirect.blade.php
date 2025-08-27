{{--// resources/views/pay/redirect.blade.php--}}
<!DOCTYPE html>
<html lang="uk">
<head><meta charset="utf-8"><title>Оплата</title></head>
<body>
<p>Переходимо до оплати…</p>
<form id="wfp" action="https://secure.wayforpay.com/pay" method="POST" accept-charset="utf-8">
    @foreach($fields as $key=>$value)
        @if(is_array($value))
            @foreach($value as $v)
                <input type="hidden" name="{{ $key }}[]" value="{{ e($v) }}">
            @endforeach
        @else
            <input type="hidden" name="{{ $key }}" value="{{ e($value) }}">
        @endif
    @endforeach
</form>
<script>document.getElementById('wfp').submit();</script>
</body></html>
