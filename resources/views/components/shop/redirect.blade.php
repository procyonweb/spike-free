@if(isset($redirectTo))
<p class="mt-6 text-gray-600">
    {!! __('spike::translations.redirecting_to', ['seconds' => $redirectDelay ?? 5]) !!}
</p>
<a href="{{ $redirectTo }}" class="mt-6 inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-brand hover:bg-opacity-80 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-brand">
    {{ __('spike::translations.redirect_now') }}
</a>
<script>
    var secondsElement = document.getElementById('redirect-seconds');
    var seconds = {{ $redirectDelay ?? 5 }};
    var interval = null;

    var redirectFunc = function() {
        if (seconds <= 0) {
            window.location.href = '{{ $redirectTo }}';
            clearInterval(interval);
        } else {
            seconds--;
            secondsElement.innerHTML = seconds;
        }
    };

    interval = setInterval(redirectFunc, 1000);
</script>
@endif
